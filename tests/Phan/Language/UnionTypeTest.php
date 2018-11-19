<?php declare(strict_types=1);

namespace Phan\Tests\Language;

use AssertionError;
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
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

/**
 * Unit tests of the many methods of UnionType
 */
final class UnionTypeTest extends BaseTest
{
    /** @var CodeBase The code base within which this unit test is operating */
    protected static $code_base = null;

    /**
     * Based on BaseTest
     * TODO: Investigate instantiating CodeBase in a cheaper way (lazily?)
     * @suppress PhanReadOnlyProtectedProperty read by phpunit framework
     */
    // phpcs:ignore
    protected $backupStaticAttributesBlacklist = [
        'Phan\AST\PhanAnnotationAdder' => [
            'closures_for_kind',
        ],
        'Phan\Language\Type' => [
            'canonical_object_map',
            'internal_fn_cache',
        ],
        'Phan\Language\Type\LiteralIntType' => [
            'nullable_int_type',
            'non_nullable_int_type',
        ],
        'Phan\Language\Type\LiteralStringType' => [
            'nullable_int_type',
            'non_nullable_int_type',
        ],
        'Phan\Language\UnionType' => [
            'empty_instance',
        ],
        // Back this up because it takes 306 ms.
        'Phan\Tests\Language\UnionTypeTest' => [
            'code_base',
        ],
    ];

    protected function setUp()
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

    public static function tearDownAfterClass()
    {
        // @phan-suppress-next-line PhanTypeMismatchProperty
        self::$code_base = null;
    }

    public function testInt()
    {
        Phan::setIssueCollector(new BufferingCollector());
        $this->assertUnionTypeStringEqual('rand(0,20)', 'int');
        $this->assertUnionTypeStringEqual('rand(0,20) + 1', 'int');
        // TODO: Perform arithmetic if in bounds
        $this->assertUnionTypeStringEqual('42 + 2', '44');
        $this->assertUnionTypeStringEqual('46 - 2', '44');
        $this->assertUnionTypeStringEqual('PHP_INT_MAX', (string)PHP_INT_MAX);
        $this->assertUnionTypeStringEqual('PHP_INT_MAX + PHP_INT_MAX', 'float');
        $this->assertUnionTypeStringEqual('2 ** -9999999', 'float');
        $this->assertUnionTypeStringEqual('2 ** 9999999', 'float');
        $this->assertUnionTypeStringEqual('0 ** 0', '1');
        $this->assertUnionTypeStringEqual('1 << 2.3', 'int');
        $this->assertUnionTypeStringEqual('1 | 1', '1');
        $this->assertUnionTypeStringEqual('1 | 2', '3');
        $this->assertUnionTypeStringEqual('4 >> 1', 'int');
        $this->assertUnionTypeStringEqual('4 >> 1.2', 'int');
        $this->assertUnionTypeStringEqual('1 << rand(0,20)', 'int');
        $this->assertUnionTypeStringEqual('-42', '-42');
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
        $this->assertUnionTypeStringEqual('-constant($argv[0])', 'float|int');
        $this->assertUnionTypeStringEqual('-(1.5)', 'float');
        $this->assertUnionTypeStringEqual('(rand(0,1) ? "12" : 2.5) - (rand(0,1) ? "3" : 1.5)', 'float|int');
        $this->assertUnionTypeStringEqual('(rand(0,1) ? "12" : 2.5) * (rand(0,1) ? "3" : 1.5)', 'float|int');
        $this->assertUnionTypeStringEqual('(rand(0,1) ? "12" : 2.5) + (rand(0,1) ? "3" : 1.5)', 'float|int');
    }

    public function testComplex()
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
        $this->assertUnionTypeStringEqual('$x=2; $x += 3; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=2.5; $x += 3; $x', 'float');
        $this->assertUnionTypeStringEqual('$x=2; $x += 3.5; $x', 'float');
        $this->assertUnionTypeStringEqual('$x=2; $x += (rand()%2) ? 3.5 : 2; $x', 'float|int');
        $this->assertUnionTypeStringEqual('$x=2; $x -= 3; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=2; $x -= 3.5; $x', 'float');
        $this->assertUnionTypeStringEqual('$x=2; $x *= 3.5; $x', 'float');
        $this->assertUnionTypeStringEqual('$x=2; $x *= 3; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=2; $x **= 3; $x', 'int');
        $this->assertUnionTypeStringEqual('$x=2; $x **= 3.5; $x', 'float');
        $this->assertUnionTypeStringEqual('$x=5; $x %= 3; $x', 'int');  // This casts to float
        $this->assertUnionTypeStringEqual('$x=21.2; $x %= 3.5; $x', 'int');  // This casts to float
        $this->assertUnionTypeStringEqual('$x=5;    $x ^= 3;    $x', 'int');
        $this->assertUnionTypeStringEqual('$x="ab"; $x ^= "ac"; $x', 'string');
        $this->assertUnionTypeStringEqual('$x=5;    $x |= 3;    $x', 'int');
        $this->assertUnionTypeStringEqual('$x="ab"; $x |= "ac"; $x', 'string');
        // `&=` is a bitwise and, not to be confused with `=&`
        $this->assertUnionTypeStringEqual('$x=5;    $x &= 3;    $x', 'int');
        $this->assertUnionTypeStringEqual('$x="ab"; $x &= "ac"; $x', 'string');

        // TODO: Implement more code to warn about invalid operands.
        // Evaluating this should result in '0'
        $this->assertUnionTypeStringEqual('$x=(new stdClass()); $x &= 2; $x', 'int');
        $this->assertUnionTypeStringEqual('$x = stdClass::class; new $x();', '\stdClass');

        // !is_numeric removes integers from the type
        $this->assertUnionTypeStringEqual('$x = rand() ? "a string" : 1; assert(!is_numeric($x)); $x', "'a string'");
        $this->assertUnionTypeStringEqual('$x = rand() ? 2.4 : new stdClass(); assert(!is_numeric($x)); $x', '\stdClass');
        $this->assertUnionTypeStringEqual('$x = rand() ? $argv[0] : $argc; assert(!is_numeric($x)); $x', 'string');
    }

    public function testString()
    {
        try {
            $this->assertUnionTypeStringEqual(
                '"a string"',
                "'a string'"
            );
        } catch (\Exception $exception) {
            print((string)$exception);
        }
    }

    public function testArrayUniform()
    {
        $this->assertUnionTypeStringEqual(
            '[false => \'$string\']',
            "array{0:'\$string'}"
        );
    }

    public function testArrayUniformMultipleValuesLiteral()
    {
        $this->assertUnionTypeStringEqual(
            '[false => rand(0,1) ? zend_version() : 2]',
            "array{0:2|string}"
        );
    }

    public function testArrayUniformMultipleValues()
    {
        $this->assertUnionTypeStringEqual(
            '[false => rand(0,1) ? zend_version() : 2]',
            'array{0:2|string}'
        );
    }

    public function testArrayMixed()
    {
        $this->assertUnionTypeStringEqual(
            '[1, zend_version()]',
            'array{0:1,1:string}'
        );
    }

    public function testArrayEmpty()
    {
        $this->assertUnionTypeStringEqual(
            '[]',
            'array{}'
        );
    }
    public function testInternalObject()
    {
        $this->assertUnionTypeStringEqual(
            'new SplStack();',
            '\\ArrayAccess|\\Countable|\\Iterator|\\Serializable|\\SplDoublyLinkedList|\\SplStack|\\Traversable|iterable'
        );
    }

    public function testGenericArrayType()
    {
        $type = self::createGenericArrayTypeWithMixedKey(
            self::createGenericArrayTypeWithMixedKey(
                IntType::instance(false),
                false
            ),
            false
        );

        $this->assertEquals(
            $type->genericArrayElementType()->__toString(),
            "int[]"
        );
        $this->assertEquals(
            $type->genericArrayElementUnionType()->__toString(),
            "int[]"
        );
    }

    public function testGenericArrayTypeFromString()
    {
        $type = Type::fromFullyQualifiedString("int[][]");
        if (!($type instanceof GenericArrayType)) {
            throw new AssertionError("Expected $type to be GenericArrayType");
        }

        $this->assertEquals(
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
    ) {
        $this->assertEquals(
            $type_name,
            $this->typeStringFromCode('<' . '?php ' . $code_stub . ';')
        );
    }

    /**
     * @return string
     * A string representation of the union type begotten from
     * the first statement in the statement list in the given
     * code.
     * @suppress PhanPartialTypeMismatchArgument
     */
    private function typeStringFromCode(string $code) : string
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

    const VALID_UNION_TYPE_REGEX = '(^(' . UnionType::union_type_regex_or_this . ')$)';

    private static function makePHPDocUnionType(string $union_type_string) : UnionType
    {
        self::assertTrue(preg_match(self::VALID_UNION_TYPE_REGEX, $union_type_string) > 0, "$union_type_string should be parsed by regex");
        return UnionType::fromStringInContext($union_type_string, new Context(), Type::FROM_PHPDOC);
    }

    private function makePHPDocType(string $type) : Type
    {
        return Type::fromStringInContext($type, new Context(), Type::FROM_PHPDOC);
    }

    private function assertIsType(Type $type, string $union_type_string)
    {
        $union_type = self::makePHPDocUnionType($union_type_string);
        $this->assertTrue($union_type->hasType($type), "Expected $union_type (from $union_type_string) to be $type");
    }

    public function testExpandedTypes()
    {
        $this->assertSame(
            '\Exception[]|\Throwable[]',
            UnionType::fromFullyQualifiedString('\Exception[]')->asExpandedTypes(self::$code_base)->__toString()
        );
        $this->assertSame(
            'array<int,\Exception>|array<int,\Throwable>',
            UnionType::fromFullyQualifiedString('array<int,\Exception>')->asExpandedTypes(self::$code_base)->__toString()
        );
    }

    public function testBasicTypes()
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

    public function testTemplateTypes()
    {
        $union_type = self::makePHPDocUnionType('TypeTestClass<A1,B2>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);
        if (!($type instanceof Type)) {
            throw new AssertionError("Should be Type");
        }

        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('A1')));
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('B2')));
    }

    public function testTemplateNullableTypes()
    {
        $this->assertRegExp('/^' . Type::type_regex . '$/', 'TypeTestClass<A1|null,B2|null>', 'type_regex does not support nested pipes');
        $this->assertRegExp('/^' . Type::type_regex_or_this . '$/', 'TypeTestClass<A1|null>', 'type_regex_or_this does not support nested pipes');
        $this->assertRegExp('/^' . Type::type_regex_or_this . '$/', 'TypeTestClass<A1|null,B2|null>', 'type_regex_or_this does not support nested pipes');
        $union_type = self::makePHPDocUnionType('TypeTestClass<A1,B2|null>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);
        if (!($type instanceof Type)) {
            throw new AssertionError("Should be Type");
        }

        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('A1')));
        $this->assertTrue($parts[1]->isEqualTo(self::makePHPDocUnionType('B2|null')));
    }

    public function testNormalize()
    {
        $union_type = self::makePHPDocUnionType('object|null');
        $this->assertSame(2, $union_type->typeCount());

        $new_union_type = $union_type->asNormalizedTypes();
        $this->assertSame('?object', (string)$new_union_type);
        $type_set = $new_union_type->getTypeSet();
        $this->assertSame(ObjectType::instance(true), reset($type_set));
    }

    public function testAlternateArrayTypes()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('array<int,string>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('array<int,string>', (string)$type);
        $expected_type = GenericArrayType::fromElementType(StringType::instance(false), false, GenericArrayType::KEY_INT);
        $this->assertSame($expected_type, $type);
    }

    public function testAlternateArrayTypesNullable()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('array<string,?int>');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('array<string,?int>', (string)$type);
        $expected_type = GenericArrayType::fromElementType(IntType::instance(true), false, GenericArrayType::KEY_STRING);
        $this->assertSame($expected_type, $type);
    }

    public function testNestedArrayTypes()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('array<int|string>');
        $this->assertSame('int[]|string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());

        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('(int|string)[]');
        $this->assertSame('int[]|string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());

        $union_type = self::makePHPDocUnionType('((int)|(string))[]');
        $this->assertSame('int[]|string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $array_type = function (Type $type) : GenericArrayType {
            return self::createGenericArrayTypeWithMixedKey($type, false);
        };

        $this->assertSame($array_type(IntType::instance(false)), $type);

        $union_type = self::makePHPDocUnionType('array<bool|array<array<int|string>>>');
        $this->assertSame('bool[]|int[][][]|string[][][]', (string)$union_type);
        $this->assertSame(3, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame($array_type(BoolType::instance(false)), $type);
        $type = next($types);
        $this->assertSame($array_type($array_type($array_type(IntType::instance(false)))), $type);
        $type = next($types);
        $this->assertSame($array_type($array_type($array_type(StringType::instance(false)))), $type);
    }

    /**
     * Assert values that should not be matched by the regular expression for a valid union type.
     * This regular expression controls what can get passed to UnionType::from...()
     *
     * @dataProvider unparseableUnionTypeProvider
     */
    public function testUnparseableUnionType(string $type)
    {
        $this->assertNotRegExp(self::VALID_UNION_TYPE_REGEX, $type, "'$type' should be unparseable");
    }

    public function unparseableUnionTypeProvider() : array
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

    public function testComplexUnionType()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('(int|string)|Closure():(int|stdClass)');
        $this->assertSame('Closure():(\stdClass|int)|int|string', (string)$union_type);
        $this->assertSame(3, $union_type->typeCount());
    }

    public function testNullableBasicArrayType()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('?(int|string)[]');
        $this->assertSame('?int[]|?string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());
        $union_type = self::makePHPDocUnionType('?((int|string))[]');
        $this->assertSame('?int[]|?string[]', (string)$union_type);
        $this->assertSame(2, $union_type->typeCount());
    }

    public function testNullableArrayType()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('?string[]');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('?string[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(false), true), $type);
    }

    public function testNullableBracketedArrayType()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('(?string)[]');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('(?string)[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(true), false), $type);
    }

    public function testNullableBracketedArrayType2()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('(?string)[]|(int)[]');
        $this->assertSame(2, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        list($type1, $type2) = \array_values($types);

        $this->assertSame('(?string)[]', (string)$type1);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(true), false), $type1);

        $this->assertSame('int[]', (string)$type2);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(IntType::instance(false), false), $type2);
    }

    public function testNullableBracketedArrayType3()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('?(string[])|?(int[])');
        $this->assertSame(2, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        list($type1, $type2) = \array_values($types);

        $this->assertSame('?string[]', (string)$type1);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(false), true), $type1);

        $this->assertSame('?int[]', (string)$type2);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(IntType::instance(false), true), $type2);
    }

    public function testNullableArrayOfNullables()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('?(?string)[]');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('?(?string)[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(true), true), $type);
    }

    public function testNullableExtraBracket()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('?(string[])');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('?string[]', (string)$type);
        $this->assertSame(self::createGenericArrayTypeWithMixedKey(StringType::instance(false), true), $type);
    }

    public function testUnionInArrayShape()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('array{key:int|string[]}');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('array{key:int|string[]}', (string)$type);
        $this->assertSame('array<string,int>|array<string,string[]>', (string)$union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        if (!($type instanceof ArrayShapeType)) {
            throw new AssertionError("Expected $type to be ArrayShapeType");
        }
        $field_union_type = $type->getFieldTypes()['key'];
        $this->assertFalse($field_union_type->getIsPossiblyUndefined());
    }

    public function testOptionalInArrayShape()
    {
        // array keys are integers, values are strings
        $union_type = self::makePHPDocUnionType('array{key:int|string=}');
        $this->assertSame(1, $union_type->typeCount());
        $types = $union_type->getTypeSet();
        $type = reset($types);

        $this->assertSame('array{key?:int|string}', (string)$type);
        if (!($type instanceof ArrayShapeType)) {
            throw new AssertionError("Expected $type to be an ArrayShapeType");
        }
        $this->assertSame('array<string,int>|array<string,string>', (string)$union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
        $field_union_type = $type->getFieldTypes()['key'];
        $this->assertTrue($field_union_type->getIsPossiblyUndefined());
        $this->assertSame('int|string=', (string)$field_union_type);
        $this->assertSame([IntType::instance(false), StringType::instance(false)], $field_union_type->getTypeSet());
    }

    private static function createGenericArrayTypeWithMixedKey(Type $type, bool $is_nullable) : GenericArrayType
    {
        return GenericArrayType::fromElementType($type, $is_nullable, GenericArrayType::KEY_MIXED);
    }
}
