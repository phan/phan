<?php declare(strict_types=1);
namespace Phan\Tests;

global $internal_class_name_list;
global $internal_interface_name_list;
global $internal_trait_name_list;
global $internal_function_name_list;

$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use Phan\CodeBase;
use PHPUnit_Framework_Test;

/**
 * @suppress PhanUnreferencedClass
 * This class is referenced in phpunit.xml
 */
class PhanTestListener
    extends \PHPUnit_Framework_BaseTestListener
{
    public function startTest(PHPUnit_Framework_Test $test) {
        if ($test instanceof CodeBaseAwareTestInterface) {

            // We're holding a static reference to the
            // CodeBase because its pretty slow to build. To
            // avoid state moving from test to test, we clone
            // the CodeBase for each test to avoid changing
            // the one we're building here.
            static $code_base = null;
            if (!$code_base) {
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
            }

            $test->setCodeBase($code_base->shallowClone());
        }
    }

    public function endTest(PHPUnit_Framework_Test $test, $time) {
        if ($test instanceof CodeBaseAwareTestInterface) {
            $test->setCodeBase(null);
        }
    }
}
