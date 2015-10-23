<?php
declare(strict_types=1);
namespace phan;

require_once(__DIR__.'/language/Context.php');
require_once(__DIR__.'/language/element/Method.php');
require_once(__DIR__.'/language/element/Comment.php');
require_once(__DIR__.'/language/element/Clazz.php');

use \phan\language\Context;
use \phan\language\element\Clazz;
use \phan\language\element\Comment;
use \phan\language\element\Method;

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

    public function addMethod(Method $method_element) {
        $this->method_map[$method_element->getFQSEN()->__tostring()] =
            $method_element;
    }

    /**
     *
     */
    private function addClassesByNames(array $class_name_list) {
        foreach ($class_name_list as $i => $class_name) {
            $this->class_map[$class_name] =
                language\element\Clazz::fromClassName($class_name);
        }
    }


    /**
     *
     */
    private function addFunctionsByNames($function_name_list) {
        // TODO
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
