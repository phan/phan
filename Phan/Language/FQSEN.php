<?php
declare(strict_types=1);
namespace Phan\Language;

/**
 * A Fully-Qualified Structural Element Name
 */
class FQSEN {

    /**
     * ...
     */
    private $namespace_map = [];

    /**
     * @var string
     * ...
     */
    private $namespace = '';

    /**
     * @var string
     * ...
     */
    private $class_name = '';

    /**
     * @var string
     * ...
     */
    private $method_name = '';

    /**
     * @var int
     * An identifier for which alternative form
     * of this FQSEN.
     */
    private $alternate_id = 0;

    /**
     * @param array $namespace_map
     * @param string $namespace
     * @param string $class_name
     * @param string $method_name
     */
    public function __construct(
        array $namespace_map = null,
        string $namespace = '',
        string $class_name = '',
        string $method_name = ''
    ) {
        $this->namespace_map = $namespace_map;
        $this->namespace = $namespace;
        $this->class_name = $class_name;
        $this->method_name = $method_name;
    }

    /**
     * @return FQSEN
     * A fully-qualified structural element name describing
     * the given Context.
     */
    public static function fromContext(Context $context) : FQSEN {
        return new FQSEN(
            [],
            $context->getNamespace(),
            $context->getClassFQSEN()->getClassName(),
            $context->getMethodFQSEN()->getMethodName()
        );
    }

    /**
     * @return FQSEN[]
     * An infinite list of alternative FQSENs
     */
    public function alternateFQSENInfiniteList() : \Iterator {
        $i = 0;
        while(true) {
            yield (new FQSEN(
                $this->namespace_map,
                $this->namespace,
                $this->class_name,
                $this->method_name
            ))->withAlternateId(++$i);
        }
    }

    /**
     * @return FQSEN
     * This FQSEN with the given namespace
     */
    public function withNamespace(string $namespace) : FQSEN {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     * The namespace associated with this FQSEN
     * or null if not defined
     */
    public function getNamespace() : string {
        return $this->namespace;
    }

    /**
     * @return FQSEN
     * This FQSEN with the given class name
     */
    public function withClassName(string $class_name) : string {
        $this->class_name = $class_name;
        return $this;
    }

    /**
     * @return string
     * The class associated with this FQSEN or
     * null if not defined
     */
    public function getClassName() : string {
        return $this->class_name;
    }

    /**
     * @return FQSEN
     * This FQSEN with the given method name
     */
    public function withMethodName(string $method_name) : FQSEN {
        $this->method_name = $method_name;
        return $this;
    }

    /**
     * @return string
     * The method name associated with this
     * FQSEN or null if not defined.
     */
    public function getMethodName() : string {
        return $this->method_name;
    }

    /**
     * @return FQSEN
     * A new FQSEN with the given alternate_id set
     */
    public function withAlternateId(int $alternate_id) {
        $this->alternate_id = $alternate_id;
        return $this;
    }

    /**
     * @return int
     * An alternate identifier associated with this
     * FQSEN or zero if none if this is not an
     * alternative.
     */
    public function getAlternateId() : int {
        return $this->alternate_id;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string {
        $fqsen_string = '';

        $fqsen_string .= $this->namespace ?: '';

        if ($this->class_name) {
            $fqsen_string .= strtolower($this->class_name);
        }

        if ($this->method_name) {
            $fqsen_string .= '::' . $this->method_name;
        }

        if ($this->alternate_id) {
            $fqsen_string .= ' ' . $this->alternate_id;
        }

        return $fqsen_string;
    }

    /**
     * @param string $namespace
     * A possibly null namespace within which the class
     * exits
     *
     * @param string $class_name
     * The name of a class to get an FQSEN string for
     *
     * @return string
     * An FQSEN string representing the given
     * namespace and class_name
     */
    public static function fqsenStringForClassName(
        string $namespace = null,
        string $class_name
    ) : string {
        return (
            new FQSEN($namespace, $class_name, null)
        )->__toString();
    }

    /**
     * @param string $namespace
     * A possibly null namespace within which the class
     * exits
     *
     * @param string $function_name
     * The name of a function to get an FQSEN string for
     *
     * @return string
     * An FQSEN string representing the given
     * namespace and function
     */
    public static function fqsenStringForFunctionName(
        string $namespace = null,
        string $function_name
    ) {
        return (
            new FQSEN($namespace, null, $function_name)
        )->__toString();
    }

    /**
     * @param string $namespace
     * A possibly null namespace within which the class
     * exits
     *
     * @param string $class_name
     * The name of a class to get an FQSEN string for
     *
     * @param string $method_name
     * The name of a method to get an FQSEN string for
     *
     * @return string
     * An FQSEN string representing the given
     * namespace and class_name
     */
    public static function fqsenStringForClassAndMethodName(
        string $namespace,
        string $class_name,
        string $method_name
    ) {
        return (
            new FQSEN($namespace, $class_name, $method_name)
        )->__toString();
    }
}
