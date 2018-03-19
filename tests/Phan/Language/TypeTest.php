<?php declare(strict_types = 1);
namespace Phan\Tests\Language;

use Phan\Tests\BaseTest;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableDeclarationType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\ClosureDeclarationType;
use Phan\Language\Type\ClosureDeclarationParameter;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;

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

    const delimited_type_regex_or_this = '@^' . Type::type_regex_or_this . '$@';

    public function assertParsesAsType(Type $expected_type, string $type_string)
    {
        $this->assertTrue(\preg_match(self::delimited_type_regex_or_this, $type_string) > 0, "Failed to parse '$type_string'");
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

    private function assertSameType(Type $expected, Type $actual, string $extra = '')
    {
        $message = \sprintf("Expected %s to be %s", (string)$actual, (string)$expected);
        if ($extra) {
            $message .= ": $extra";
        }
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
        $expectedGenericArrayType = self::createGenericArrayTypeWithMixedKey(
            self::createGenericArrayTypeWithMixedKey(
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
        $expectedGenericArrayType = self::createGenericArrayTypeWithMixedKey(
            IntType::instance(false),
            true
        );
        $this->assertSameType($expectedGenericArrayType, $genericArrayType);
        $genericArrayArrayType = self::makePHPDocType('?int[][]');
        $expectedGenericArrayArrayType = self::createGenericArrayTypeWithMixedKey(
            self::createGenericArrayTypeWithMixedKey(
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
        $expectedStringArrayType = self::createGenericArrayTypeWithMixedKey(
            StringType::instance(false),
            false
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);

        $stringArrayType2 = self::makePHPDocType('array<mixed,string>');
        $this->assertSameType($expectedStringArrayType, $stringArrayType2);

        // We track key types.
        $expectedStringArrayTypeWithIntKey = GenericArrayType::fromElementType(
            StringType::instance(false),
            false,
            GenericArrayType::KEY_INT
        );
        $stringArrayType3 = self::makePHPDocType('array<int,string>');
        $this->assertSameType($expectedStringArrayTypeWithIntKey, $stringArrayType3);

        // Allow space
        $stringArrayType4 = self::makePHPDocType('array<mixed, string>');
        $this->assertSameType($expectedStringArrayType, $stringArrayType4);

        // Combination of int|string in array key results in mixed key
        $stringArrayType5 = self::makePHPDocType('array<int|string, string>');
        $this->assertSameType($expectedStringArrayType, $stringArrayType5);

        // Nested array types.
        $expectedStringArrayArrayType = self::createGenericArrayTypeWithMixedKey(
            $expectedStringArrayType,
            false
        );
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<string[]>');
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<string>[]');
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<array<string>>');
        $this->assertParsesAsType($expectedStringArrayArrayType, 'array<mixed,array<mixed,string>>');
    }

    public function testArrayNested()
    {
        $deeplyNestedArray = self::makePHPDocType('array<int,array<mixed,array<mixed,stdClass>>>');
        $this->assertSame('array<int,\stdClass[][]>', (string)$deeplyNestedArray);
    }

    public function testArrayExtraBrackets()
    {
        $stringArrayType = self::makePHPDocType('?(float[])');
        $expectedStringArrayType = self::createGenericArrayTypeWithMixedKey(
            FloatType::instance(false),
            true
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);
        $this->assertSame('?float[]', (string)$stringArrayType);
    }

    public function testArrayExtraBracketsForElement()
    {
        $stringArrayType = self::makePHPDocType('(?float)[]');
        $expectedStringArrayType = self::createGenericArrayTypeWithMixedKey(
            FloatType::instance(true),
            false
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);
        $this->assertSame('(?float)[]', (string)$stringArrayType);
    }

    public function testArrayExtraBracketsAfterNullable()
    {
        $stringArrayType = self::makePHPDocType('?(float)[]');
        $expectedStringArrayType = self::createGenericArrayTypeWithMixedKey(
            FloatType::instance(false),
            true
        );
        $this->assertSameType($expectedStringArrayType, $stringArrayType);
        $this->assertSame('?float[]', (string)$stringArrayType);
    }

    private static function makeBasicClosureParam(string $type_string) : ClosureDeclarationParameter
    {
        // is_variadic, is_reference, is_optional
        return new ClosureDeclarationParameter(
            UnionType::fromFullyQualifiedString($type_string),
            false,
            false,
            false
        );
    }

    private function verifyClosureParam(FunctionLikeDeclarationType $expected_closure_type, string $union_type_string, string $normalized_type_string)
    {
        $this->assertTrue(\preg_match(self::delimited_type_regex_or_this, $union_type_string) > 0, "Failed to parse '$union_type_string'");
        $parsed_closure_type = self::makePHPDocType($union_type_string);
        $this->assertSame(get_class($expected_closure_type), get_class($parsed_closure_type));
        $this->assertSame($normalized_type_string, (string)$parsed_closure_type, "failed parsing $union_type_string");
        $this->assertSame($normalized_type_string, (string)$expected_closure_type, "Bad precondition for $expected_closure_type");
        $this->assertTrue($expected_closure_type->canCastToType($parsed_closure_type), "failed casting $union_type_string");
        $this->assertTrue($parsed_closure_type->canCastToType($expected_closure_type), "failed casting $union_type_string");
    }

    public function testClosureAnnotation()
    {
        $expected_closure_void_type = new ClosureDeclarationType(
            new Context(),
            [],
            VoidType::instance(false)->asUnionType(),
            false,
            false
        );
        foreach (['Closure():void', 'Closure()'] as $union_type_string) {
            $this->verifyClosureParam($expected_closure_void_type, $union_type_string, 'Closure():void');
        }
    }

    public function testCallableAnnotation()
    {
        $expected_closure_void_type = new CallableDeclarationType(
            new Context(),
            [self::makeBasicClosureParam('string')],
            IntType::instance(false)->asUnionType(),
            false,
            false
        );
        foreach (['callable(string):int', 'callable(string $x):int'] as $union_type_string) {
            $this->verifyClosureParam($expected_closure_void_type, $union_type_string, 'callable(string):int');
        }
    }

    public function testClosureBasicAnnotation()
    {
        $expected_closure_type = new ClosureDeclarationType(
            new Context(),
            [self::makeBasicClosureParam('int'), self::makeBasicClosureParam('mixed')],
            IntType::instance(false)->asUnionType(),
            false,
            false
        );
        foreach (['Closure(int,mixed):int', '\Closure(int,mixed):int', 'Closure(int $p1,$other): int'] as $union_type_string) {
            $this->verifyClosureParam($expected_closure_type, $union_type_string, 'Closure(int,mixed):int');
        }
    }

    public function testClosureUnionAnnotation()
    {
        $nullable_scalar_param = self::makeBasicClosureParam('?int|?string');

        $expected_closure_scalar_type = new ClosureDeclarationType(
            new Context(),
            [$nullable_scalar_param],
            UnionType::fromFullyQualifiedString('?int|?string'),
            false,
            false
        );
        foreach ([
            'Closure(?int|?string $argName) : (?int|?string)',
            'Closure(?int|?string):(?int|?string)',
        ] as $union_type_string) {
            $this->verifyClosureParam($expected_closure_scalar_type, $union_type_string, 'Closure(?int|?string):(?int|?string)');
        }
    }

    public function testClosureRefVariadicAnnotations()
    {
        // is_variadic, is_reference, is_optional
        $string_ref_annotation = new ClosureDeclarationParameter(UnionType::fromFullyQualifiedString('string'), false, true, false);
        $variadic_bool_annotation = new ClosureDeclarationParameter(UnionType::fromFullyQualifiedString('bool'), true, true, false);

        $expected_closure_type = new ClosureDeclarationType(
            new Context(),
            [$string_ref_annotation, $variadic_bool_annotation],
            UnionType::fromFullyQualifiedString('void'),
            false,
            false
        );
        foreach (['Closure(string &$arg, bool &...$switches) : void', 'Closure(string&,bool&...)'] as $union_type_string) {
            $this->verifyClosureParam($expected_closure_type, $union_type_string, 'Closure(string&,bool&...):void');
        }
    }

    public function testClosureOptionalParam()
    {
        // is_variadic, is_reference, is_optional
        $optional_string_annotation = new ClosureDeclarationParameter(UnionType::fromFullyQualifiedString('?string'), false, false, true);
        $optional_int_annotation = new ClosureDeclarationParameter(UnionType::fromFullyQualifiedString('int'), false, false, true);

        $expected_closure_type = new ClosureDeclarationType(
            new Context(),
            [$optional_string_annotation, $optional_int_annotation],
            UnionType::fromFullyQualifiedString('void'),
            false,
            false
        );
        foreach (['Closure(string $arg=null,int $y = 2) : void', 'Closure(?string=,int=)'] as $union_type_string) {
            $this->verifyClosureParam($expected_closure_type, $union_type_string, 'Closure(?string=,int=):void');
        }
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

    /**
     * @dataProvider arrayShapeProvider
     */
    public function testArrayShape($normalized_union_type_string, $type_string)
    {
        $this->assertTrue(\preg_match('@^' . Type::type_regex . '$@', $type_string) > 0, "Failed to parse '$type_string' with type_regex");
        $this->assertTrue(\preg_match('@^' . Type::type_regex_or_this . '$@', $type_string) > 0, "Failed to parse '$type_string' with type_regex_or_this");
        $actual_type = self::makePHPDocType($type_string);
        $expected_flattened_type = UnionType::fromStringInContext($normalized_union_type_string, new Context(), Type::FROM_PHPDOC);
        $this->assertInstanceOf(ArrayShapeType::class, $actual_type, "Failed to create expected class for $type_string");
        assert($actual_type instanceof ArrayShapeType);
        $actual_flattened_type = UnionType::of($actual_type->withFlattenedArrayShapeTypeInstances());
        $this->assertTrue($expected_flattened_type->isEqualTo($actual_flattened_type), "expected $actual_flattened_type to equal $expected_flattened_type");
    }

    public function arrayShapeProvider()
    {
        return [
            [
                'array',
                'array{}'
            ],
            [
                'array<string,int>',
                'array{field:int}'
            ],
            [
                'array<string,int>',
                'array{field:int=}'
            ],
            [
                'array<string,int>|array<string,string>',
                'array{field:int|string}'
            ],
            [
                'array<int,int>|array<int,string>',
                'array{0:int,1:string}'
            ],
            [
                'array<int,int>|array<int,stdClass>|array<int,string>',
                'array{0:int,1:string,2:stdClass}'
            ],
            [
                'array<string,int>',
                'array{string:int}'
            ],
            [
                'array<string,\T<int>>',
                'array{field:T<int>}'
            ],
            [
                'array<string,?int>',
                'array{field:?int}',
            ],
            [
                'array<string,?int>|array<string,int[]>',
                'array{field:int[],field2:?int}'
            ],
            [
                'array<string,array{}>',
                'array{field:array{}}'
            ],
            [
                'array<string,array{innerField:int}>',
                'array{field:array{innerField:int}}'
            ],
        ];
    }

    /** @dataProvider unparseableArrayShapeProvider */
    public function testUnparseableArrayShape($type_string)
    {
        $this->assertFalse(\preg_match('@^' . Type::type_regex . '$@', $type_string) > 0, "Failed to parse '$type_string' with type_regex");
        $this->assertFalse(\preg_match('@^' . Type::type_regex_or_this . '$@', $type_string) > 0, "Failed to parse '$type_string' with type_regex_or_this");
    }

    public function unparseableArrayShapeProvider()
    {
        return [
            ['array{'],
            ['{}'],
            ['array{,field:int}'],
            ['array{field:}'],
            ['array{::int}'],
        ];
    }

    private static function createGenericArrayTypeWithMixedKey(Type $type, bool $is_nullable) : GenericArrayType
    {
        return GenericArrayType::fromElementType($type, $is_nullable, GenericArrayType::KEY_MIXED);
    }
}
