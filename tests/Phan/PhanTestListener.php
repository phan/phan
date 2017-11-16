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
use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

/**
 * @suppress PhanUnreferencedClass
 * This class is referenced in phpunit.xml
 */
class PhanTestListener extends BaseTestListener
{
    public function startTest(Test $test)
    {
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
                    CodeBase::getPHPInternalConstantNameList(),  // Get everything except user-defined constants
                    $internal_function_name_list
                );
            }

            $test->setCodeBase($code_base->shallowClone());
        }
    }

    /**
     * @param $time @phan-unused-param
     */
    public function endTest(Test $test, $time)
    {
        if ($test instanceof CodeBaseAwareTestInterface) {
            $test->setCodeBase(null);
        }
    }
}
