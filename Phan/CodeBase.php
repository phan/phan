<?php
declare(strict_types=1);
namespace Phan;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\Element\{Clazz, Element, Method};

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

    private $class_map = [];
    private $method_map = [];
    private $namespace_map = [];
    private $summary = [];

    public function __construct(
        array $internal_class_name_list,
        array $internal_interface_name_list,
        array $internal_trait_name_list,
        array $internal_function_name_list
    ) {
        $this->addClassesByNames($internal_class_name_list);
        $this->addClassesByNames($internal_interface_name_list);
        $this->addClassesByNames($internal_trait_name_list);
        $this->addFunctionsByNames($internal_function_name_list);

        $this->summary = [
            'conditionals' => 0,
            'classes' => 0,
            'traits' => 0,
            'methods' => 0,
            'functions' => 0,
            'closures' => 0,
        ];
    }

    public function addClass(Clazz $class_element) {
        $this->class_map[$class_element->getFQSEN()->__toString()]
            = $class_element;
    }

    /**
     * @return Clazz
     */
    public function getClassByFQSEN(FQSEN $fqsen) : Clazz {
        return $this->class_map[$fqsen->__toString()];
    }

    /**
     * @return bool
     * True if the exlass exists else false
     */
    public function hasClassWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->class_map[$fqsen->__toString()]);
    }

    /**
     * @param Method $method
     * A method to add to the code base
     */
    public function addMethod(Method $method) {
        $this->method_map[$method->getFQSEN()->__tostring()] =
            $method;
    }

    /**
     * @return bool
     * True if a method exists with the given FQSEN
     */
    public function hasMethodWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->method_map[(string)$fqsen]);
    }

    /**
     *
     */
    private function addClassesByNames(array $class_name_list) {
        foreach ($class_name_list as $i => $class_name) {
            $clazz = Clazz::fromClassName($this, $class_name);
            $this->class_map[$clazz->getFQSEN()->__toString()] =
                $clazz;
        }
    }

    /**
     *
     */
    private function addFunctionsByNames($function_name_list) {
        foreach ($function_name_list as $i => $function_name) {
            $method = Method::fromFunctionName($this, $function_name);
            $this->method_map[$function_name] = $method;
        }
    }

    /**
     *
     */
    public function incrementConditionals() {
        ++$this->summary['conditionals'];
    }

    public function incrementClasses() {
        ++$this->summary['classes'];
    }

    public function incrementTraits() {
        ++$this->summary['traits'];
    }

    public function incrementMethods() {
        ++$this->summary['methods'];
    }

    public function incrementFunctions() {
        ++$this->summary['functions'];
    }

    public function incrementClosures() {
        ++$this->summary['closures'];
    }

}
