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

    /** @var CodeBase */
    private $code_base = null;

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

    /**
     */
    public function testCodeBase() {
        $context = new Context($this->code_base);

        (new Analyzer)->parseNode(
            $context,
            \ast\parse_code(
                '<?php Class A {}',
                Configuration::instance()->ast_version
            )
        );

        $this->assertTrue(
            $this->code_base->hasClassWithFQSEN(
                FQSEN::fromContextAndString($context, 'A')
            )
        );


    }
}
