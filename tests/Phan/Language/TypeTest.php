<?php declare(strict_types = 1);
namespace Phan\Tests\Language;

use Phan\Tests\BaseTest;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NativeType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;

/**
 * Unit tests of Type
 */
class TypeTest extends BaseTest
{
    private function makePHPDocType(string $type_string) : Type
    {
        $this->assertTrue(\preg_match('@^' . Type::type_regex_or_this . '$@', $type_string) > 0, "Failed to parse '$type_string'");
        return Type::fromStringInContext($type_string, new Context(), Type::FROM_PHPDOC);
    }

    public function testBracketedTypes()
    {
        $this->assertParsesAsType(ArrayType::instance(false), '(array)');
        $this->assertParsesAsType(ArrayType::instance(false), '((array))');
        $this->assertParsesAsType(ArrayType::instance(false), '((array))');
    }

    public function assertParsesAsType(Type $expected_type, string $type_string)
    {
        $this->assertTrue(\preg_match('@^' . Type::type_regex_or_this . '$@', $type_string) > 0, "Failed to parse '$type_string'");
        $this->assertSameType($expected_type, self::makePHPDocType($type_string));
    }

    public function testBasicTypes()
    {
        $this->assertParsesAsType(ArrayType::instance(false), 'array');
        $this->assertParsesAsType(ArrayType::instance(true), '?array');
        $this->assertParsesAsType(ArrayType::instance(true), '?ARRAY');
        $this->assertParsesAsType(BoolType::instance(false), 'bool');
        $this->assertParsesAsType(CallableType::instance(false), 'callable');
        $this->assertParsesAsType(ClosureType::instance(false), 'Closure');
        $this->assertParsesAsType(FalseType::instance(false), 'false');
        $this->assertParsesAsType(FloatType::instance(false), 'float');
        $this->assertParsesAsType(IntType::instance(false), 'int');
        $this->assertParsesAsType(IterableType::instance(false), 'iterable');
        $this->assertParsesAsType(MixedType::instance(false), 'mixed');
        $this->assertParsesAsType(ObjectType::instance(false), 'object');
        $this->assertParsesAsType(ResourceType::instance(false), 'resource');
        $this->assertParsesAsType(StaticType::instance(false), 'static');
        $this->assertParsesAsType(StringType::instance(false), 'string');
        $this->assertParsesAsType(TrueType::instance(false), 'true');
        $this->assertParsesAsType(VoidType::instance(false), 'void');
    }

    private function assertSameType(Type $expected, Type $actual)
    {
        $message = \sprintf("Expected %s to be %s", (string)$actual, (string)$expected);
        $this->assertEquals($expected, $actual, $message);
        $this->assertSame($expected, $actual, $message);
    }

    public function testUnionTypeOfThis()
    {
        $this->assertParsesAsType(StaticType::instance(false), '$this');
        $this->assertParsesAsType(StaticType::instance(true), '?$this');
    }

    public function testGenericArray()
    {
        $genericArrayType = self::makePHPDocType('int[][]');
        $expectedGenericArrayType = GenericArrayType::fromElementType(
            GenericArrayType::fromElementType(
                IntType::instance(false),
                false
            ),
            false
        );
        $this->assertSameType($expectedGenericArrayType, $genericArrayType);
        $this->assertSame('int[][]', (string)$expectedGenericArrayType);
        $this->assertSameType($expectedGenericArrayType, self::makePHPDocType('(int)[][]'));
        // TODO: Parse (int[])[]?
    }

    public function testTemplateTypes()
    {
        $type = self::makePHPDocType('TypeTestClass<A1,B2>');
        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('A1')));
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('B2')));
    }

    public function testTemplateTypesWithArray()
    {
        $type = self::makePHPDocType('TypeTestClass<array<string>,array<int>>');  // not exactly a template, but has the same parsing
        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('string[]')));
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('int[]')));
    }

    public function testTemplateTypesWithTemplates()
    {
        $type = self::makePHPDocType('TypeTestClass<T1<int,string[]>,T2>');  // not exactly a template, but has the same parsing
        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('T1<int,string[]>')), "Unexpected value for " . (string)$parts[0]);
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('T2')));
        $inner_parts = $parts[0]->getTemplateParameterTypeList();
        $this->assertCount(2, $inner_parts);
        $this->assertTrue($inner_parts[0]->isType(self::makePHPDocType('int')));
        $this->assertTrue($inner_parts[1]->isType(self::makePHPDocType('string[]')));
    }

    public function testTemplateTypesWithNullable()
    {
        $type = self::makePHPDocType('TypeTestClass<'.'?int,?string>');  // not exactly a template, but has the same parsing
        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('?int')), "Unexpected value for " . (string)$parts[0]);
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('?string')));
    }

    /**
     * Regression test - Phan parses ?int[] as ?(int[])
     */
    public function testGenericArrayNullable()
    {
        $genericArrayType = self::makePHPDocType('?int[]');
        $expectedGenericArrayType = GenericArrayType::fromElementType(
            IntType::instance(false),
            true
        );
        $this->assertSameType($expectedGenericArrayType, $genericArrayType);
        $genericArrayArrayType = self::makePHPDocType('?int[][]');
        $expectedGenericArrayArrayType = GenericArrayType::fromElementType(
            GenericArrayType::fromElementType(
                IntType::instance(false),
                false
            ),
            true
        );
        $this->assertSameType($expectedGenericArrayArrayType, $genericArrayArrayType);
    }

    public function testArrayAlternate()
    {
        $stringArrayType = self::makePHPDocType('array<string>');
        $expectedStringArrayType = GenericArrayType::fromElementType(
            StringType::instance(false),
            false
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);

        $stringArrayType2 = self::makePHPDocType('array<mixed,string>');
        $this->assertSameType($expectedStringArrayType, $stringArrayType2);

        // We don't track key types yet, but may track them in the future.
        $stringArrayType3 = self::makePHPDocType('array<int,string>');
        $this->assertSameType($expectedStringArrayType, $stringArrayType3);

        // Allow space
        $stringArrayType4 = self::makePHPDocType('array<mixed, string>');
        $this->assertSameType($expectedStringArrayType, $stringArrayType4);

        // Nested array types.
        $expectedStringArrayArrayType = GenericArrayType::fromElementType(
            $expectedStringArrayType,
            false
        );
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<string[]>');
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<string>[]');
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<array<string>>');
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<mixed,array<mixed,string>>');
    }

    public function testArrayExtraBrackets()
    {
        $stringArrayType = self::makePHPDocType('?(float[])');
        $expectedStringArrayType = GenericArrayType::fromElementType(
            FloatType::instance(false),
            true
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);
        $this->assertSame('?float[]', (string)$stringArrayType);
    }

    public function testArrayExtraBracketsForElement()
    {
        $stringArrayType = self::makePHPDocType('(?float)[]');
        $expectedStringArrayType = GenericArrayType::fromElementType(
            FloatType::instance(true),
            false
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);
        $this->assertSame('(?float)[]', (string)$stringArrayType);
    }

    public function testArrayExtraBracketsAfterNullable()
    {
        $stringArrayType = self::makePHPDocType('?(float)[]');
        $expectedStringArrayType = GenericArrayType::fromElementType(
            FloatType::instance(false),
            true
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);
        $this->assertSame('?float[]', (string)$stringArrayType);
    }

    /**
     * @dataProvider canCastToTypeProvider
     */
    public function testCanCastToType(string $fromTypeString, string $toTypeString)
    {
        $fromType = self::makePHPDocType($fromTypeString);
        $toType = self::makePHPDocType($toTypeString);
        $this->assertTrue($fromType->canCastToType($toType), "expected $fromTypeString to be able to cast to $toTypeString");
    }

    public function canCastToTypeProvider() : array
    {
        return [
            ['int', 'int'],
            ['int', 'float'],
            ['int', 'mixed'],
            ['mixed', 'int'],
            ['null', 'mixed'],
            ['null[]', 'mixed[]'],
        ];
    }
}
