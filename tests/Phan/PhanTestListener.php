<?php
/**
 * User: scaytrase
 * Created: 2016-01-16 11:30
 */

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

class PhanTestListener extends \PHPUnit_Framework_BaseTestListener
{
    public function startTest(PHPUnit_Framework_Test $test)
    {
        if ($test instanceof CodeBaseAwareTestInterface) {

            global $internal_class_name_list;
            global $internal_interface_name_list;
            global $internal_trait_name_list;
            global $internal_function_name_list;

            $codebase = new CodeBase(
                $internal_class_name_list,
                $internal_interface_name_list,
                $internal_trait_name_list,
                $internal_function_name_list
            );

            $test->setCodeBase($codebase);
        }
    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        if ($test instanceof CodeBaseAwareTestInterface) {
            $test->setCodeBase(null);
        }
    }
}