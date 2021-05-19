<?php

declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\AST\UnionTypeVisitor;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\IntersectionType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Tests\BaseTest;

// Grab these before we define our own classes
$internal_class_name_list = \get_declared_classes();
$internal_interface_name_list = \get_declared_interfaces();
$internal_trait_name_list = \get_declared_traits();
$internal_function_name_list = \get_defined_functions()['internal'];

/**
 * Unit tests of the many methods of UnionType
 * @phan-file-suppress PhanThrowTypeAbsentForCall
 */
final class UnionTypeTest extends BaseTest
{
    /** @var CodeBase The code base within which this unit test is operating */
    protected static $code_base = null;

    protected function setUp(): void
    {
        // Deliberately not calling parent::setUp()
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;

        if (self::$code_base === null) {
            self::$code_base = new CodeBase(
                $internal_class_name_list,
                $internal_interface_name_list,
                $internal_trait_name_list,
                CodeBase::getPHPInternalConstantNameList(),
                $internal_function_name_list
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        // @phan-suppress-next-line PhanTypeMismatchPropertyProbablyReal
        self::$code_base = null;
    }

    public function testInt(): void
    {
        Phan::setIssueCollector(new BufferingCollector());
        $this->assertUnionTypeStringEqual('rand(0,20)', 'int');
        $this->assertUnionTypeStringEqual('rand(0,20) + 1', 'int');
        $this->assertUnionTypeStringEqual('42 + 2', '44');
        $this->assertUnionTypeStringEqual('46 - 2', '44');
        $this->assertUnionTypeStringEqual('PHP_INT_MAX', (string)\PHP_INT_MAX);
        $this->assertUnionTypeStringEqual('PHP_INT_MAX + PHP_INT_MAX', \var_export(\PHP_INT_MAX + \PHP_INT_MAX, true));
        $this->assertUnionTypeStringEqual('2 ** -9999999', '0.0');
        $this->assertUnionTypeStringEqual('2 ** 9999999', 'float');
        $this->assertUnionTypeStringEqual('0 ** 0', '1');
        $this->assertUnionTypeStringEqual('1 - 2.5', '-1.5');
        $this->assertUnionTypeStringEqual('1.2 / 0.0', 'float');
        $this->assertUnionTypeStringEqual('0.0 / 3', '0.0');
        $this->assertUnionTypeStringEqual('rand() / getrandmax()', 'float|int');
        $this->assertUnionTypeStringEqual('1.2 / 0.5', '2.4');
        $this->assertUnionTypeStringEqual('1 << 2.3', 'int');
        $this->assertUnionTypeStringEqual('1 | 1', '1');
        $this->assertUnionTypeStringEqual('1 | 2', '3');
        $this->assertUnionTypeStringEqual('4 >> 1', 'int');
        $this->assertUnionTypeStringEqual('4 >> 1.2', 'int');
        $this->assertUnionTypeStringEqual('1 << rand(0,20)', 'int');
        $this->assertUnionTypeStringEqual('-42', '-42');
        $this->assertUnionTypeStringEqual('+42', '42');
        $this->assertUnionTypeStringEqual('+"42"', '42');
        $this->assertUnionTypeStringEqual('-"42"', '-42');
        $this->assertUnionTypeStringEqual('-"a string"', '0');  // also emits a warning
        $this->assertUnionTypeStringEqual('-"0x12"', '0');  // also emits a warning
        $this->assertUnionTypeStringEqual('+-42', '-42');
        $this->assertUnionTypeStringEqual('~42', '-43');
        $this->assertUnionTypeStringEqual('12.3 % 5.2', 'int');
        $this->assertUnionTypeStringEqual('~-43', '42');
        $this->assertUnionTypeStringEqual('$argc', 'int');
        $this->assertUnionTypeStringEqual('$argc - 1', 'int');
        $this->assertUnionTypeStringEqual('$argc + 1', 'int');
        $this->assertUnionTypeStringEqual('$argc * 1', 'int');
        $this->assertUnionTypeStringEqual('$argc - 1.5', 'float');
        $this->assertUnionTypeStringEqual('$argc + 1.5', 'float');
        $this->assertUnionTypeStringEqual('$argc * 1.5', 'float');
        $this->assertUnionTypeStringEqual('constant($argv[0]) - constant($argv[1])', 'float|int');
        $this->assertUnionTypeStringEqual('constant($argv[0]) + constant($argv[1])', 'float|int');
        $this->assertUnionTypeStringEqual('-constant($argv[0])', '0|float|int');
        $this->assertUnionTypeStringEqual('-(1.5)', '-1.5');
        $this->assertUnionTypeStringEqual('(rand(0,1) ? "12" : 2.5) - (rand(0,1) ? "3" : 1.5)', 'float|int');
        $this->assertUnionTypeStringEqual('(rand(0,1) ? "12" : 2.5) * (rand(0,1) ? "3" : 1.5)', 'float|int');
        $this->assertUnionTypeStringEqual('(rand(0,1) ? "12" : 2.5) + (rand(0,1) ? "3" : 1.5)', 'float|int');
    }

    public function testComplex(): void
    {
        Phan::setIssueCollector(new BufferingCollector());
        $this->assertUnionTypeStringEqual('$x=constant($argv[0]); $x <<= 2; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=constant($argv[0]); $x >>= 2; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=constant($argv[0]); $x %= 2; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=constant($argv[0]); $x .= "suffix"; $x', 'string');
        $this->assertUnionTypeStringEqual('$x=constant($argv[0]); $x .= 33; $x', 'string');
        // TODO: Optionally, convert to "prefixSuffix"
        $this->assertUnionTypeStringEqual('$x="prefixSuffix"; $x .= "Suffix"; $x', 'string');
        $this->assertUnionTypeStringEqual('$x=[2]; $x += [3,"other"]; $x', "array{0:2,1:'other'}");
        $this->assertUnionTypeStringEqual('$x=2; $x += 3; $x', '5');
        $this->assertUnionTypeStringEqual('$x=2.5; $x += 3; $x', '5.5');
        $this->assertUnionTypeStringEqual('$x=2; $x += 3.5; $x', '5.5');
        $this->assertUnionTypeStringEqual('$x=2; $x += (rand()%2) ? 3.5 : 2; $x', 'float|int');
        $this->assertUnionTypeStringEqual('$x=2; $x -= 3; $x', '-1');
        $this->assertUnionTypeStringEqual('$x=2; $x -= 3.5; $x', '-1.5');
        $this->assertUnionTypeStringEqual('$x=2; $x *= 3.5; $x', '7.0');
        $this->assertUnionTypeStringEqual('$x=2; $x *= 3; $x', '6');
        $this->assertUnionTypeStringEqual('$x=2; $x **= 3; $x', '8');
        $this->assertUnionTypeStringEqual('$x=4; $x **= 3.5; $x', '128.0');
        $this->assertUnionTypeStringEqual('$x=5; $x %= 3; $x', '2');  // This casts to float
        $this->assertUnionTypeStringEqual('$x=21.2; $x %= 3.5; $x', '0');
        $this->assertUnionTypeStringEqual('$x=23.2; $x %= 3.5; $x', '2');
        $this->assertUnionTypeStringEqual('$x=5;    $x ^= 3;    $x', '6');
        $this->assertUnionTypeStringEqual('$x="ab"; $x ^= "ac"; $x', 'string');
        $this->assertUnionTypeStringEqual('$x=5;    $x |= 3;    $x', '7');
        $this->assertUnionTypeStringEqual('$x="ab"; $x |= "ac"; $x', 'string');
        // `&=` is a bitwise and, not to be confused with `=&`
        $this->assertUnionTypeStringEqual('$x=5;    $x &= 3;    $x', '1');
        $this->assertUnionTypeStringEqual('$x="ab"; $x &= "ac"; $x', 'string');

        // TODO: Implement more code to warn about invalid operands.
        // Evaluating this should result in '0'
        $this->assertUnionTypeStringEqual('$x=(new stdClass()); $x &= 2; $x', 'int');
        $this->assertUnionTypeStringEqual('$x = stdClass::class; new $x();', '\stdClass');

        // !is_numeric removes integers from the type
        $this->assertUnionTypeStringEqual('$x = rand() ? "a string" : 1; assert(!is_numeric($x)); $x', "'a string'");
        $this->assertUnionTypeStringEqual('$x = rand() ? 2.4 : new stdClass(); assert(!is_numeric($x)); $x', '\stdClass');
        $this->assertUnionTypeStringEqual('$x = rand() ? $argv[0] : $argc; assert(!is_numeric($x)); $x', 'string');
        $this->assertUnionTypeStringEqual('"foo" . PHP_EOL', "'foo" . \addcslashes(\PHP_EOL, "\r\n") . "'");
    }

    public function testString(): void
    {
        $this->assertUnionTypeStringEqual(
            '"a string"',
            "'a string'"
        );
    }

    public function testArrayUniform(): void
    {
        $this->assertUnionTypeStringEqual(
            '[false => \'$string\']',
            "array{0:'\$string'}"
        );
    }

    public function testArrayUniformMultipleValuesLiteral(): void
    {
        $this->assertUnionTypeStringEqual(
            '[false => rand(0,1) ? zend_version() : 2]',
            "array{0:2|string}"
        );
    }

    public function testArrayUniformMultipleValues(): void
    {
        $this->assertUnionTypeStringEqual(
            '[false => rand(0,1) ? zend_version() : 2]',
            'array{0:2|string}'
        );
    }

    public function testArrayMixed(): void
    {
        $this->assertUnionTypeStringEqual(
            '[1, zend_version()]',
            'array{0:1,1:string}'
        );
    }

    public function testArrayEmpty(): void
    {
        $this->assertUnionTypeStringEqual(
            '[]',
            'array{}'
        );
    }
    public function testInternalObject(): void
    {
        $this->assertUnionTypeStringEqual(
            'new SplStack();',
            '\\ArrayAccess|\\Countable|\\Iterator|\\Serializable|\\SplDoublyLinkedList|\\SplStack|\\Traversable|iterable'
        );
    }

    public function testGenericArrayType(): void
    {
        $type = self::createGenericArrayTypeWithMixedKey(
            self::createGenericArrayTypeWithMixedKey(
                IntType::instance(false),
                false
            ),
            false
        );

        $this->assertSame(
            $type->genericArrayElementType()->__toString(),
            "int[]"
        );
        $this->assertSame(
            $type->genericArrayElementUnionType()->__toString(),
            "int[]"
        );
    }

    public function testGenericArrayTypeFromString(): void
    {
        $type = Type::fromFullyQualifiedString("int[][]");
        $this->assertInstanceOf(GenericArrayType::class, $type);

        $this->assertSame(
            $type->genericArrayElementType()->__toString(),
            "int[]"
        );
    }

    /**
     * Assert that a piece of code produces a type
     * with the given name
     *
     * @param string $code_stub
     * A code stub for which '<?php ' will be prefixed
     *
     * @param string $type_name
     * The expected type of the statement
     */
    private function assertUnionTypeStringEqual(
        string $code_stub,
        string $type_name
    ): void {
        $this->assertSame(
            $type_name,
            self::typeStringFromCode('<' . '?php ' . $code_stub . ';'),
            "Unexpected result of $code_stub"
        );
    }

    /**
     * @return string
     * A string representation of the union type begotten from
     * the first statement in the statement list in the given
     * code.
     */
    private static function typeStringFromCode(string $code): string
    {
        $stmt_list = \ast\parse_code(
            $code,
            Config::AST_VERSION
        );
        $last_node = \array_pop($stmt_list->children);
        $context = new Context();
        (new BlockAnalysisVisitor(self::$code_base, new Context()))($stmt_list);
        return UnionTypeVisitor::unionTypeFromNode(
            self::$code_base,
            $context,  // NOTE: This has to be new - Otherwise, object ids will be reused and inferences would be cached for those.
            $last_node
        )->asExpandedTypes(self::$code_base)->__toString();
    }

    private const VALID_UNION_TYPE_REGEX = '(^(' . UnionType::union_type_regex_or_this . ')$)';

    private static function makePHPDocUnionType(string $union_type_string): UnionType
    {
        self::assertTrue(\preg_match(self::VALID_UNION_TYPE_REGEX, $union_type_string) > 0, "$union_type_string should be parsed by regex");
        return UnionType::fromStringInContext($union_type_string, new Context(), Type::FROM_PHPDOC);
    }

    private static function makePHPDocType(string $type): Type
    {
        return Type::fromStringInContext($type, new Context(), Type::FROM_PHPDOC);
    }

    private function assertIsType(Type $type, string $union_type_string): void
    {
        $union_type = self::makePHPDocUnionType($union_type_string);
        $this->assertTrue($union_type->hasType($type), "Expected $union_type (from $union_type_string) to be $type");
    }

    public function testExpandedTypes(): void
    {
        $this->assertSame(
            \PHP_MAJOR_VERSION >= 8 ? '\Exception[]|\Stringable[]|\Throwable[]' : '\Exception[]|\Throwable[]',
            UnionType::fromFullyQualifiedPHPDocString('\Exception[]')->asExpandedTypes(self::$code_base)->__toString()
        );
        $this->assertSame(
            \PHP_MAJOR_VERSION >= 8 ? 'array<int,\Exception>|array<int,\Stringable>|array<int,\Throwable>' : 'array<int,\Exception>|array<int,\Throwable>',
            UnionType::fromFullyQualifiedPHPDocString('array<int,\Exception>')->asExpandedTypes(self::$code_base)->__toString()
        );
    }

    public function testBasicTypes(): void
    {
        $this->assertIsType(ArrayType::instance(false), 'array');
        $this->assertIsType(ArrayType::instance(true), '?array');
        $this->assertIsType(ArrayType::instance(true), '?ARRAY');
        $this->assertIsType(BoolType::instance(false), 'bool');
        $this->assertIsType(CallableType::instance(false), 'callable');
        $this->assertIsType(ClosureType::instance(false), 'Closure');
        $this->assertIsType(FalseType::instance(false), 'false');
        $this->assertIsType(FloatType::instance(false), 'float');
        $this->assertIsType(IntType::instance(false), 'int');
        $this->assertIsType(IterableType::instance(false), 'iterable');
        $this->assertIsType(MixedType::instance(false), 'mixed');
        $this->assertIsType(ObjectType::instance(false), 'object');
        $this->assertIsType(ResourceType::instance(false), 'resource');
        $this->assertIsType(StaticType::instance(false), 'static');
        $this->assertIsType(StringType::instance(false), 'string');
        $this->assertIsType(TrueType::instance(false), 'true');
        $this->assertIsType(VoidType::instance(false), 'void');
    }

    public function testTemplateTypes(): void
    {
        $union_type = self::makePHPDocUnionType('TypeTestClass<A1,B2>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);
        $this->assertInstanceOf(Type::class, $type);

        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('A1')));
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('B2')));
    }

    public function testTemplateNullableTypes(): void
    {
        $this->assertRegExp('/^' . Type::type_regex . '$/', 'TypeTestClass<A1|null,B2|null>', 'type_regex does not support nested pipes');
        $this->assertRegExp('/^' . Type::type_regex_or_this . '$/', 'TypeTestClass<A1|null>', 'type_regex_or_this does not support nested pipes');
        $this->assertRegExp('/^' . Type::type_regex_or_this . '$/', 'TypeTestClass<A1|null,B2|null>', 'type_regex_or_this does not support nested pipes');
        $union_type = self::makePHPDocUnionType('TypeTestClass<A1,B2|null>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);
        $this->assertInstanceOf(Type::class, $type);

        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('A1')));
        $this->assertTrue($parts[1]->isEqualTo(self::makePHPDocUnionType('B2|null')));
    }

    public function testNormalize(): void
    {
        $union_type = self::makePHPDocUnionType('object|null');
        $this->assertSame(2, $union_type->typeCount());

        $new_union_type = $union_type->asNormalizedTypes();
        $this->assertSame('?object', (string)$new_union_type);
        $type_set = $new_union_type->getTypeSet();
        $this->assertSame(ObjectType::instance(true), \reset($type_set));
    }

    public function testAlternateArrayTypes(): void
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('array<int,string>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('array<int,string>', (string)$type);
        $expected_type = GenericArrayType::fromElementType(StringType::instance(false), false, GenericArrayType::KEY_INT);
        $this->assertSame($expected_type, $type);
    }

    public function testAlternateArrayTypesNullable(): void
    {
        // array keys are nullable integers, values are strings
        $union_type = self::makePHPDocUnionType('array<string,?int>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('array<string,?int>', (string)$type);
        $expected_type = GenericArrayType::fromElementType(IntType::instance(true), false, GenericArrayType::KEY_STRING);
        $this->assertSame($expected_type, $type);
    }

    public function testNestedArrayTypes(): void
    {
        $union_type = self::makePHPDocUnionType('array<int|string>');
        $this->assertSame('int[]|string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());

        $union_type = self::makePHPDocUnionType('(int|string)[]');
        $this->assertSame('int[]|string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());

        $union_type = self::makePHPDocUnionType('((int)|(string))[]');
        $this->assertSame('int[]|string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $array_type = static function (Type $type): GenericArrayType {
            return self::createGenericArrayTypeWithMixedKey($type, false);
        };

        $this->assertSame($array_type(IntType::instance(false)), $type);

        $union_type = self::makePHPDocUnionType('array<bool|array<array<int|string>>>');
        $this->assertSame('bool[]|int[][][]|string[][][]', (string)$union_type);
        $this->assertSame(3, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame($array_type(BoolType::instance(false)), $type);
        $type = \next($types);
        $this->assertSame($array_type($array_type($array_type(IntType::instance(false)))), $type);
        $type = \next($types);
        $this->assertSame($array_type($array_type($array_type(StringType::instance(false)))), $type);
    }

    /**
     * Assert values that should not be matched by the regular expression for a valid union type.
     * This regular expression controls what can get passed to UnionType::from...()
     *
     * @dataProvider unparseableUnionTypeProvider
     */
    public function testUnparseableUnionType(string $type): void
    {
        $this->assertNotRegExp(self::VALID_UNION_TYPE_REGEX, $type, "'$type' should be unparseable");
    }

    /** @return list<array{0:string}> */
    public function unparseableUnionTypeProvider(): array
    {
        return [
            ['()'],
            ['()'],
            [')('],
            ['<>'],
            ['[]'],
            ['{}'],
            ['?()'],
            ['<=>'],
            ['()[]'],
            ['array<>'],
            ['[(a|b)]'],
            ['int|'],
            ['|int'],
            ['int|?'],
            ['int|()'],
            ['(int|)'],
            ['array{'],
            ['array{}}'],
            ['(int){}'],
        ];
    }

    public function testComplexUnionType(): void
    {
        $union_type = self::makePHPDocUnionType('(int|string)|Closure():(int|stdClass)');
        $this->assertSame('Closure():(\stdClass|int)|int|string', (string)$union_type);
        $this->assertSame(3, $union_type->typeCount());
    }

    public function testClosureInsideArrayShape(): void
    {
        $union_type = self::makePHPDocUnionType('array{key:Closure(int,string|int):void}');
        $this->assertSame('array{key:Closure(int,int|string):void}', (string)$union_type);
        $this->assertSame(1, $union_type->typeCount());
    }

    public function testClosureInsideGenericArray(): void
    {
        $union_type = self::makePHPDocUnionType('array<int,Closure(int,string|int):void>');
        $this->assertSame('array<int,Closure(int,int|string):void>', (string)$union_type);
        $this->assertSame(1, $union_type->typeCount());
    }

    public function testArrayShapeInsideClosure(): void
    {
        $union_type = self::makePHPDocUnionType('Closure(array{key:int,other:string|int}):void');
        $this->assertSame('Closure(array{key:int,other:int|string}):void', (string)$union_type);
        $this->assertSame(1, $union_type->typeCount());
    }

    public function testClosureInsideClosure(): void
    {
        $union_type = self::makePHPDocUnionType('Closure(int|bool,Closure(int,string|false):bool):void');
        $this->assertSame('Closure(bool|int,Closure(int,false|string):bool):void', (string)$union_type);
        $this->assertSame(1, $union_type->typeCount());
    }

    public function testNullableBasicType(): void
    {
        $union_type = self::makePHPDocUnionType('?(int|string|float|false)');
        $this->assertSame('?false|?float|?int|?string', (string)$union_type);
    }

    public function testNullableBasicArrayType(): void
    {
        $union_type = self::makePHPDocUnionType('?(int|string)[]');
        $this->assertSame('?int[]|?string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());
        $union_type = self::makePHPDocUnionType('?((int|string))[]');
        $this->assertSame('?int[]|?string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());
    }

    public function testNullableArrayType(): void
    {
        $union_type = self::makePHPDocUnionType('?string[]');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('?string[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(false), true), $type);
    }

    public function testNullableBracketedArrayType(): void
    {
        $union_type = self::makePHPDocUnionType('(?string)[]');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('(?string)[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(true), false), $type);
    }

    public function testNullableBracketedArrayType2(): void
    {
        $union_type = self::makePHPDocUnionType('(?string)[]|(int)[]');
        $this->assertSame(2, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        [$type1, $type2] = \array_values($types);

        $this->assertSame('(?string)[]', (string)$type1);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(true), false), $type1);

        $this->assertSame('int[]', (string)$type2);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(IntType::instance(false), false), $type2);
    }

    public function testNullableBracketedArrayType3(): void
    {
        $union_type = self::makePHPDocUnionType('?(string[])|?(int[])');
        $this->assertSame(2, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        [$type1, $type2] = \array_values($types);

        $this->assertSame('?string[]', (string)$type1);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(false), true), $type1);

        $this->assertSame('?int[]', (string)$type2);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(IntType::instance(false), true), $type2);
    }

    public function testNullableArrayOfNullables(): void
    {
        $union_type = self::makePHPDocUnionType('?(?string)[]');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('?(?string)[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(true), true), $type);
    }

    public function testNullableExtraBracket(): void
    {
        $union_type = self::makePHPDocUnionType('?(string[])');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('?string[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(false), true), $type);
    }

    public function testUnionInArrayShape(): void
    {
        $union_type = self::makePHPDocUnionType('array{key:int|string[]}');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('array{key:int|string[]}', (string)$type);
        $this->assertSame('array<string,int>|array<string,string[]>', (string)$union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        $this->assertInstanceOf(ArrayShapeType::class, $type);
        $field_union_type = $type->getFieldTypes()['key'];
        $this->assertFalse($field_union_type->isPossiblyUndefined());
    }

    public function testFlattenEmptyArrayShape(): void
    {
        $union_type = self::makePHPDocUnionType('array{}|array<int,\stdClass>');

        $this->assertSame('array<int,\stdClass>|array{}', (string)$union_type);
        $this->assertSame('array<int,\stdClass>', (string)$union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        $this->assertSame('array<int,\stdClass>', (string)$union_type->withFlattenedArrayShapeTypeInstances());

        $empty_union_type = self::makePHPDocUnionType('array{}');
        $this->assertSame('array', (string)$empty_union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        $this->assertSame('array', (string)$empty_union_type->withFlattenedArrayShapeTypeInstances());

        $empty_union_type_and_literals = self::makePHPDocUnionType("array{}|2|'str'");
        $this->assertSame('array|int|string', (string)$empty_union_type_and_literals->withFlattenedArrayShapeOrLiteralTypeInstances());
        $this->assertSame("'str'|2|array", (string)$empty_union_type_and_literals->withFlattenedArrayShapeTypeInstances());
    }

    public function testOptionalInArrayShape(): void
    {
        $union_type = self::makePHPDocUnionType('array{key:int|string=}');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertSame('array{key?:int|string}', (string)$type);
        $this->assertInstanceOf(ArrayShapeType::class, $type);
        $this->assertSame('array<string,int>|array<string,string>', (string)$union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        $field_union_type = $type->getFieldTypes()['key'];
        $this->assertTrue($field_union_type->isPossiblyUndefined());
        $this->assertSame('int|string=', (string)$field_union_type);
        $this->assertSame([IntType::instance(false), StringType::instance(false)], $field_union_type->getTypeSet());
    }

    public function testSymbolInArrayShape(): void
    {
        $union_type = self::makePHPDocUnionType('array{key\x0a\\\\line\x3a:int}');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = \reset($types);

        $this->assertInstanceOf(ArrayShapeType::class, $type);
        $this->assertSame(["key\n\\line:"], \array_keys($type->getFieldTypes()));
        $this->assertSame('array{key\n\\\\line\x3a:int}', (string)$type);
        $this->assertSame('array<string,int>', (string)$union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        $field_union_type = $type->getFieldTypes()["key\n\\line:"];
        $this->assertFalse($field_union_type->isPossiblyUndefined());
        $this->assertSame('int', (string)$field_union_type);
    }

    public function testBackslashInArrayShape(): void
    {
        $union_type = self::makePHPDocUnionType('array{\n:0,\r:1,\t:2,\\\\:3}');
        $this->assertSame('array{\n:0,\r:1,\t:2,\\\\:3}', (string)$union_type);
        $type = $union_type->getTypeSet()[0];
        $this->assertInstanceOf(ArrayShapeType::class, $type);
        $this->assertSame(["\n", "\r", "\t", "\\"], \array_keys($type->getFieldTypes()));
    }

    private static function createGenericArrayTypeWithMixedKey(Type $type, bool $is_nullable): GenericArrayType
    {
        return GenericArrayType::fromElementType($type, $is_nullable, GenericArrayType::KEY_MIXED);
    }

    public function testFunctionSignatureMapConsistency(): void
    {
        $signatures_dir = \dirname(__DIR__, 3) . '/src/Phan/Language/Internal';
        $php80_map = UnionType::internalFunctionSignatureMap(80000);
        $php74_map = UnionType::internalFunctionSignatureMap(70400);
        $php73_map = UnionType::internalFunctionSignatureMap(70300);
        $php72_map = UnionType::internalFunctionSignatureMap(70200);
        $php71_map = UnionType::internalFunctionSignatureMap(70100);
        $php70_map = UnionType::internalFunctionSignatureMap(70000);
        $php56_map = UnionType::internalFunctionSignatureMap(50600);

        $php80_delta = require("$signatures_dir/FunctionSignatureMap_php80_delta.php");
        $php74_delta = require("$signatures_dir/FunctionSignatureMap_php74_delta.php");
        $php73_delta = require("$signatures_dir/FunctionSignatureMap_php73_delta.php");
        $php72_delta = require("$signatures_dir/FunctionSignatureMap_php72_delta.php");
        $php71_delta = require("$signatures_dir/FunctionSignatureMap_php71_delta.php");
        $php70_delta = require("$signatures_dir/FunctionSignatureMap_php70_delta.php");
        $this->assertDeltasApply($php80_map, $php74_map, $php80_delta, 'php80_delta');
        $this->assertDeltasApply($php74_map, $php73_map, $php74_delta, 'php74_delta');
        $this->assertDeltasApply($php73_map, $php72_map, $php73_delta, 'php73_delta');
        $this->assertDeltasApply($php72_map, $php71_map, $php72_delta, 'php72_delta');
        $this->assertDeltasApply($php71_map, $php70_map, $php71_delta, 'php71_delta');
        $this->assertDeltasApply($php70_map, $php56_map, $php70_delta, 'php70_delta');
    }

    /**
     * @param array<string, array<mixed,string>> $new_map
     * @param array<string, array<mixed,string>> $old_map
     * @param array<string, array<string, array<mixed,string>>> $deltas
     */
    private function assertDeltasApply(array $new_map, array $old_map, array $deltas, string $name): void
    {
        $errors = '';
        $new_deltas = $deltas['new'];
        $old_deltas = $deltas['old'];
        $new_deltas = \array_change_key_case($new_deltas, \CASE_LOWER);
        $old_deltas = \array_change_key_case($old_deltas, \CASE_LOWER);
        foreach ($new_deltas as $function_key => $new_signature) {
            if (($old_deltas[$function_key] ?? null) === $new_deltas) {
                $errors .= "For $function_key: Old deltas in $name were the same as new deltas\n";
            }
            $actual_new_signature = $new_map[$function_key] ?? null;
            if (!isset($old_deltas[$function_key]) && isset($old_map[$function_key])) {
                $errors .= "Expected to remove $function_key from older signature map for $name\n";
            }
            if (!$actual_new_signature) {
                $errors .= "Missing $function_key for $name in new deltas\n";
                continue;
            }
            if ($actual_new_signature !== $new_signature) {
                $errors .= "Different $function_key for $name from new deltas :\n " . \json_encode($actual_new_signature) . " !=\n " . \json_encode($new_signature) . "\n";
                continue;
            }
        }
        foreach ($old_deltas as $function_key => $old_signature) {
            $actual_old_signature = $old_map[$function_key] ?? null;
            if (!isset($new_deltas[$function_key]) && isset($new_map[$function_key])) {
                $errors .= "Expected to remove $function_key from newer signature map for $name\n";
            }
            if (!$actual_old_signature) {
                $errors .= "Missing $function_key for $name in old deltas\n";
                continue;
            }
            if ($actual_old_signature !== $old_signature) {
                $errors .= "Different $function_key for $name from old deltas :\n " . \json_encode($actual_old_signature) . " !=\n " . \json_encode($old_signature) . "\n";
                continue;
            }
        }
        $this->assertSame('', $errors);
    }

    public function testIntersectionTypeExplicit(): void
    {
        $type = self::makePHPDocUnionType('and<MyClass, MyInterfaceUTT>');
        $this->assertSame(1, $type->typeCount());
        $this->assertSame('\MyClass&\MyInterfaceUTT', (string)$type);
        $this->assertInstanceOf(IntersectionType::class, $type->getTypeSet()[0]);
    }

    public function testIntersectionTypeFallback(): void
    {
        $type = self::makePHPDocUnionType('MyClass&MyInterfaceUTT');
        $this->assertSame(1, $type->typeCount());
        $this->assertSame('\MyClass&\MyInterfaceUTT', (string)$type);
        $this->assertInstanceOf(IntersectionType::class, $type->getTypeSet()[0]);
    }

    public function testIntersectionTypeWithUnionType(): void
    {
        $type = self::makePHPDocUnionType('int|MyClass&MyInterfaceUTT');
        $this->assertSame(2, $type->typeCount());
        $this->assertSame('\MyClass&\MyInterfaceUTT|int', (string)$type);
        $this->assertInstanceOf(IntType::class, $type->getTypeSet()[0]);
        $this->assertInstanceOf(IntersectionType::class, $type->getTypeSet()[1]);
    }

    public function testIntersectionTypeInArrayFallback(): void
    {
        $type = self::makePHPDocUnionType('(MyClass&MyInterfaceUTT)[]');
        $this->assertSame(1, $type->typeCount());
        $this->assertSame('(\MyClass&\MyInterfaceUTT)[]', (string)$type);
        // @phan-suppress-next-line PhanUndeclaredMethod
        $this->assertInstanceOf(IntersectionType::class, $type->getTypeSet()[0]->genericArrayElementType());
    }

    public function testIntersectionTypeInShapeFallback(): void
    {
        $type = self::makePHPDocUnionType('array{keyName:MyClass&MyInterfaceUTT,other:array}');
        $this->assertSame(1, $type->typeCount());
        $this->assertSame('array{keyName:\MyClass&\MyInterfaceUTT,other:array}', (string)$type);
        $array_shape_type = $type->getTypeSet()[0];
        $this->assertInstanceof(ArrayShapeType::class, $array_shape_type);
        $this->assertSame(2, \count($array_shape_type->getFieldTypes()));
        $this->assertInstanceOf(IntersectionType::class, $array_shape_type->getFieldTypes()['keyName']->getTypeSet()[0]);
    }

    public function testIntersectionTypeCasting(): void
    {
        $code_base = self::$code_base;
        $intersection1 = self::makePHPDocUnionType('Countable&ArrayAccess');
        $intersection2 = self::makePHPDocUnionType('\ArrayAccess&\Countable');
        $this->assertSame(1, $intersection1->typeCount());
        $this->assertSame('\ArrayAccess|\Countable|\Countable&\ArrayAccess', $intersection1->asExpandedTypes($code_base)->__toString());

        $this->assertTrue($intersection1->canCastToUnionType($intersection2, $code_base), 'expected intersection type to cast to itself');
        $this->assertTrue($intersection1->hasSubtypeOf($intersection2, $code_base), 'expect hasSubtypeOf to be true for intersection type');
        $this->assertTrue($intersection1->canStrictCastToUnionType($code_base, $intersection2), 'expected intersection type to strictly cast to itself');

        $countable = self::makePHPDocUnionType('Countable');
        $this->assertTrue($intersection1->canCastToUnionType($countable, $code_base), 'expected intersection type to cast to component');
        $this->assertTrue($intersection1->hasSubtypeOf($countable, $code_base), 'expect hasSubtypeOf to be true for component type');
        $this->assertTrue($intersection1->canStrictCastToUnionType($code_base, $countable), 'expected intersection type to strictly cast to component');

        $traversable = self::makePHPDocUnionType('Traversable');
        $this->assertFalse($intersection1->canCastToUnionType($traversable, $code_base), 'expected intersection type not to cast to non-component');
        $this->assertFalse($intersection1->hasSubtypeOf($traversable, $code_base), 'expect hasSubtypeOf to be false for unrelated type');
        $this->assertFalse($intersection1->canStrictCastToUnionType($code_base, $traversable));

        $intersection_narrowed = self::makePHPDocUnionType('\ArrayAccess&\Countable&\Traversable');
        $this->assertTrue($intersection_narrowed->canCastToUnionType($intersection1, $code_base));
        $this->assertFalse($intersection1->canCastToUnionType($intersection_narrowed, $code_base));
    }

    /** @return list<list> */
    public function isStrictSubtypeOfProvider(): array
    {
        return [
            [false, "'literal'", '?int'],
            [false, 'int|string', 'int'],
            [true, "'literal'", '?string'],
            [true, "'literal'", 'mixed'],
            [true, 'ArrayObject', 'ArrayAccess'],
            [true, 'ArrayObject', 'ArrayAccess&Countable'],
            [false, 'ArrayAccess&Countable', 'ArrayObject'],
            [true, 'ArrayObject', 'Countable'],
            [true, 'ArrayObject', 'object'],
            [false, 'ArrayAccess', 'ArrayObject'],
        ];
    }

    /**
     * @dataProvider isStrictSubtypeOfProvider
     */
    public function testIsStrictSubtypeOf(bool $expected, string $from_type_string, string $to_type_string): void
    {
        $from_type = self::makePHPDocUnionType($from_type_string);
        $to_type = self::makePHPDocUnionType($to_type_string);
        $this->assertSame($expected, $from_type->isStrictSubtypeOf(self::$code_base, $to_type), "unexpected isStrictSubtypeOf result for $from_type_string to $to_type_string");
    }

}
