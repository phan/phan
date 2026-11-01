<?php

declare(strict_types=1);

namespace Phan\Tests\Analysis;

use Phan\Analysis;
use Phan\Analysis\EmitOnlyChecks;
use Phan\Config;
use Phan\IssueInstance;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\UnionType;
use Phan\Library\PhaseTimer;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Phan\Tests\CodeBaseAwareTestBase;

/**
 * Tests that replaying the declaration analysis of inherited methods (Analysis\CloneReplay)
 * leaves the methods in the same state and emits the same issues as running it.
 */
final class CloneReplayTest extends CodeBaseAwareTestBase
{
    private const FILE = 'clone_replay_test.php';

    private const CODE = <<<'EOT'
<?php
namespace CloneReplayTest;

interface Shape {
    /** @param int[] $values */
    public function area(array $values, ?string $unit = null): float;
}

interface NamedShape extends Shape {
    public function name(string $prefix = '', bool ...$flags);
}

abstract class AbstractShape implements NamedShape {
}

abstract class Base {
    const LIMIT = 3;

    public function __construct(int $x = 0) {
    }

    /**
     * @param int[] $values
     * @param string|false $flag
     * @param array<string,int> $options
     */
    public function plain(array $values, $flag = false, int $limit = self::LIMIT, array $options = [], ...$rest) {
        return $values;
    }

    /**
     * @param string $out @phan-output-reference
     */
    public function reference(&$out, ?Base $other = null): void {
        $out = 'x';
    }

    public static function make(?self $other = null, string $name = 'default'): ?static {
        return null;
    }

    /** @return static */
    public function fluent() {
        return $this;
    }

    public function linked() {
        return 42;
    }

    public function inferredVoid(int $x) {
    }

    /**
     * @param int $missing
     */
    public function withIssue(string $x) {
    }

    /**
     * @param int $other_missing
     * @suppress PhanCommentParamWithoutRealParam
     */
    public function withSuppressedIssue(string $x) {
    }

    /**
     * @param string $first @phan-mandatory-param
     */
    public function mandatory($first = '', $second = null) {
    }

    /** @param \stdClass[] $list */
    abstract public function abstractMethod(iterable $list);
}

/**
 * @template T
 */
class Generic extends Base {
    /** @param T $value */
    public function set($value) {
    }

    /** @param int[] $ids */
    public function ids(array $ids, int $count = 0) {
    }

    /** @param \stdClass[] $list */
    public function abstractMethod(iterable $list) {
    }
}

/** @extends Generic<int> */
class IntGeneric extends Generic {
}

class Child extends Base {
    /** @param \stdClass[] $list */
    public function abstractMethod(iterable $list) {
    }
}

class GrandChild extends Child {
    /** @inheritDoc */
    public function plain(array $values, $flag = false, int $limit = self::LIMIT, array $options = [], ...$rest) {
        return $values;
    }
}

final class FinalChild extends GrandChild {
}

/** @no-named-arguments */
class NoNamedArguments extends Child {
}

trait Greets {
    /** @param string[] $names */
    public function greet(array $names, string $greeting = 'hello') {
        return $greeting;
    }

    /** @param int[] $values */
    protected function fromTrait(array $values) {
    }
}

class UsesTrait extends Child {
    use Greets;
}

class ExtendsTraitUser extends UsesTrait {
}

abstract class ImplementsLater extends AbstractShape {
    public function area(array $values, ?string $unit = null): float {
        return 0.0;
    }
}

abstract class InheritsImplementation extends ImplementsLater {
}
EOT;

    public function setUp(): void
    {
        parent::setUp();
        Config::setValue('check_docblock_signature_param_type_match', true);
        Config::setValue('prefer_narrowed_phpdoc_param_type', true);
        Config::setValue('analyze_signature_compatibility', true);
        Config::setValue('inherit_phpdoc_types', true);
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    public function tearDown(): void
    {
        \putenv('PHAN_DISABLE_CLONE_REPLAY');
        \putenv('PHAN_DUMP_METHOD_STATE_DIGEST');
        PhaseTimer::resetForTests();  // @phan-suppress-current-line PhanAccessMethodInternal
        parent::tearDown();
    }

    public function testReplayIsIdenticalToTheDeclarationAnalysis(): void
    {
        [$digest, $issues, $notes] = $this->analyze(false);
        // Analyze the same code in a new code base, with the declaration analysis of every method
        $this->setUp();
        [$expected_digest, $expected_issues, $expected_notes] = $this->analyze(true);

        $this->assertArrayNotHasKey('clone_replay_eligible', $expected_notes);
        $notes_text = (string)\json_encode($notes, \JSON_PRETTY_PRINT);
        $this->assertGreaterThan(20, $notes['clone_replay_eligible'] ?? 0, "Expected inherited methods to be replayed: $notes_text");
        // The clones of methods whose declaration analysis checks an issue (even a suppressed one) are not replayed
        $this->assertGreaterThan(0, $notes['clone_replay_ineligible_source_issues'] ?? 0, $notes_text);
        // The clones of methods with template types are not replayed
        $this->assertGreaterThan(0, $notes['clone_replay_ineligible_source_template'] ?? 0, $notes_text);
        // A walk over the overridden methods may find an inherited (at)phan-mandatory-param
        $this->assertGreaterThan(0, $notes['clone_replay_ineligible_mandatory_param_walk'] ?? 0, $notes_text);
        $this->assertGreaterThan(0, $notes['clone_replay_ineligible_construct'] ?? 0, $notes_text);
        // The clones in a class with (at)no-named-arguments have different flags
        $this->assertGreaterThan(0, $notes['clone_replay_ineligible_fingerprint_phan_flags'] ?? 0, $notes_text);
        $this->assertGreaterThan(0, $notes['clone_replay_eligible_interface'] ?? 0, $notes_text);

        $this->assertSame($expected_issues, $issues);
        $this->assertSame($expected_digest, $digest);
        $this->assertStringContainsString('suppress={"PhanCommentParamWithoutRealParam":', $digest);
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall, PhanAccessMethodInternal
     */
    public function testFingerprintMatching(): void
    {
        $this->analyze(false);
        $method = $this->code_base->getMethodByFQSEN(FullyQualifiedMethodName::fromFullyQualifiedString('\CloneReplayTest\FinalChild::plain'));
        $this->assertInstanceOf(Method::class, $method->getCloneSource());
        $fingerprint = $method->getCloneReplayFingerprint();
        $this->assertTrue($method->matchesCloneReplayFingerprint($fingerprint));
        foreach ([0, 1, 2, 3, 4, 5, 6, 7] as $i) {
            $changed_fingerprint = $fingerprint;
            $changed_fingerprint[$i] = 'changed';
            $this->assertFalse($method->matchesCloneReplayFingerprint($changed_fingerprint), "changed part $i");
        }
        $this->assertFalse($method->matchesCloneReplayFingerprint(\array_slice($fingerprint, 0, -1)));
        $parameter_fingerprint = $fingerprint[8];
        $this->assertIsArray($parameter_fingerprint);
        foreach (\array_keys($parameter_fingerprint) as $i) {
            $changed_fingerprint = $fingerprint;
            $changed_parameter_fingerprint = $parameter_fingerprint;
            $changed_parameter_fingerprint[$i] = 'changed';
            $changed_fingerprint[8] = $changed_parameter_fingerprint;
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            $this->assertFalse($method->matchesCloneReplayFingerprint($changed_fingerprint), "changed part $i of the first parameter");
        }
        foreach (['\CloneReplayTest\GrandChild::plain', '\CloneReplayTest\Child::plain', '\CloneReplayTest\Base::plain'] as $other_fqsen) {
            $other = $this->code_base->getMethodByFQSEN(FullyQualifiedMethodName::fromFullyQualifiedString($other_fqsen));
            $this->assertSame($other->getCloneReplayFingerprint() === $fingerprint, $other->matchesCloneReplayFingerprint($fingerprint), $other_fqsen);
        }

        // The state that the declaration analysis writes can be restored
        $parameter = $method->getParameterList()[0];
        $state = $parameter->getCloneReplayState();
        $parameter->setUnionType(UnionType::fromFullyQualifiedPHPDocString('string'));
        $this->assertFalse($method->matchesCloneReplayFingerprint($fingerprint));
        $parameter->setCloneReplayState($state);
        $this->assertSame($state, $parameter->getCloneReplayState());
        $this->assertTrue($method->matchesCloneReplayFingerprint($fingerprint));
    }

    /**
     * Parse and analyze self::CODE in $this->code_base like Phan does before the analysis phase
     *
     * @suppress PhanThrowTypeAbsentForCall
     * @return array{0:string,1:list<string>,2:array<string,mixed>} the state digest, the issues and the phase timing notes
     */
    private function analyze(bool $disable_replay): array
    {
        $digest_path = (string)\tempnam(\sys_get_temp_dir(), 'phan_clone_replay');
        \putenv($disable_replay ? 'PHAN_DISABLE_CLONE_REPLAY=1' : 'PHAN_DISABLE_CLONE_REPLAY');
        \putenv("PHAN_DUMP_METHOD_STATE_DIGEST=$digest_path");
        PhaseTimer::resetForTests();  // @phan-suppress-current-line PhanAccessMethodInternal
        PhaseTimer::$enabled = true;
        $collector = new BufferingCollector();
        Phan::setIssueCollector($collector);
        try {
            $code_base = $this->code_base;
            Analysis::parseFile($code_base, self::FILE, false, self::CODE);
            $code_base->setShouldHydrateRequestedElements(true);
            Analysis::analyzeClasses($code_base);
            Analysis::analyzeFunctions($code_base);
            EmitOnlyChecks::maybeDumpStateDigest($code_base);
            $notes = PhaseTimer::buildReport()['notes'];  // @phan-suppress-current-line PhanAccessMethodInternal
        } finally {
            PhaseTimer::resetForTests();  // @phan-suppress-current-line PhanAccessMethodInternal
        }
        $digest = (string)\file_get_contents($digest_path);
        \unlink($digest_path);
        $issues = \array_map(static function (IssueInstance $issue): string {
            return $issue->getFile() . ':' . $issue->getLine() . ' ' . $issue->getIssue()->getType() . ' ' . $issue->getMessage();
        }, $collector->getCollectedIssues());
        $this->assertIsArray($notes);
        return [$digest, $issues, $notes];
    }
}
