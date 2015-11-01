<?php declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;

class ContextTest extends \PHPUnit_Framework_TestCase {

    /** @var CodeBase */
    protected $code_base = null;

    protected function setUp() {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;

        $this->code_base = new CodeBase(
            $internal_class_name_list,
            $internal_interface_name_list,
            $internal_trait_name_list,
            $internal_function_name_list
        );
    }

    public function tearDown() {
        $this->code_base = null;
    }

    public function testSimple() {
        $context = new Context($this->code_base);

        $context_namespace =
            $context->withNamespace('\A');

        $context_class =
            $context_namespace->withClassFQSEN(
                new FQSEN([], '\A', 'B')
            );

        $context_method =
            $context_namespace->withMethodFQSEN(
                new FQSEN([], '\A', 'B', 'c')
            );

        $this->assertTrue(!empty($context));
        $this->assertTrue(!empty($context_namespace));
        $this->assertTrue(!empty($context_class));
        $this->assertTrue(!empty($context_method));
    }

}
