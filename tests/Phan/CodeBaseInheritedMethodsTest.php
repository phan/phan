<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Library\FileCache;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;

/**
 * Tests that CodeBase::getMethodsInheritedFrom() (used to attach plugin closures to the inherited copies of a method)
 * finds the same methods as grouping the whole method set by defining FQSEN did.
 */
final class CodeBaseInheritedMethodsTest extends TestBase
{
    private static function makeCodeBase(): CodeBase
    {
        $code = <<<'PHP'
<?php
interface I5950 { public function fromInterface(); }
trait T5950 { public function fromTrait() {} public function aliased() {} }
abstract class Base5950 implements I5950 {
    use T5950;
    public function inherited() {}
    public function overridden() {}
    public function fromInterface() {}
    public static function staticInherited() {}
}
class Child5950 extends Base5950 { public function overridden() {} }
class GrandChild5950 extends Child5950 {
    use T5950 { aliased as renamed; }
    public function own() {}
}
/** @mixin Base5950 */
class Mixed5950 {}
class Unrelated5950 { public function inherited() {} }
class UsesTrait5950 { use T5950; }
class Alias5950 { use T5950 { aliased as viaParent; } }
class AliasChild5950 extends Alias5950 {}
/** @template T */
class TBase5950 {
    /** @param T $x */
    public function run($x) {}
    public function plain(int $i) {}
}
/** @extends TBase5950<callable> */
class TChild5950 extends TBase5950 {}
class TPlain5950 extends TBase5950 {}
class_alias(Base5950::class, 'Aliased5950');
class ViaAlias5950 extends Aliased5950 {}
/** @template T */
trait GT5950 {
    /** @param T $x */
    public function gt($x) {}
}
/** @use GT5950<callable> */
class GUser5950 {
    use GT5950;
}
class GChild5950 extends GUser5950 {}
PHP;
        $code_base = new CodeBase([], [], [], [], []);
        Phan::setIssueCollector(new BufferingCollector());
        Config::setValue('enable_class_alias_support', true);
        // Issues emitted while importing the ancestors (e.g. about the template types) look the file up for suppressions.
        FileCache::addEntry('5950.php', $code);
        FileCache::addEntry(Config::projectPath('5950.php'), $code);
        Analysis::parseNodeInContext(
            $code_base,
            (new Context())->withFile('5950.php'),
            \ast\parse_code($code, Config::AST_VERSION)
        );
        $code_base->resolveClassAliases();
        // Create the inherited copies of methods, as the class analysis phase does.
        foreach ($code_base->getUserDefinedClassMap() as $class) {
            $class->importAncestorClasses($code_base);
        }
        return $code_base;
    }

    /**
     * @param iterable<Method> $methods
     * @return list<string>
     */
    private static function fqsenStrings(iterable $methods): array
    {
        $result = [];
        foreach ($methods as $method) {
            $result[] = $method->getFQSEN()->__toString();
        }
        \sort($result);
        return \array_values(\array_unique($result));
    }

    /**
     * @param array<string,list<\Phan\Language\FQSEN\FullyQualifiedClassName>> $index
     * @return list<string> the FQSENs of the methods inheriting the method $fqsen_string, sorted
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException for an invalid $fqsen_string
     */
    private static function inheritedFrom(CodeBase $code_base, array $index, string $fqsen_string): array
    {
        return self::fqsenStrings($code_base->getMethodsInheritedFrom(
            FullyQualifiedMethodName::fromFullyQualifiedString($fqsen_string),
            $index
        ));
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException for an invalid $fqsen_string
     */
    private static function method(CodeBase $code_base, string $fqsen_string): Method
    {
        return $code_base->getMethodByFQSEN(FullyQualifiedMethodName::fromFullyQualifiedString($fqsen_string));
    }

    public function testMatchesGroupingByDefiningFQSEN(): void
    {
        $code_base = self::makeCodeBase();
        $expected_map = $code_base->getMethodsMapGroupedByDefiningFQSEN();
        $index = $code_base->getDirectChildClassIndex();
        $checked = 0;
        foreach ($code_base->getMethodSet() as $method) {
            $fqsen = $method->getFQSEN();
            $expected = $expected_map->offsetExists($fqsen) ? self::fqsenStrings($expected_map->offsetGet($fqsen)) : [];
            $actual = self::fqsenStrings($code_base->getMethodsInheritedFrom($fqsen, $index));
            $this->assertSame($expected, $actual, "methods defined by $fqsen");
            $checked++;
        }
        $this->assertGreaterThan(10, $checked);
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the FQSENs are literals
     */
    public function testInheritedCopies(): void
    {
        $code_base = self::makeCodeBase();
        $index = $code_base->getDirectChildClassIndex();
        // The method itself and the copies in the subclasses that do not override it; not the unrelated method of the same name.
        // (ViaAlias5950 extends Base5950 through a class alias.)
        $this->assertSame(
            ['\Base5950::inherited', '\Child5950::inherited', '\GrandChild5950::inherited', '\Mixed5950::inherited', '\ViaAlias5950::inherited'],
            self::inheritedFrom($code_base, $index, '\Base5950::inherited')
        );
        // The override in Child5950 is a different method, inherited by GrandChild5950.
        $this->assertSame(['\Base5950::overridden', '\Mixed5950::overridden', '\ViaAlias5950::overridden'], self::inheritedFrom($code_base, $index, '\Base5950::overridden'));
        $this->assertSame(['\Child5950::overridden', '\GrandChild5950::overridden'], self::inheritedFrom($code_base, $index, '\Child5950::overridden'));
        // A trait method, including the copy with another name.
        $this->assertSame(
            [
                '\Alias5950::aliased', '\Alias5950::viaParent', '\AliasChild5950::aliased', '\AliasChild5950::viaParent',
                '\Base5950::aliased', '\Child5950::aliased', '\GrandChild5950::aliased', '\GrandChild5950::renamed',
                '\Mixed5950::aliased', '\T5950::aliased', '\UsesTrait5950::aliased', '\ViaAlias5950::aliased',
            ],
            self::inheritedFrom($code_base, $index, '\T5950::aliased')
        );
        $this->assertSame([], self::inheritedFrom($code_base, $index, '\Unrelated5950::missing'));
        // An alias given in a parent class is inherited under that name by its subclasses (which use no trait themselves).
        $this->assertContains('\AliasChild5950::viaParent', self::inheritedFrom($code_base, $index, '\T5950::aliased'));
    }

    /**
     * @throws \Phan\Exception\FQSENException|\InvalidArgumentException never, the FQSENs are literals
     */
    public function testInheritedCopyWithSameSignature(): void
    {
        $code_base = self::makeCodeBase();
        // A declared method is not a copy.
        $this->assertFalse($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\Base5950::inherited')));
        // A plain inherited copy shares the parameters and comment of the method it was inherited from.
        $this->assertTrue($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\Child5950::inherited')));
        $this->assertTrue($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\GrandChild5950::inherited')));
        // A method of a templated base class without template types in its own signature is copied unchanged.
        $this->assertTrue($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\TChild5950::plain')));
        // A copy whose parameter types were specialized by template substitution has parameters of its own:
        // `TChild5950::run()` accepts a callable while `TBase5950::run()` accepts `T`
        // (and `TPlain5950::run()` accepts mixed, the template type's default without template arguments).
        $this->assertFalse($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\TChild5950::run')));
        $this->assertStringContainsString('callable', (string)self::method($code_base, '\TChild5950::run')->getParameterList()[0]->getUnionType());
        $this->assertFalse($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\TPlain5950::run')));
        // A trait method specialized with `@use GT5950<callable>` stays specialized when adapted into the using class,
        // so the copy a subclass inherits from that class is not an unspecialized copy either.
        $this->assertStringContainsString('callable', (string)self::method($code_base, '\GUser5950::gt')->getParameterList()[0]->getUnionType());
        $this->assertFalse($code_base->isInheritedCopyWithSameSignature(self::method($code_base, '\GChild5950::gt')));
    }
}
