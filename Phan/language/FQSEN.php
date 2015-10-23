<?php
declare(strict_types=1);
namespace phan\language;

/**
 * A Fully-Qualified Structural Element Name
 */
class FQSEN {

    /**
     * @var string
     * ...
     */
    private $namespace = null;

    /**
     * @var string
     * ...
     */
    private $class_name = null;

    /**
     * @var string
     * ...
     */
    private $method_name = null;

    /**
     * @var Type[]
     */
    private $closure_parameter_list = null;

    /**
     * @var Type
     */
    private $closure_return_type = null;

    public function __construct(
        string $namespace = null,
        string $class_name = null,
        string $method_name = null,
        array $closure_parameter_list = null,
        Type $closure_return_type = null
    ) {
        $this->namespace = $namespace;
        $this->class_name = $class_name;
        $this->method_name = $method_name;
        $this->closure_parameter_list = $closure_parameter_list;
        $this->closure_return_type = $closure_return_type;
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return '';
    }

    /**
     *
     */
    public static function fqsenStringForClassName(
        string $namespace = null,
        string $class_name
    ) : string {
        return (
            new FQSEN($namespace, $class_name, null, null, null)
        )->__toString();
    }

    /**
     *
     */
    public static function fqsenForFunctionName(
        string $namespace = null,
        string $function_name
    ) {
        return (
            new FQSEN($namespace, null, $function_name, null, null)
        )->__toString();
    }

    /**
     *
     */
    public static function fqsenForClassAndMethodName(
        string $namespace,
        string $class_name,
        string $method_name
    ) {
        return (
            new FQSEN($namespace, $class_name, $method_name, null, null)
        )->__toString();
    }

    /**
     *
     */
    public static function fqsenForClosure(
        string $namespace,
        string $class_name,
        string $method_name,
        array $closure_parameter_list,
        Type $closure_return_type
    ) {
        return (
            new FQSEN($namespace, $class_name, $method_name, $closure_parameter_list, $closure_return_type)
        )->__toString();
    }

}
