<?php declare(strict_types=1);
namespace Phan;

use \Phan\CodeBase\File;
use \Phan\Language\Context;
use \Phan\Language\Element\FunctionFactory;
use \Phan\Language\Element\{Clazz, Element, Method};
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\UnionType;

/**
 * A CodeBase represents the known state of a code base
 * we're analyzing.
 *
 * In order to understand internal classes, interfaces,
 * traits and functions, a CodeBase needs to be
 * initialized with the list of those elements begotten
 * before any classes are loaded.
 *
 * # Example
 * ```
 * // Grab these before we define our own classes
 * $internal_class_name_list = get_declared_classes();
 * $internal_interface_name_list = get_declared_interfaces();
 * $internal_trait_name_list = get_declared_traits();
 * $internal_function_name_list = get_defined_functions()['internal'];
 *
 * // Load any required code ...
 *
 * $code_base = new CodeBase(
 *     $internal_class_name_list,
 *     $internal_interface_name_list,
 *     $internal_trait_name_list,
 *     $internal_function_name_list
 *  );
 *
 *  // Do stuff ...
 * ```
 */
class CodeBase {
    use \Phan\CodeBase\ClassMap;
    use \Phan\CodeBase\MethodMap;
    use \Phan\CodeBase\ConstantMap;
    use \Phan\CodeBase\PropertyMap;
    use \Phan\CodeBase\GlobalVariableMap;
    use \Phan\CodeBase\FileMap;

    public function __construct(
        array $internal_class_name_list,
        array $internal_interface_name_list,
        array $internal_trait_name_list,
        array $internal_function_name_list
    ) {
        $this->constructClassMap();

        $this->addClassesByNames($internal_class_name_list);
        $this->addClassesByNames($internal_interface_name_list);
        $this->addClassesByNames($internal_trait_name_list);
        $this->addFunctionsByNames($internal_function_name_list);
    }

    public function __clone() {
        $this->class_map = clone($this->class_map);
    }

    /**
     * @param string[] $function_name_list
     * A list of function names to load type information for
     */
    private function addFunctionsByNames(array $function_name_list) {
        foreach ($function_name_list as $i => $function_name) {
            foreach (FunctionFactory::functionListFromName($this, $function_name) as $method) {
                $this->addMethod($method);
            }
        }
    }

    public function store() {
        if (!Database::isEnabled()) {
            return;
        }

        $this->storeClassMap();
        $this->storeMethodMap();
        $this->storeConstantMap();
        $this->storePropertyMap();
        $this->storeFileMap();
    }
}
