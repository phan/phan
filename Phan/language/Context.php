<?php
declare(strict_types=1);
namespace phan\langauge;

/**
 * An object representing the context in which any
 * structural element (such as a class or method) lives.
 */
class Context {

    /**
     * @var string
     * The path to the file in which this element is defined
     */
    private $file = '';

    /**
     * @var string
     * The namespace of the file
     */
    private $namespace = '';

    /**
     * @var array
     * ...
     */
    private $namespace_map = [];

    /**
     * @var int
     * The starting line number of the element within the $file
     */
    private $line_number_start = 0;

    /**
     * @var int
     * The ending line number of the element within the $file
     */
    private $line_number_end = 0;

    /**
     * @var string
     * A fully-qualified structural element name describing
     * the current scope.
     */
    private $scope_fqsen = '';

    /**
     * @var string
     * A fully-qualified structural element name describing
     * the current class or the empty-string if we are not
     * in a class scope.
     */
    private $class_fqsen = '';

    /**
     * @var string
     * A fully-qualified structural element name describing
     * the current function or method or the empty-string if
     * we are not in a function or method scope.
     */
    private $method_fqsen = '';

    /**
     * @var bool
     */
    private $is_conditional = false;

    /**
     * @return Context
     * An empty context such as for builtin functions and
     * classes.
     */
    public static function none() {
        return new Context();
    }

    /**
     * @param string $file
     * The path to the file in which this element is defined
     *
     * @return Context
     */
    public function withFile(string $file) {
        $this->file = $file;
        return $this;
    }

    /**
     * @return string
     * The path to the file in which the element is defined
     */
    public function getFile() : string {
        return $this->file;
    }

    /*
     * @param string $namespace
     * The namespace of the file
     *
     * @return Context
     */
    public function withNamespace(string $namespace) {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     * The namespace of the file
     */
    public function getNamespace() : string {
        return $this->namespace;
    }

    /**
     * ...
     *
     * @return Context
     */
    public function withNamespaceMap(
        int $flags,
        string $alias,
        string  $target
    ) {
        $this->namespace_map[$flags][strtolower($alias)] = $target;
        return $this;
    }

    /**
     * @var int $line_number
     * The starting line number of the element within the file
     *
     * @return Context
     */
    public function withLineNumberStart(int $line_number) {
        $this->line_number_start = $line_number;
        return $this;
    }

    /*
     * @return int
     * The starting line number of the element within the file
     */
    public function getLineNumberStart() : int {
        return $this->line_number_start;
    }

    /**
     * @param int $line_number
     * The ending line number of the element within the $file
     *
     * @return Context
     */
    public function withLineNumberEnd(int $line_number) {
        $this->line_number_end = $line_number;
        return $this;
    }

    /**
     * @return int
     * The ending line number of the element within the $file
     */
    public function getLineNumberEnd() : int {
        return $this->line_number_end;
    }

    /**
     * @param string $fqsen
     * A fully-qualified structural element name describing
     * the current scope.
     *
     * @return Context
     */
    public function withScopeFQSEN(string $fqsen) {
        $this->scope_fqsen = $fqsen;
        return $this;
    }

    /**
     * @return string
     * A fully-qualified structural element name describing
     * the current scope.
     */
    public function getScopeFQSEN() : string {
        return $this->scope_fqsen;
    }

    /**
     * @param string $fqsen
     * A fully-qualified structural element name describing
     * the current class or the empty-string if we are not
     * in a class scope.
     *
     * @return Context
     */
    public function withClassFQSEN(string $fqsen) {
        $this->class_name = $class_name;
        return $this;
    }

    /**
     * @return string
     * A fully-qualified structural element name describing
     * the current class or the empty-string if we are not
     * in a class scope.
     */
    public function getClassName() {
        return $this->class_name;
    }

    /*
     * @param string $fqsen
     * A fully-qualified structural element name describing
     * the current function or method or the empty-string if
     * we are not in a function or method scope.
     *
     * @return Context
     */
    public function withMethodFQSEN(string $fqsen) {
        $this->method_fqsen = $fqsen;
        return $this;
    }

    /*
     * @return string
     * A fully-qualified structural element name describing
     * the current function or method or the empty-string if
     * we are not in a function or method scope.
     */
    public function getMethodFQSEN() : string {
        return $this->method_fqsen;
    }

    /**
     * @param bool $is_conditional
     * True if the current context is within a conditional
     * else false.
     *
     * @return Context
     */
    public function withIsConditional(bool $is_conditional) {
        $this->is_conditional = $is_conditional;
        return $this;
    }

    /**
     * @return bool
     * True if the current context is within a conditional
     * else false.
     */
    public function getIsConditional() : bool {
        return $this->is_conditional;
    }

}
