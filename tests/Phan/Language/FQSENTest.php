<?php declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use \Phan\CodeBase;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;

class FQSENTest extends \PHPUnit_Framework_TestCase {

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

    public function testSimple() {
        $this->assertFQSENEqual(
            new FQSEN([], '', 'A'),
            '\a'
        );
    }

    public function testNamespace() {
        $this->assertFQSENEqual(
            new FQSEN([], '\A', 'B'),
            '\A\b'
        );
    }

    public function testMethod() {
        $this->assertFQSENEqual(
            new FQSEN([], '\A', 'B', 'C'),
            '\A\b::C'
        );
    }

    public function testFromContext() {
        $this->assertFQSENEqual(
            FQSEN::fromContext(
                $this->context->withClassFQSEN(new FQSEN([], '\A', 'B'))
            ),
            '\A\b'
        );
    }

    /**
     * Asserts that a given FQSEN produces the given string
     */
    public function assertFQSENEqual(
        FQSEN $fqsen,
        string $string
    ) {
        $this->assertEquals($string, (string)$fqsen);
    }

}
