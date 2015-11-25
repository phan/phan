<?php declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use \Phan\Phan;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Language\FQSEN\FullyQualifiedClassName;

class AnalyzerTest extends \PHPUnit_Framework_TestCase {

    private $class_name_list;
    private $interface_name_list;
    private $trait_name_list;
    private $function_name_list;

    /**
     * @var CodeBase
     */
    private $code_base;

    protected function setUp() {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;
        $this->class_name_list = $internal_class_name_list;
        $this->interface_name_list = $internal_interface_name_list;
        $this->trait_name_list = $internal_trait_name_list;
        $this->function_name_list = $internal_function_name_list;


        $this->code_base =
            $code_base = new CodeBase(
                [], // $this->class_name_list,
                [], // $this->interface_name_list,
                [], // $this->trait_name_list,
                []  // $this->function_name_list
            );


    }

    public function tearDown() {
    }

    public function testClassInCodeBase() {


        $context =
            $this->contextForCode("
                Class A {}
            ");

        $this->assertTrue(
            $this->code_base->hasClassWithFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString('A')
            )
        );
    }

    public function testNamespaceClassInCodeBase() {
        $context =
            $this->contextForCode("
                namespace A;
                Class B {}
            ");

        $this->assertTrue(
            $this->code_base->hasClassWithFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString('\A\b')
            )
        );
    }

    public function testMethodInCodeBase() {
        $context =
            $this->contextForCode("
                namespace A;
                Class B {
                    public function c() {
                        return 42;
                    }
                }
            ");

        $class_fqsen =
            FullyQualifiedClassName::fromFullyQualifiedString('\A\b');

        $this->assertTrue(
            $this->code_base->hasClassWithFQSEN($class_fqsen),
            "Class with FQSEN $class_fqsen not found"
        );

        $clazz =
            $this->code_base->getClassByFQSEN($class_fqsen);

        $this->assertTrue(
            $clazz->hasMethodWithName($this->code_base, 'c'),
            "Method with FQSEN not found"
        );
    }

    /**
     * Get a Context after parsing the given
     * bit of code.
     */
    private function contextForCode(
        string $code_stub
    ) : Context {

        return
            (new Phan)->parseNodeInContext(
                \ast\parse_code(
                    '<?php ' . $code_stub,
                    Config::get()->ast_version
                ),
                new Context,
                $this->code_base
            );
    }
}
