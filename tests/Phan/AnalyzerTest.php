<?php declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use \Phan\Analyzer;
use \Phan\CodeBase;
use \Phan\Configuration;
use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Language\FQSEN;

class AnalyzerTest extends \PHPUnit_Framework_TestCase {

    private $class_name_list;
    private $interface_name_list;
    private $trait_name_list;
    private $function_name_list;

    protected function setUp() {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;
        $this->class_name_list = $internal_class_name_list;
        $this->interface_name_list = $internal_interface_name_list;
        $this->trait_name_list = $internal_trait_name_list;
        $this->function_name_list = $internal_function_name_list;
    }

    public function tearDown() {
    }

    public function testClassInCodeBase() {
        $context =
            $this->contextForCode("
                Class A {}
            ");

        $this->assertTrue(
            $context->getCodeBase()->hasClassWithFQSEN(
                FQSEN::fromFullyQualifiedString('A')
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
            $context->getCodeBase()->hasClassWithFQSEN(
                FQSEN::fromFullyQualifiedString('\A\b')
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

        $class_fqsen = FQSEN::fromFullyQualifiedString('\A\b');

        $this->assertTrue(
            $context->getCodeBase()->hasClassWithFQSEN($class_fqsen)
        );

        $clazz =
            $context->getCodeBase()->getClassByFQSEN($class_fqsen);

        $this->assertTrue(
            $clazz->hasMethodWithFQSEN(
                FQSEN::fromFullyQualifiedString('\A\b::c')
            )
        );
    }

    /**
     * Get a Context after parsing the given
     * bit of code.
     */
    private function contextForCode(
        string $code_stub
    ) : Context {

        $code_base = new CodeBase(
            [], // $this->class_name_list,
            [], // $this->interface_name_list,
            [], // $this->trait_name_list,
            []  // $this->function_name_list
        );

        return
            (new Analyzer)->parseAndGetContextForNodeInContext(
                \ast\parse_code(
                    '<?php ' . $code_stub,
                    Configuration::instance()->ast_version
                ),
                new Context($code_base)
            );
    }
}
