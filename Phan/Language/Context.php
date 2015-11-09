<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Variable;
use \Phan\Language\Scope;
use \Phan\Log;

/**
 * An object representing the context in which any
 * structural element (such as a class or method) lives.
 */
class Context {

    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @var string
     * The path to the file in which this element is defined
     */
    private $file = 'internal';

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
     * @var FQSEN
     * A fully-qualified structural element name describing
     * the current class or the empty-string if we are not
     * in a class scope.
     */
    private $class_fqsen = null;

    /**
     * @var FQSEN
     * A fully-qualified structural element name describing
     * the current function or method or the empty-string if
     * we are not in a function or method scope.
     */
    private $method_fqsen = null;

    /**
     * @var FQSEN
     * A fully-qualified structural element name describing
     * the current closure we're in or null if we're not
     * in a closure.
     */
    private $closure_fqsen = null;

    /**
     * @var bool
     */
    private $is_conditional = false;

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
     * @var Scope
     */
    private $scope = null;

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which this
     * context exists
     */
    public function __construct(CodeBase $code_base) {
        $this->code_base = $code_base;
        $this->file = 'internal';
        $this->namespace = '';
        $this->namespace_map = [];
        $this->scope_fqsen = null;
        $this->class_fqsen = null;
        $this->method_fqsen = null;
        $this->closure_fqsen = null;
        $this->is_conditional = false;
        $this->line_number_start = 0;
        $this->line_number_end = 0;
        $this->scope = new Scope();
    }

    /**
     * @return CodeBase
     * The code base in which this context exists
     */
    public function getCodeBase() : CodeBase {
        return $this->code_base;
    }

    /**
     * @param string $file
     * The path to the file in which this element is defined
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withFile(string $file) : Context {
        $context = clone($this);
        $context->file = $file;
        return $context;
    }

    /**
     * @return string
     * The path to the file in which the element is defined
     */
    public function getFile() : string {
        return $this->file;
    }

    /**
     * @return bool
     * True if this object is internal to PHP
     */
    public function isInternal() : bool {
        return ('internal' === $this->getFile());
    }

    /*
     * @param string $namespace
     * The namespace of the file
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withNamespace(string $namespace) : Context {
        $context = clone($this);
        $context->namespace = $namespace;
        return $context;
    }

    /**
     * @return bool
     * True if a namespace is defined in this context, else
     * false.
     */
    public function hasNamespace() : bool {
        return !empty($this->namespace);
    }

    /**
     * @return string
     * The namespace of the file
     */
    public function getNamespace() : string {
        return $this->namespace;
    }

    /**
     * @return array
     */
    public function getNamespaceMap() : array {
        return $this->namespace_map;
    }

    /**
     * @return bool
     * True if we have a mapped NS for the given named element
     */
    public function hasNamespaceMapFor(int $flags, string $name) : bool {
        return !empty($this->namespace_map[$flags][strtolower($name)]);
    }

    /**
     * @return string
     * The namespace mapped name for the given flags and name
     */
    public function getNamespaceMapFor(int $flags, string $name) : FQSEN {
        if (!empty($this->namespace_map[$flags][strtolower($name)])) {
            return $this->namespace_map[$flags][strtolower($name)];
        }

        return '';
    }

    /**
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withNamespaceMap(
        int $flags,
        string $alias,
        string  $target
    ) : Context {
        $context = clone($this);

        $context->namespace_map[$flags][strtolower($alias)] =
            FQSEN::fromFullyQualifiedString($target);

        return $context;
    }

    /**
     * @var int $line_number
     * The starting line number of the element within the file
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withLineNumberStart(int $line_number) : Context {
        $context = clone($this);
        $context->line_number_start = $line_number;
        return $context;
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
     * A clone of this context with the given value is returned
     */
    public function withLineNumberEnd(int $line_number) : Context {
        $context = clone($this);
        $context->line_number_end = $line_number;
        return $context;
    }

    /**
     * @return int
     * The ending line number of the element within the $file
     */
    public function getLineNumberEnd() : int {
        return $this->line_number_end;
    }

    /**
     * @param FQSEN $fqsen
     * A fully-qualified structural element name describing
     * the current class in scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withClassFQSEN(FQSEN $fqsen) : Context {
        $context = clone($this);
        $context->class_fqsen = $fqsen;
        return $context;
    }

    /**
     * @return bool
     * True if a class fqsen is defined within this context.
     */
    public function hasClassFQSEN() : bool {
        return !empty($this->class_fqsen);
    }

    /**
     * @return FQSEN
     * A fully-qualified structural element name describing
     * the current class in scope.
     */
    public function getClassFQSEN() : FQSEN {
        return $this->class_fqsen;
    }

    /**
     * @return bool
     * True if this context is currently within a class
     * scope, else false.
     */
    public function isInClassScope() : bool {
        return !empty($this->class_fqsen);
    }

    /*
     * @param FQSEN $fqsen
     * A fully-qualified structural element name describing
     * the current function or method in scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withMethodFQSEN(FQSEN $fqsen) : Context {
        $context = clone($this);
        $context->method_fqsen = $fqsen;
        return $context;
    }

    /**
     * @return bool
     * True if a method FQSEN is defined, else false.
     */
    public function hasMethodFQSEN() : bool {
        return !empty($this->method_fqsen);
    }

    /*
     * @return FQSEN
     * A fully-qualified structural element name describing
     * the current function or method in scope.
     */
    public function getMethodFQSEN() : FQSEN {
        return $this->method_fqsen;
    }

    /*
     * @param FQSEN $fqsen
     * A fully-qualified structural element name describing
     * the current closure in scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withClosureFQSEN(FQSEN $fqsen) : Context {
        $context = clone($this);
        $context->closure_fqsen = $fqsen;
        return $context;
    }

    /**
     * @return bool
     * True if a closure FQSEN is defined, else false.
     */
    public function hasClosureFQSEN() : bool {
        return !empty($this->closure_fqsen);
    }

    /*
     * @return FQSEN
     * A fully-qualified structural element name describing
     * the current closure in scope
     */
    public function getClosureFQSEN() : FQSEN {
        return $this->closure_fqsen;
    }

    /**
     * @param bool $is_conditional
     * True if the current context is within a conditional
     * else false.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withIsConditional(bool $is_conditional) : Context {
        $context = clone($this);
        $context->is_conditional = $is_conditional;
        return $context;
    }

    /**
     * @return bool
     * True if the current context is within a conditional
     * else false.
     */
    public function getIsConditional() : bool {
        return $this->is_conditional;
    }

    /**
     * @return Scope
     * An object describing the contents of the current
     * scope.
     */
    public function getScope() : Scope {
        return $this->scope;
    }

    /**
     * @return Context
     * A new context with the given scope
     */
    public function withScope(Scope $scope) : Context {
        $context = clone($this);
        $context->scope = $scope;
        return $context;
    }

    /**
     * @param Variable $variable
     * A variable to add to the scope for the new
     * context
     *
     * @return Context
     * A new context based on this with a variable
     * as defined by the parameters in scope
     */
    public function withScopeVariable(
        Variable $variable
    ) : Context {
        return $this->withScope(
            $this->getScope()->withVariable($variable)
        );
    }

    /**
     * @param FQSEN $fqsen
     * A fully-qualified structural element name describing
     * the current scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withScopeFQSEN(FQSEN $fqsen) : Context {
        return clone($this)
            ->withNamespace($fqsen->getNamespace())
            ->withClassName($fqsen->getClassName())
            ->withMethodName($fqsen->getMethodName())
            ->withClosureName($fqsen->getClosureName());
    }

    /**
     * @return string
     * A fully-qualified structural element name describing
     * the current scope.
     */
    public function getScopeFQSEN() : FQSEN {

        // If we're in a method, return it's FQSEN
        if ($this->hasMethodFQSEN()) {
            return $this->getMethodFQSEN();
        }

        // If we're in a class, return it's FQSEN
        if ($this->hasClassFQSEN()) {
            return $this->getClassFQSEN();
        }

        // If we have a namespace defined, return a
        // partial FQSEN
        if ($this->hasNamespace()) {
            return new FQSEN($this->getNamespace());
        }

        // Otherwise, pass the current namespace map
        // along
        return new FQSEN();
    }

    /**
     * @return bool
     * True if we are currently within the global scope
     * i.e. Not within a class
     */
    public function isGlobalScope() : bool {
        return (
            empty($this->class_fqsen)
            && empty($this->method_fqsen)
        );
    }

    /**
     * @return bool
     * True if we're currently within a class scope
     */
    public function isClassScope() : bool {
        return $this->hasClassFQSEN();
    }

    /**
     * @return Clazz
     * Get the class in this scope, or fail real hard
     */
    public function getClassInScope() : Clazz {
        assert($this->isClassScope(),
            "Must be in class scope to get class");

        if (!$this->getCodeBase()->hasClassWithFQSEN($this->getClassFQSEN())) {
            Log::err(
                Log::EFATAL,
                "Cannot find class with FQSEN {$this->getClassFQSEN()} in context {$this}",
                $this->getFile(),
                0
            );
        }

        return $this->getCodeBase()->getClassByFQSEN(
            $this->getClassFQSEN()
        );
    }

    /**
     * @return bool
     * True if we're within a method scope
     */
    public function isMethodScope() : bool {
        return !empty($this->method_fqsen);
    }

    /**
     * @return Method
     * Get the method in this scope or fail real hard
     */
    public function getMethodInScope() : Method {
        assert($this->isMethodScope(),
            "Must be in method scope to get class. Actually in {$this}");

        return $this->getCodeBase()->getMethodByFQSEN(
            $this->getMethodFQSEN()
        );
    }

    /**
     * Get a string representation of the context
     *
     * @return string
     */
    public function __toString() : string {
        return $this->file
            . ':' . (string)$this->line_number_start
            . ($this->line_number_end
                ? (':' . (string)$this->line_number_end)
                : '')
            . ' in scope ' . (string)$this->getScopeFQSEN()
            ;
    }
}
