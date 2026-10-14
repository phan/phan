<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Analysis;
use Phan\Analysis\CloneReplay;
use Phan\Analysis\EmitOnlyChecks;
use Phan\Analysis\LazyInheritedMethods;
use Phan\Analysis\MethodDeclarationAnalyzer;
use Phan\CodeBase;
use Phan\CodeBase\ClassMap;
use Phan\CodeBase\InheritedMethodLink;
use Phan\Config;
use Phan\Daemon\Request;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\TypedElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Library\None;
use Phan\Library\PhaseTimer;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AnalyzeMethodSkipsInheritedCopiesCapability;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests of the lazily created copies of inherited methods (lazy_inherited_methods, Analysis\LazyInheritedMethods):
 * the copies and the order of the methods are those of eager inheritance.
 *
 * The vendor/ fixture is parsed and excluded from analysis (exclude_analysis_directory_list), as in a project.
 *
 * @phan-file-suppress PhanAccessMethodInternal, PhanAccessPropertyInternal
 */
final class LazyInheritedMethodsTest extends TestBase
{
    private const VENDOR_CODE = <<<'PHP'
<?php
/** @suppress PhanUndeclaredMethod */
class VRoot6002 {
    /** The analysis of a copy of this checks issues: the parameter type is undeclared */
    public function helper(?UndeclaredType6002 $x = null): int { return 1; }
    public function __construct() { $this->helper(); }
    public function a(): int { return 1; }
    /** @param string $s */
    public function b($s): string { return $s; }
    public function untyped() {}
    public static function st(): void {}
    private function priv(): void {}
}
class VMid6002 extends VRoot6002 {
    public function own(): int { return 2; }
}
PHP;

    private const SRC_CODE = <<<'PHP'
<?php
class SChild6002 extends VMid6002 {}
class SOther6002 extends VRoot6002 {}
interface SI6002 { public function a(): int; }
class SPin6002 extends VMid6002 implements SI6002 {}
class SGrand6002 extends SChild6002 {
    public function own(): int { return $this->a() + 3; }
}
class SBase6002 {
    /** Runs */
    public function run(int $x): int { return $x; }
}
class SKid6002 extends SBase6002 {}
class SKidChild6002 extends SKid6002 {}
class SLate6002 extends VMid6002 {}
/** @suppress PhanUndeclaredMethod */
class SSuppress6002 {}
PHP;

    /** @var array<string,string|false> environment variables to restore */
    private $saved_env = [];

    public function setUp(): void
    {
        parent::setUp();
        foreach (['PHAN_LAZY_INHERITED_METHODS', 'PHAN_DISABLE_LAZY_INHERIT', 'PHAN_LAZY_INHERIT_VERIFY', 'PHAN_DISABLE_CLONE_REPLAY', 'PHAN_DUMP_METHOD_STATE_DIGEST', 'PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED'] as $name) {
            $this->saved_env[$name] = \getenv($name);
            \putenv($name);
        }
        Config::setValue('exclude_analysis_directory_list', ['vendor/']);
    }

    public function tearDown(): void
    {
        foreach ($this->saved_env as $name => $value) {
            \putenv($value === false ? $name : "$name=$value");
        }
        PhaseTimer::resetForTests();
        Config::reset();
        parent::tearDown();
    }

    /**
     * Parses the fixtures, without hydrating any class. With $lazy, lazy entries can be created (the class phase).
     */
    private static function makeCodeBase(bool $lazy): CodeBase
    {
        $code_base = new CodeBase([], [], [], [], []);
        Phan::setIssueCollector(new BufferingCollector());
        foreach (['vendor/6002.php' => self::VENDOR_CODE, 'src/6002.php' => self::SRC_CODE] as $file => $code) {
            FileCache::addEntry($file, $code);
            FileCache::addEntry(Config::projectPath($file), $code);
            Analysis::parseNodeInContext(
                $code_base,
                (new Context())->withFile($file),
                \ast\parse_code($code, Config::AST_VERSION)
            );
        }
        if ($lazy) {
            $registry = new LazyInheritedMethods($code_base);
            $registry->creation_open = true;
            $registry->registration_open = true;
            $code_base->setLazyInherited($registry);
        }
        $code_base->setShouldHydrateRequestedElements(true);
        return $code_base;
    }

    private static function registry(CodeBase $code_base): LazyInheritedMethods
    {
        $registry = $code_base->getLazyInherited();
        if (!$registry) {
            throw new \AssertionError('expected a registry');
        }
        return $registry;
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    private static function getClass(CodeBase $code_base, string $name): Clazz
    {
        return $code_base->getClassByFQSENWithoutHydrating(FullyQualifiedClassName::fromFullyQualifiedString($name));
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    private static function classMap(CodeBase $code_base, string $name): ClassMap
    {
        return $code_base->getClassMapForLazyInheritance(FullyQualifiedClassName::fromFullyQualifiedString($name));
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    private static function method(CodeBase $code_base, string $fqsen): Method
    {
        return $code_base->getMethodByFQSEN(FullyQualifiedMethodName::fromFullyQualifiedString($fqsen));
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    private static function hydrate(CodeBase $code_base, string ...$names): void
    {
        foreach ($names as $name) {
            self::getClass($code_base, $name)->hydrate($code_base);
        }
    }

    /**
     * A description of the state of $method that its uses depend on (the same for eager and lazy copies).
     */
    private static function describe(Method $method): string
    {
        return \implode(' ', [
            (string)$method->getFQSEN(),
            'def=' . $method->getDefiningFQSEN(),
            'real_def=' . $method->getRealDefiningFQSEN(),
            'flags=' . $method->getFlags(),
            'phan_flags=' . $method->getPhanFlags(),
            'type=' . $method->getUnionType(),
            'real=' . $method->getRealReturnType(),
            'params=' . \implode(',', \array_map(static function (Parameter $parameter): string {
                return $parameter->getName() . ':' . $parameter->getUnionType();
            }, $method->getParameterList())),
            'source=' . ($method->getCloneSource()?->getDefiningFQSEN() ?? '-'),
        ]);
    }

    /**
     * @param iterable<Method> $methods
     * @return list<string>
     */
    private static function fqsens(iterable $methods): array
    {
        $result = [];
        foreach ($methods as $method) {
            $result[] = (string)$method->getFQSEN();
        }
        return $result;
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    public function testSlotsAndOrderAreThoseOfEagerCopies(): void
    {
        $classes = ['\SChild6002', '\SOther6002', '\SPin6002', '\SGrand6002'];
        $eager = self::makeCodeBase(false);
        $lazy = self::makeCodeBase(true);
        self::hydrate($eager, ...$classes);
        self::hydrate($lazy, ...$classes);
        $registry = self::registry($lazy);
        $this->assertGreaterThan(0, $registry->getLazyEntryCount());
        $this->assertSame($registry->getLazyEntryCount(), $lazy->getLazyEntryCount());
        $this->assertInstanceOf(InheritedMethodLink::class, self::classMap($lazy, '\SChild6002')->entry('a'));
        // Constructors and private methods are always created eagerly, as are the methods of project files
        $this->assertInstanceOf(Method::class, self::classMap($lazy, '\SChild6002')->entry('__construct'));
        $this->assertInstanceOf(InheritedMethodLink::class, self::classMap($lazy, '\SChild6002')->entry('priv'));

        $user_classes = ['\VMid6002', '\SChild6002', '\SOther6002', '\SPin6002', '\SGrand6002'];
        foreach ($user_classes as $name) {
            // A lazy entry has the position of the copy (whose creation is not visible through the entry API)
            $this->assertSame(self::classMap($eager, $name)->getMethodNames(), self::classMap($lazy, $name)->getMethodNames(), $name);
        }
        // Creating all copies gives the eager method set, in the eager order
        $this->assertSame(self::fqsens($eager->getMethodSet()), self::fqsens($lazy->getMethodSet()));
        $this->assertSame(0, $lazy->getLazyEntryCount());
        foreach ($user_classes as $name) {
            $eager_map = self::classMap($eager, $name)->getMethodMap();
            $lazy_map = self::classMap($lazy, $name)->getMethodMap();
            $this->assertSame(\array_keys($eager_map), \array_keys($lazy_map), $name);
            foreach ($eager_map as $key => $eager_method) {
                $this->assertSame(self::describe($eager_method), self::describe($lazy_map[$key]), "$name::$key");
                $this->assertSame($eager_method->getEagerOrdinal(), $lazy_map[$key]->getEagerOrdinal(), "rank of $name::$key");
            }
        }
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testChainsResolveThroughTheSlotHistory(): void
    {
        $code_base = self::makeCodeBase(true);
        self::hydrate($code_base, '\SChild6002');
        $mid_map = self::classMap($code_base, '\VMid6002');
        $child_map = self::classMap($code_base, '\SChild6002');
        $child_link = $child_map->entry('a');
        $this->assertInstanceOf(InheritedMethodLink::class, $child_link);
        // The parent's entry is itself lazy: the child's entry resolves through it.
        $this->assertInstanceOf(InheritedMethodLink::class, $mid_map->entry('a'));

        // Creating the parent's copy after the child's import keeps the value it had at the import in the history
        $mid_a = self::method($code_base, '\VMid6002::a');
        $this->assertSame($mid_a, $mid_map->entry('a'));
        $this->assertInstanceOf(InheritedMethodLink::class, $mid_map->entryAt('a', $child_link->seq));
        $this->assertSame($mid_a, $mid_map->entryAt('a', \PHP_INT_MAX));
        $this->assertSame(1, $mid_map->getSlotHistoryRecordCount());

        $child_a = self::method($code_base, '\SChild6002::a');
        $this->assertSame('\VRoot6002::a', (string)$child_a->getDefiningFQSEN());
        $this->assertSame('\SChild6002::a', (string)$child_a->getFQSEN());
        // The record is needed while the child has lazy entries resolving through it
        $this->assertSame(1, $mid_map->getSlotHistoryRecordCount());
        self::registry($code_base)->materializeAllOf($child_map, 'test');
        $this->assertSame(0, $mid_map->getSlotHistoryRecordCount());
        $counts = self::registry($code_base)->getCounts();
        $this->assertSame(1, $counts['lazy_history_records']);
        $this->assertSame(1, $counts['lazy_history_pruned']);
    }

    /**
     * A chain that resolves through the slot history after records were pruned (because the links that needed them
     * have no lazy slot left) gives the same copies as without the pruning, and as eager imports.
     *
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testChainsResolveTheSameAfterHistoryPruning(): void
    {
        $results = [];
        foreach (['unpruned' => false, 'pruned' => true] as $label => $prune) {
            $code_base = self::makeCodeBase(true);
            $registry = self::registry($code_base);
            // The import of SChild6002 (seq s1) resolves through the lazy entries of VMid6002
            self::hydrate($code_base, '\SChild6002');
            $mid_map = self::classMap($code_base, '\VMid6002');
            $child_map = self::classMap($code_base, '\SChild6002');
            // A record of the slot a of VMid6002 (> s1)
            self::method($code_base, '\VMid6002::a');
            // The import of SLate6002 (seq s2, after that record): its a resolves through the copy, its b through the link
            self::hydrate($code_base, '\SLate6002');
            $late_map = self::classMap($code_base, '\SLate6002');
            $late_link = $late_map->entry('b');
            $this->assertInstanceOf(InheritedMethodLink::class, $late_link);
            $this->assertInstanceOf(InheritedMethodLink::class, $late_map->entry('a'));
            // A record of the slot b of VMid6002 (> s2)
            self::method($code_base, '\VMid6002::b');
            $this->assertSame(2, $mid_map->getSlotHistoryRecordCount(), $label);
            if ($prune) {
                // SChild6002 no longer needs the history: the record of a, which only imports before it read, is dropped
                $registry->materializeAllOf($child_map, 'test');
                $this->assertSame(1, $mid_map->getSlotHistoryRecordCount());
                $this->assertGreaterThan(0, $registry->getCounts()['lazy_history_pruned'] ?? 0);
                $this->assertSame(self::method($code_base, '\VMid6002::a'), $mid_map->entryAt('a', $late_link->seq));
                $this->assertInstanceOf(InheritedMethodLink::class, $mid_map->entryAt('b', $late_link->seq));
            } else {
                $this->assertInstanceOf(InheritedMethodLink::class, $child_map->entry('a'));
            }
            $registry->materializeAllOf($late_map, 'test');
            $results[$label] = \array_map(self::describe(...), $late_map->getMethodMap());
        }
        $this->assertSame($results['unpruned'], $results['pruned']);
        $eager = self::makeCodeBase(false);
        self::hydrate($eager, '\SChild6002', '\SLate6002');
        $this->assertSame(\array_map(self::describe(...), self::classMap($eager, '\SLate6002')->getMethodMap()), $results['pruned']);
    }

    /**
     * Verify mode reports a late or nested declaration analysis that changes a suppression count of an element declared
     * in an analyzed file (and not one of an element declared in a file excluded from analysis).
     *
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testSideEffectCheckReportsSuppressionCounts(): void
    {
        $code_base = self::makeCodeBase(true);
        $registry = self::registry($code_base);
        $registry->verify = true;
        self::hydrate($code_base, '\SChild6002');
        $copy = self::method($code_base, '\SChild6002::a');
        $state = $registry->beginSideEffectCheck();
        // An element declared in an excluded file
        self::getClass($code_base, '\VRoot6002')->incrementSuppressIssueCount('PhanUndeclaredMethod');
        $registry->endSideEffectCheck($copy, $state, 'test pipeline');
        $this->assertSame(0, $registry->getViolationCount());

        // (This prints the violation)
        $state = $registry->beginSideEffectCheck();
        self::getClass($code_base, '\SSuppress6002')->incrementSuppressIssueCount('PhanUndeclaredMethod');
        $registry->endSideEffectCheck($copy, $state, 'test pipeline');
        $this->assertSame(1, $registry->getViolationCount());
        $this->assertNull(TypedElement::$suppression_count_recorder);
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testChangedRootCapturesANewTemplate(): void
    {
        $code_base = self::makeCodeBase(true);
        self::hydrate($code_base, '\SOther6002');
        $root = self::method($code_base, '\VRoot6002::a');
        $counts_before = self::registry($code_base)->getCounts();
        // A change of the root after the first import (e.g. by a plugin in the class phase) ...
        $root->setUnionType(UnionType::fromFullyQualifiedPHPDocString('int|string'));
        self::hydrate($code_base, '\SChild6002');
        $counts = self::registry($code_base)->getCounts();
        $this->assertSame(1, $counts['lazy_template_recaptures'] ?? 0);
        // (one recaptured template of the root, and one of VMid6002::own, imported by SChild6002)
        $this->assertSame(($counts_before['lazy_templates'] ?? 0) + 2, $counts['lazy_templates']);
        // ... does not make the later import eager
        $this->assertInstanceOf(InheritedMethodLink::class, self::classMap($code_base, '\VMid6002')->entry('a'));
        // Each copy is created from the state the root had at its import
        $this->assertSame('int', (string)self::method($code_base, '\SOther6002::a')->getUnionType());
        $this->assertSame('int|string', (string)self::method($code_base, '\SChild6002::a')->getUnionType());
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testConflictsCreateTheParentCopyFirst(): void
    {
        $code_base = self::makeCodeBase(true);
        // SPin6002 gets a() from its interface first: its parent's lazy entry is created ("pinned") before it is cloned.
        self::hydrate($code_base, '\SPin6002');
        $this->assertInstanceOf(Method::class, self::classMap($code_base, '\VMid6002')->entry('a'));
        $this->assertInstanceOf(InheritedMethodLink::class, self::classMap($code_base, '\VMid6002')->entry('b'));
        $counts = self::registry($code_base)->getCounts();
        $this->assertSame(1, $counts['lazy_materialized_class_pin']);
        $this->assertSame(1, $counts['lazy_ineligible_multi_provider']);

        // Adding a method over a lazy entry looks the entry up first (Clazz::addMethod()), which creates the copy
        self::hydrate($code_base, '\SChild6002');
        $child = self::getClass($code_base, '\SChild6002');
        $child->addMethod($code_base, self::method($code_base, '\VRoot6002::b'), None::instance());
        $copy = self::classMap($code_base, '\SChild6002')->entry('b');
        $this->assertInstanceOf(Method::class, $copy);
        $this->assertSame('\VRoot6002::b', (string)$copy->getDefiningFQSEN());

        // A method replacing a lazy entry directly (without looking it up first) discards the entry
        $registry = self::registry($code_base);
        $count = $registry->getLazyEntryCount();
        $replacement = clone(self::method($code_base, '\VRoot6002::untyped'));
        $replacement->setFQSEN(FullyQualifiedMethodName::fromFullyQualifiedString('\SChild6002::untyped'));
        $code_base->addMethod($replacement);
        $this->assertSame($count - 1, $registry->getLazyEntryCount());
        $this->assertSame($replacement, self::classMap($code_base, '\SChild6002')->entry('untyped'));
        $registry->materializeAll('test');
        $this->assertSame(0, $registry->getLazyEntryCount());
        $this->assertSame($replacement, self::method($code_base, '\SChild6002::untyped'));
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testPublicMethodSetIsInEagerOrder(): void
    {
        $classes = ['\SChild6002', '\SOther6002'];
        $eager = self::makeCodeBase(false);
        $lazy = self::makeCodeBase(true);
        self::hydrate($eager, ...$classes);
        self::hydrate($lazy, ...$classes);
        // Create some copies out of order: they are added at the end of the method set ...
        $other_b = self::method($lazy, '\SOther6002::b');
        $child_a = self::method($lazy, '\SChild6002::a');
        $this->assertGreaterThan($child_a->getEagerOrdinal(), $other_b->getEagerOrdinal());
        $internal_order = self::fqsens($lazy->getMethodSetWithoutLazyEntries());
        $this->assertSame(['\SOther6002::b', '\SChild6002::a'], \array_slice($internal_order, -2));
        // ... and the public set (which creates the remaining copies) is in eager order
        $this->assertSame(self::fqsens($eager->getMethodSet()), self::fqsens($lazy->getMethodSet()));
        $this->assertSame(self::fqsens($eager->getMethodSet()), self::fqsens($lazy->getMethodSetWithoutLazyEntries()));
        $counts = self::registry($lazy)->getCounts();
        $this->assertSame(1, $counts['lazy_materialize_all_method_set']);
        $this->assertSame(2, $counts['lazy_materialized_class_lookup']);
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testLateCopiesGetThePluginClosuresInOrder(): void
    {
        $code_base = self::makeCodeBase(true);
        self::hydrate($code_base, '\SChild6002', '\SOther6002');
        $registry = self::registry($code_base);
        // After the method loop: copies created now get the declaration analysis and the recorded plugin closures
        $registry->creation_open = false;
        $registry->registration_open = false;
        $make_closure = static function (string $type): \Closure {
            return static function () use ($type): UnionType {
                return UnionType::fromFullyQualifiedPHPDocString($type);
            };
        };
        $root_fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\VRoot6002::a');
        // SOther6002::a has its own return type override, which takes precedence over that of the method it inherits
        $registry->beginLoadMethodPlugins(['\SOther6002::a' => true]);
        $registry->recordOp($root_fqsen, 'return_type', $make_closure('float'), null);
        $registry->recordOp($root_fqsen, 'return_type', $make_closure('string'), null);
        $registry->recordOp($root_fqsen, 'call', static function (): void {
        }, null);

        $context = new Context();
        $child_a = self::method($code_base, '\SChild6002::a');
        $this->assertTrue($child_a->hasDependentReturnType());
        // The closures are applied in the order they were attached: the last return type override wins
        $this->assertSame('string', (string)$child_a->getDependentReturnType($code_base, $context, []));
        $this->assertTrue($child_a->hasFunctionCallAnalyzer());
        // The declaration analysis ran
        $this->assertTrue($child_a->isScopeInitialized());

        $other_a = self::method($code_base, '\SOther6002::a');
        $this->assertFalse($other_a->hasDependentReturnType());
        $this->assertTrue($other_a->hasFunctionCallAnalyzer());

        $child_b = self::method($code_base, '\SChild6002::b');
        $this->assertFalse($child_b->hasDependentReturnType());
        $this->assertFalse($child_b->hasFunctionCallAnalyzer());
        $counts = $registry->getCounts();
        $this->assertSame(4, $counts['lazy_ops_replayed']);
        $this->assertSame(3, $counts['lazy_materialized_late_lookup']);
        $this->assertSame(3, $counts['lazy_late_pipelines']);
    }

    /**
     * @throws \ReflectionException never
     */
    public function testDisabledInModesWhereItWasNotVerified(): void
    {
        $this->assertSame([false, 'not_requested'], LazyInheritedMethods::decide(null, null));
        \putenv('PHAN_LAZY_INHERITED_METHODS=1');
        $this->assertSame([true, null], LazyInheritedMethods::decide(null, null));
        \putenv('PHAN_LAZY_INHERITED_METHODS=0');
        Config::setValue('lazy_inherited_methods', true);
        $this->assertSame([false, 'not_requested'], LazyInheritedMethods::decide(null, null));
        \putenv('PHAN_LAZY_INHERITED_METHODS');
        $this->assertSame([true, null], LazyInheritedMethods::decide(null, null));
        \putenv('PHAN_DISABLE_LAZY_INHERIT=1');
        $this->assertSame([true, 'env_disabled'], LazyInheritedMethods::decide(null, null));
        \putenv('PHAN_DISABLE_LAZY_INHERIT');
        Config::setValue('lazy_inherited_methods', false);
        // PHAN_LAZY_INHERIT_VERIFY=1 enables it
        \putenv('PHAN_LAZY_INHERIT_VERIFY=1');
        $this->assertSame([true, null], LazyInheritedMethods::decide(null, null));

        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $this->assertSame([true, 'request'], LazyInheritedMethods::decide($request, null));
        $this->assertSame([true, 'path_filter'], LazyInheritedMethods::decide(null, ['src/a.php' => 0]));
        $this->assertSame([true, 'incremental'], LazyInheritedMethods::decide(null, null, true));
        foreach ([
            'dead_code_detection' => 'track_references',
            'force_tracking_references' => 'track_references',
            '__analyze_twice' => 'analyze_twice',
            '__analyze_until_convergence' => 'analyze_until_convergence',
            'dump_matching_functions' => 'dump_matching_functions',
        ] as $option => $reason) {
            Config::setValue($option, true);
            $this->assertSame([true, $reason], LazyInheritedMethods::decide(null, null), $option);
            Config::setValue($option, false);
        }

        // Every AnalyzeMethodCapability plugin must implement AnalyzeMethodSkipsInheritedCopiesCapability
        $plugin_set = ConfigPluginSet::instance();
        $property = new ReflectionProperty(ConfigPluginSet::class, 'analyze_method_plugin_set');
        $saved = $property->getValue($plugin_set);
        try {
            $marked = new MarkedMethodPlugin6002();
            $property->setValue($plugin_set, [$marked]);
            $this->assertSame([true, null], LazyInheritedMethods::decide(null, null));
            $property->setValue($plugin_set, [$marked, new UnmarkedMethodPlugin6002()]);
            $this->assertSame([true, 'unmarked_plugin:' . UnmarkedMethodPlugin6002::class], LazyInheritedMethods::decide(null, null));
        } finally {
            $property->setValue($plugin_set, $saved);
        }
    }

    /**
     * A copy that comes before the method being processed in eager order, created while that method's declaration
     * analysis runs, is analyzed in the middle of it, as if it had been processed before: the outer method keeps
     * its CloneReplay record although the nested analysis checks issues.
     *
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testNestedPipelineKeepsOuterRecord(): void
    {
        // The analysis of the copy SChild6002::helper checks issues (Issue::$emit_attempt_count), which would make
        // CloneReplay discard the record of a method being recorded at the same time.
        $eager = self::makeCodeBase(false);
        self::hydrate($eager, '\SChild6002');
        $attempts = Issue::$emit_attempt_count;
        MethodDeclarationAnalyzer::forLateUse($eager)->analyze(self::method($eager, '\SChild6002::helper'));
        $this->assertGreaterThan($attempts, Issue::$emit_attempt_count);

        $code_base = self::makeCodeBase(true);
        // SChild6002::helper is lazy; SKid6002::run (a copy of a method of a project file) is created after it,
        // and is the source of SKidChild6002::run.
        self::hydrate($code_base, '\SChild6002', '\SKidChild6002');
        $registry = self::registry($code_base);
        $registry->closeCreationWindow();
        $outer = self::method($code_base, '\SKid6002::run');
        $this->assertInstanceOf(InheritedMethodLink::class, self::classMap($code_base, '\SChild6002')->entry('helper'));

        // The method loop of Analysis::analyzeFunctions(), processing SKid6002::run
        $analyzer = MethodDeclarationAnalyzer::forLateUse($code_base);
        EmitOnlyChecks::resetLookedUpTypes();
        $this->assertTrue(CloneReplay::beginAnalyzingMethods($code_base, true, false, false));
        $registry->beginMethodLoop($analyzer, true);
        try {
            $this->assertFalse($registry->beginLoopMethod($outer));
            EmitOnlyChecks::beginAnalyzingMethod($code_base, $outer);
            $this->assertNull(CloneReplay::beginAnalyzingMethod($code_base, $outer));
            $this->assertTrue(CloneReplay::$has_pending_method, 'the outer method is being recorded');
            // A lookup in the middle of the outer method's declaration analysis creates the copy and analyzes it
            $attempts = Issue::$emit_attempt_count;
            $helper = self::method($code_base, '\SChild6002::helper');
            $this->assertLessThan($outer->getEagerOrdinal(), $helper->getEagerOrdinal());
            $this->assertTrue($helper->isScopeInitialized());
            $this->assertSame(1, $registry->getCounts()['lazy_nested_processed'] ?? 0);
            $this->assertSame($attempts, Issue::$emit_attempt_count, 'the nested analysis is excluded from the outer method\'s checks');
            $this->assertTrue(CloneReplay::$has_pending_method, 'the outer method is still being recorded');
            $analyzer->analyze($outer);
            EmitOnlyChecks::endAnalyzingMethod();
            $this->assertFalse(CloneReplay::$has_pending_method);
            // The loop skips the copy that was processed
            $this->assertTrue($registry->beginLoopMethod($helper));

            // The outer method was recorded: its copy replays its declaration analysis
            $copy = self::method($code_base, '\SKidChild6002::run');
            $this->assertFalse($registry->beginLoopMethod($copy));
            EmitOnlyChecks::beginAnalyzingMethod($code_base, $copy);
            $this->assertNotNull(CloneReplay::beginAnalyzingMethod($code_base, $copy));
            EmitOnlyChecks::endAnalyzingMethod();
        } finally {
            CloneReplay::endAnalyzingMethods($code_base);
            $registry->endMethodLoop();
        }
    }

    /**
     * The class and method phases give the same issues and CloneReplay counts with lazy entries as without.
     */
    public function testMethodPhaseMatchesEagerInheritance(): void
    {
        [$eager_issues, $eager_digest, , $eager_extended_digest] = self::analyze(false);
        [$lazy_issues, $lazy_digest, $lazy_counts, $lazy_extended_digest] = self::analyze(true);
        $this->assertSame($eager_issues, $lazy_issues);
        // The copies created after the method phase (here: by CodeBase::getMethodSet()) have the state of eager copies
        // (CloneReplay may record and replay different methods, which does not change the state)
        $this->assertNotSame('', $eager_digest);
        $this->assertSame($eager_digest, $lazy_digest);
        $this->assertStringContainsString("\tclone_source_root=\\VRoot6002::a\t", $lazy_extended_digest);
        $this->assertStringContainsString("\nclassmap \\SChild6002: ", $lazy_extended_digest);
        $this->assertSame($eager_extended_digest, $lazy_extended_digest);
        $this->assertGreaterThan(0, $lazy_counts['lazy_links'] ?? 0);
        $this->assertGreaterThan(0, $lazy_counts['lazy_pending_drained'] ?? 0);
        $this->assertGreaterThan(0, $lazy_counts['lazy_late_pipelines'] ?? 0);
        $this->assertSame(0, $lazy_counts['lazy_verify_violations'] ?? -1);
    }

    /**
     * Cloning a code base after the method phase (as daemon mode does for restore points) adds the remaining lazy
     * copies and those created after the method loop to the method set: the clone, which has no registry,
     * and the original both have the eager method set, in eager order.
     *
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the names are literals
     */
    public function testCloneAfterMethodPhaseHasEveryMethod(): void
    {
        $run_method_phase = static function (bool $lazy): CodeBase {
            $code_base = self::makeCodeBase($lazy);
            Analysis::analyzeClasses($code_base);
            $code_base->getLazyInherited()?->closeCreationWindow();
            Analysis::analyzeFunctions($code_base);
            return $code_base;
        };
        $expected = self::fqsens($run_method_phase(false)->getMethodSet());
        $code_base = $run_method_phase(true);
        $registry = self::registry($code_base);
        // A copy created after the method loop is only listed in the registry until the public method set is used
        $late = self::method($code_base, '\SOther6002::b');
        $this->assertSame(1, $registry->getCounts()['lazy_materialized_late_lookup'] ?? 0);
        $this->assertNotContains('\SOther6002::b', self::fqsens($code_base->getMethodSetWithoutLazyEntries()));
        $this->assertGreaterThan(0, $registry->getLazyEntryCount());

        $clone = clone($code_base);
        $this->assertNull($clone->getLazyInherited());
        $this->assertSame($expected, self::fqsens($clone->getMethodSet()));
        $this->assertSame(0, $registry->getLazyEntryCount());
        // The original keeps its registry and has the same, complete method set
        $this->assertSame($registry, $code_base->getLazyInherited());
        $this->assertSame($expected, self::fqsens($code_base->getMethodSetWithoutLazyEntries()));
        $this->assertSame($expected, self::fqsens($code_base->getMethodSet()));
        $this->assertSame($late, self::method($code_base, '\SOther6002::b'));
    }

    /**
     * Run the class and method phases on the fixtures like Phan does before the analysis phase.
     *
     * @return array{0:list<string>,1:string,2:array<string,int|float>,3:string} the issues, the method state digest
     * (EmitOnlyChecks::maybeDumpStateDigest(), after creating all lazy copies), the counters of LazyInheritedMethods
     * and the extended digest (PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED=1)
     */
    private static function analyze(bool $lazy): array
    {
        PhaseTimer::resetForTests();
        PhaseTimer::$enabled = true;
        $digest_path = (string)\tempnam(\sys_get_temp_dir(), 'phan_lazy_inherit');
        \putenv("PHAN_DUMP_METHOD_STATE_DIGEST=$digest_path");
        $extended_digest = '';
        // The extended digest is written before CodeBase::getMethodSet() adds the copies created late to the method set
        \putenv('PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED=1');
        $code_base = self::makeCodeBase($lazy);
        $collector = new BufferingCollector();
        Phan::setIssueCollector($collector);
        $registry = $code_base->getLazyInherited();
        if ($registry) {
            $registry->verify = true;
        }
        try {
            Analysis::analyzeClasses($code_base);
            $registry?->closeCreationWindow();
            Analysis::analyzeFunctions($code_base);
            $registry?->endLoadMethodPlugins();
            EmitOnlyChecks::maybeDumpStateDigest($code_base);
            $extended_digest = (string)\file_get_contents($digest_path);
            $code_base->getMethodSet();
            \putenv('PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED');
            EmitOnlyChecks::maybeDumpStateDigest($code_base);
        } finally {
            PhaseTimer::resetForTests();
            \putenv('PHAN_DUMP_METHOD_STATE_DIGEST');
            \putenv('PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED');
        }
        $digest = (string)\file_get_contents($digest_path);
        \unlink($digest_path);
        $issues = [];
        foreach ($collector->getCollectedIssues() as $issue) {
            $issues[] = $issue->getFile() . ':' . $issue->getLine() . ' ' . $issue->getIssue()->getType() . ' ' . $issue->getMessage();
        }
        return [$issues, $digest, $registry ? $registry->getCounts() : [], $extended_digest];
    }
}

/**
 * A plugin analyzing methods that does not depend on being called for every inherited copy
 */
final class MarkedMethodPlugin6002 extends PluginV3 implements AnalyzeMethodCapability, AnalyzeMethodSkipsInheritedCopiesCapability
{
    /**
     * @unused-param $code_base
     * @unused-param $method
     */
    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
    }
}

/**
 * A plugin analyzing methods that may depend on being called for every inherited copy
 */
final class UnmarkedMethodPlugin6002 extends PluginV3 implements AnalyzeMethodCapability
{
    /**
     * @unused-param $code_base
     * @unused-param $method
     */
    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
    }
}
