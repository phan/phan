<?php declare(strict_types=1);

namespace Phan\Tests\Language;

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

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_const_name_list = array_keys(array_merge(...array_values(
    array_diff_key(get_defined_constants(true), ['user' => []])
)));
$internal_function_name_list = get_defined_functions()['internal'];

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\Tests\BaseTest;

class UnionTypeTest extends BaseTest {

    /** @var Context|null */
    protected $context = null;

    /** @var CodeBase */
    protected $code_base = null;

    protected function setUp() {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_const_name_list;
        global $internal_function_name_list;

        $this->code_base = new CodeBase(
            $internal_class_name_list,
            $internal_interface_name_list,
            $internal_trait_name_list,
            $internal_const_name_list,
            $internal_function_name_list
        );

        $this->context = new Context;
    }

    protected function tearDown() {
        $this->context = null;
    }

    public function testInt() {
        $this->assertUnionTypeStringEqual('42', 'int');
    }

    public function testString() {
        try {
            $this->assertUnionTypeStringEqual(
                '"a string"',
                'string'
            );
        } catch (\Exception $exception) {
            print((string)$exception);
        }
    }

    public function testArrayUniform() {
        $this->assertUnionTypeStringEqual(
            '[1, 2, 3]',
            'int[]'
        );
    }

    public function testArrayMixed() {
        $this->assertUnionTypeStringEqual(
            '[1, "string"]', 'array'
        );
    }

    public function testArrayEmpty() {
        $this->assertUnionTypeStringEqual(
            '[]', 'array'
        );
    }
    public function testInternalObject() {
        $this->assertUnionTypeStringEqual(
            'new SplStack();',
            '\\ArrayAccess|\\Countable|\\Iterator|\\Serializable|\\SplDoublyLinkedList|\\SplStack|\\Traversable|iterable'
        );
    }

    public function testGenericArrayType() {
        $type = GenericArrayType::fromElementType(
            GenericArrayType::fromElementType(
                IntType::instance(false), false
            ), false
        );

        $this->assertEquals(
            $type->genericArrayElementType()->__toString(),
            "int[]"
        );
    }

    public function testGenericArrayTypeFromString() {
        $type = Type::fromFullyQualifiedString("int[][]");

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
        string $type_name)
    {
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
     */
    private function typeStringFromCode(string $code) : string {
        return UnionType::fromNode(
            $this->context,
            $this->code_base,
            \ast\parse_code(
                $code,
                Config::AST_VERSION
            )->children[0]
        )->asExpandedTypes($this->code_base)->__toString();
    }

    private static function makePHPDocUnionType(string $union_type_string) : UnionType
    {
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

        $this->assertSame('\\', $type->getNamespace());
        $this->assertSame('TypeTestClass', $type->getName());
        $parts = $type->getTemplateParameterTypeList();
        $this->assertCount(2, $parts);
        $this->assertTrue($parts[0]->isType(self::makePHPDocType('A1')));
        $this->assertTrue($parts[1]->isType(self::makePHPDocType('B2')));
    }
}
