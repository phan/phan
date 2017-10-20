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
    private function makePHPDocType(string $type) : Type
    {
        return Type::fromStringInContext($type, new Context(), Type::FROM_PHPDOC);
    }


    public function testBasicTypes()
    {
        $this->assertSame(ArrayType::instance(false), self::makePHPDocType('array'));
        $this->assertSame(ArrayType::instance(true), self::makePHPDocType('?array'));
        $this->assertSame(ArrayType::instance(true), self::makePHPDocType('?ARRAY'));
        $this->assertSame(BoolType::instance(false), self::makePHPDocType('bool'));
        $this->assertSame(CallableType::instance(false), self::makePHPDocType('callable'));
        $this->assertSame(ClosureType::instance(false), self::makePHPDocType('Closure'));
        $this->assertSame(FalseType::instance(false), self::makePHPDocType('false'));
        $this->assertSame(FloatType::instance(false), self::makePHPDocType('float'));
        $this->assertSame(IntType::instance(false), self::makePHPDocType('int'));
        $this->assertSame(IterableType::instance(false), self::makePHPDocType('iterable'));
        $this->assertSame(MixedType::instance(false), self::makePHPDocType('mixed'));
        $this->assertSame(ObjectType::instance(false), self::makePHPDocType('object'));
        $this->assertSame(ResourceType::instance(false), self::makePHPDocType('resource'));
        $this->assertSame(StaticType::instance(false), self::makePHPDocType('static'));
        $this->assertSame(StringType::instance(false), self::makePHPDocType('string'));
        $this->assertSame(TrueType::instance(false), self::makePHPDocType('true'));
        $this->assertSame(VoidType::instance(false), self::makePHPDocType('void'));
    }

    private function assertSameType(Type $expected, Type $actual)
    {
        $this->assertEquals($expected, $actual);
        $this->assertSame($expected, $actual);
    }

    public function testUnionTypeOfThis()
    {
        $this->assertSameType(StaticType::instance(false), self::makePHPDocType('$this'));
        $this->assertSameType(StaticType::instance(true), self::makePHPDocType('?$this'));
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
        $this->assertSame($expectedGenericArrayType, $genericArrayType);
        $this->assertSame('int[][]', (string)$expectedGenericArrayType);
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
}
