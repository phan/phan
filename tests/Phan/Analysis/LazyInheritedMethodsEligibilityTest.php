<?php

declare(strict_types=1);

namespace Phan\Tests\Analysis;

use Phan\Analysis;
use Phan\Analysis\LazyInheritedMethodsEligibility;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Library\None;
use Phan\Library\PhaseTimer;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Tests\TestBase;

/**
 * Unit tests of the checks deciding whether an inherited method copy could be created lazily
 * (LazyInheritedMethodsEligibility), including the order in which the reasons are reported.
 * @phan-file-suppress PhanAccessMethodInternal
 */
final class LazyInheritedMethodsEligibilityTest extends TestBase
{
    private const VENDOR_CODE = <<<'PHP'
<?php
class VBase5951 {
    public function __construct() {}
    public function typed(): int { return 1; }
    public function untyped() {}
    public function shared(): int { return 1; }
    public function untypedGenerator() { yield 1; }
}
/** @template T */
class VGeneric5951 {
    public function g(): int { return 1; }
}
class VTemplateMethod5951 {
    /**
     * @template T
     * @param T $x
     */
    public function tm($x) {}
}
PHP;

    private const SRC_CODE = <<<'PHP'
<?php
interface SI5951 { public function shared(): int; }
class SChild5951 extends VBase5951 implements SI5951 {}
class SGenericChild5951 extends VGeneric5951 {}
class SProjectBase5951 {
    public function __construct() {}
    public function p(): int { return 1; }
}
class SProjectChild5951 extends SProjectBase5951 {}
class STemplateMethodChild5951 extends VTemplateMethod5951 {}
PHP;

    public function setUp(): void
    {
        parent::setUp();
        Config::setValue('exclude_analysis_directory_list', ['vendor/']);
        LazyInheritedMethodsEligibility::setCreationWindowOpen(true);
        LazyInheritedMethodsEligibility::resetCounts();
    }

    public function tearDown(): void
    {
        LazyInheritedMethodsEligibility::setCreationWindowOpen(false);
        LazyInheritedMethodsEligibility::resetCounts();
        PhaseTimer::resetForTests();
        Config::reset();
        parent::tearDown();
    }

    /**
     * Parses the fixtures, without hydrating any class.
     */
    private static function makeCodeBase(): CodeBase
    {
        $code_base = new CodeBase([], [], [], [], []);
        Phan::setIssueCollector(new BufferingCollector());
        foreach (['vendor/5951.php' => self::VENDOR_CODE, 'src/5951.php' => self::SRC_CODE] as $file => $code) {
            FileCache::addEntry($file, $code);
            FileCache::addEntry(Config::projectPath($file), $code);
            Analysis::parseNodeInContext(
                $code_base,
                (new Context())->withFile($file),
                \ast\parse_code($code, Config::AST_VERSION)
            );
        }
        return $code_base;
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
    private static function getEntry(CodeBase $code_base, string $class_name, string $lowercase_name): Method
    {
        $method = $code_base->getMethodEntryByClassAndLowercaseName(
            FullyQualifiedClassName::fromFullyQualifiedString($class_name),
            $lowercase_name
        );
        if (!$method) {
            throw new \AssertionError("no method $class_name::$lowercase_name");
        }
        return $method;
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    private static function getReasonForEntry(CodeBase $code_base, string $class_name, string $parent_name, string $lowercase_name): ?string
    {
        return LazyInheritedMethodsEligibility::getReasonForEntry(
            $code_base,
            self::getClass($code_base, $class_name),
            self::getClass($code_base, $parent_name),
            $lowercase_name,
            self::getEntry($code_base, $parent_name, $lowercase_name)
        );
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    public function testReasonForImport(): void
    {
        $code_base = self::makeCodeBase();
        $child = self::getClass($code_base, '\SChild5951');
        $base = self::getClass($code_base, '\VBase5951');
        $this->assertNull(LazyInheritedMethodsEligibility::getReasonForImport($code_base, $child, $base));
        $this->assertSame(
            'template',
            LazyInheritedMethodsEligibility::getReasonForImport($code_base, self::getClass($code_base, '\SGenericChild5951'), self::getClass($code_base, '\VGeneric5951'))
        );
        // Only classes inheriting from classes
        $this->assertSame('not_class', LazyInheritedMethodsEligibility::getReasonForImport($code_base, self::getClass($code_base, '\SI5951'), $base));
        $this->assertSame('not_class', LazyInheritedMethodsEligibility::getReasonForImport($code_base, $child, self::getClass($code_base, '\SI5951')));
        // The window is checked first.
        LazyInheritedMethodsEligibility::setCreationWindowOpen(false);
        $this->assertSame('window', LazyInheritedMethodsEligibility::getReasonForImport($code_base, self::getClass($code_base, '\SI5951'), $base));
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    public function testReasonForEntry(): void
    {
        $code_base = self::makeCodeBase();
        $child = self::getClass($code_base, '\SChild5951');
        // The interface is imported before the parent class (Clazz::hydrateOnce()).
        $child->importAncestorClass($code_base, self::getClass($code_base, '\SI5951'), None::instance());

        $this->assertSame('construct', self::getReasonForEntry($code_base, '\SChild5951', '\VBase5951', '__construct'));
        $this->assertSame('multi_provider', self::getReasonForEntry($code_base, '\SChild5951', '\VBase5951', 'shared'));
        $this->assertNull(self::getReasonForEntry($code_base, '\SChild5951', '\VBase5951', 'typed'));
        // An untyped method of an excluded file: the copy uses its return type (the relaxed rule).
        $this->assertNull(self::getReasonForEntry($code_base, '\SChild5951', '\VBase5951', 'untyped'));
        $this->assertSame(self::getEntry($code_base, '\VBase5951', 'untyped'), self::getEntry($code_base, '\VBase5951', 'untyped')->getReturnTypeLinkOfClone());
        $this->assertNull(self::getEntry($code_base, '\VBase5951', 'typed')->getReturnTypeLinkOfClone());
        $this->assertSame('linked_yield', self::getReasonForEntry($code_base, '\SChild5951', '\VBase5951', 'untypedgenerator'));
        $this->assertSame('linked_template', self::getReasonForEntry($code_base, '\STemplateMethodChild5951', '\VTemplateMethod5951', 'tm'));
        // Methods of project files (the constructor is checked before the file)
        $this->assertSame('project_root', self::getReasonForEntry($code_base, '\SProjectChild5951', '\SProjectBase5951', 'p'));
        $this->assertSame('construct', self::getReasonForEntry($code_base, '\SProjectChild5951', '\SProjectBase5951', '__construct'));
        // Alternates of a method (a name with an alternate id)
        $this->assertSame('alternate', LazyInheritedMethodsEligibility::getReasonForEntry(
            $code_base,
            $child,
            self::getClass($code_base, '\VBase5951'),
            'typed,1',
            self::getEntry($code_base, '\VBase5951', 'typed')
        ));
        // The checks have no side effects: no class was hydrated, and the parent class map is unchanged.
        $this->assertFalse(self::getClass($code_base, '\VBase5951')->isHydrating());
        $this->assertNull($code_base->getMethodEntryByClassAndLowercaseName(FullyQualifiedClassName::fromFullyQualifiedString('\SChild5951'), 'typed'));
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    public function testCountsOfParentImport(): void
    {
        $code_base = self::makeCodeBase();
        PhaseTimer::$enabled = true;
        LazyInheritedMethodsEligibility::setCountingEnabledForTests(true);
        foreach (['\SChild5951', '\SProjectChild5951', '\STemplateMethodChild5951', '\SGenericChild5951'] as $name) {
            self::getClass($code_base, $name)->importAncestorClasses($code_base);
        }
        $counts = LazyInheritedMethodsEligibility::getCounts();
        \ksort($counts);
        $this->assertSame([
            'lazy_would_be_copies_parent' => 9,
            'lazy_would_be_eligible' => 2,
            'lazy_would_be_eligible_relaxed_link' => 1,
            'lazy_would_be_ineligible_construct' => 2,
            'lazy_would_be_ineligible_linked_template' => 1,
            'lazy_would_be_ineligible_linked_yield' => 1,
            'lazy_would_be_ineligible_multi_provider' => 1,
            'lazy_would_be_ineligible_project_root' => 1,
            'lazy_would_be_ineligible_template' => 1,
            'lazy_would_be_templates' => 2,
        ], $counts);
        // Interface imports are not counted, and the copies are created as before.
        $this->assertNotNull($code_base->getMethodEntryByClassAndLowercaseName(FullyQualifiedClassName::fromFullyQualifiedString('\SChild5951'), 'untyped'));
    }

    /**
     * @throws \Phan\Exception\FQSENException never, the names are literals
     */
    public function testCountsOfTemplateRecaptures(): void
    {
        $code_base = self::makeCodeBase();
        PhaseTimer::$enabled = true;
        $class = self::getClass($code_base, '\SChild5951');
        $parent = self::getClass($code_base, '\VBase5951');
        $root = self::getEntry($code_base, '\VBase5951', 'typed');
        $count = static function () use ($code_base, $class, $parent, $root): void {
            LazyInheritedMethodsEligibility::countParentImportEntry($code_base, $class, $parent, null, 'typed', $root);
        };
        $count();
        $count();  // @phan-suppress-current-line PhanPluginDuplicateAdjacentStatement unchanged: not a recapture
        $counts = LazyInheritedMethodsEligibility::getCounts();
        $this->assertSame(2, $counts['lazy_would_be_eligible']);
        $this->assertSame(1, $counts['lazy_would_be_templates']);
        $this->assertArrayNotHasKey('lazy_would_be_template_recaptures', $counts);

        // A change of the root's owned state since the last would-be lazy import is a recapture.
        $root->setHasReturn(!$root->hasReturn());
        $count();
        $count();  // @phan-suppress-current-line PhanPluginDuplicateAdjacentStatement unchanged since the last one
        // So is a change of a variable in its scope.
        $variable = new Variable(new Context(), 'v', UnionType::empty(), 0);
        $root->getInternalScope()->addVariable($variable);
        $count();
        $variable->setUnionType(UnionType::empty());
        $count();
        $counts = LazyInheritedMethodsEligibility::getCounts();
        $this->assertSame(1, $counts['lazy_would_be_templates']);
        $this->assertSame(3, $counts['lazy_would_be_template_recaptures']);
        $this->assertSame(1, $counts['lazy_would_be_roots_mutated']);
    }
}
