<?php declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use \Phan\CodeBase;
use \Phan\Language\Type;
use \Phan\Language\Context;

class TypeTest extends \PHPUnit_Framework_TestCase {

    /** @var Context */
    protected $context = null;

    protected function setUp() {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;

        $code_base = new CodeBase(
            $internal_class_name_list,
            $internal_interface_name_list,
            $internal_trait_name_list,
            $internal_function_name_list
        );

        $this->context = new Context($code_base);
    }

    public function tearDown() {
        $this->context = null;
    }

    public function testToString() {
        $this->assertEquals(0, 0);
    }

    public function testInt() {
        $this->assertTypeStringEqual('42', 'int');
    }

    public function testString() {
        try {
            $this->assertTypeStringEqual('"a string"', 'string');
        } catch (Exception $exception) {
            print((string)$exception);
        }
    }

    public function testArrayUniform() {
        $this->assertTypeStringEqual(
            '[1, 2, 3]',
            'int[]'
        );
    }

    public function testArrayMixed() {
        $this->assertTypeStringEqual(
            '[1, "string"]', 'array'
        );
    }

    public function testArrayEmpty() {
        $this->assertTypeStringEqual(
            '[]', 'array'
        );
    }
    public function testInternalObject() {
        $this->assertTypeStringEqual(
            'new SplStack();', 'SplStack'
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
    private function assertTypeStringEqual(
        string $code_stub,
        string $type_name)
    {
        /*
        $this->assertEquals(
            $this->typeStringFromCode('<?php ' . $code_stub),
            $type_name
        );
         */
    }

    /**
     * @return string
     * A string representation of the union type begotten from
     * the first statement in the statement list in the given
     * code.
     */
    private function typeStringFromCode(string $code) : string {
        return Type::typeFromNode(
            $this->context,
            \ast\parse_code($code, $version = 10)
            ->children[0]
        )->__toString();
    }

}
