<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Variable;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\Scope;
use \Phan\Log;

/**
 * An object representing the context in which any
 * structural element (such as a class or method) lives.
 */
class Context extends FileRef {

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
     * @var FullyQualifiedClassName
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
     * @var FullyQualifiedFunctionName
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
     * @var Scope
     */
    private $scope = null;


    /**
     * Create a new context
     */
    public function __construct() {
        $this->namespace = '';
        $this->namespace_map = [];
        $this->class_fqsen = null;
        $this->method_fqsen = null;
        $this->closure_fqsen = null;
        $this->is_conditional = false;
        $this->scope = new Scope();
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
     * @return FullyQualifiedGlobalStructuralElement
     * The namespace mapped name for the given flags and name
     */
    public function getNamespaceMapFor(
        int $flags, string $name
    ) : FullyQualifiedGlobalStructuralElement {
        $name = strtolower($name);

        assert(
            !empty($this->namespace_map[$flags][$name]),
            "No namespace defined for $name"
        );

        assert($this->namespace_map[$flags][$name] instanceof FQSEN,
            "Namespace map for $flags $name was not an FQSEN");

        return $this->namespace_map[$flags][$name];
    }

    /**
     * @return Context
     * This context with the given value is returned
     */
    public function withNamespaceMap(
        int $flags,
        string $alias,
        FQSEN $target
    ) : Context {
        $this->namespace_map[$flags][strtolower($alias)] = $target;
        return $this;
    }

    /**
     * @param FullyQualifiedClassName $fqsen
     * A fully-qualified structural element name describing
     * the current class in scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withClassFQSEN(FullyQualifiedClassName $fqsen) : Context {
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
     * @return FullyQualifiedClassName
     * A fully-qualified structural element name describing
     * the current class in scope.
     */
    public function getClassFQSEN() : FullyQualifiedClassName {
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
     * @param FullyQualifiedMethodName $fqsen
     * A fully-qualified structural element name describing
     * the current function or method in scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withMethodFQSEN(FQSEN $fqsen = null) : Context {
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
     * @return FullyQualifiedMethodName
     * A fully-qualified structural element name describing
     * the current function or method in scope.
     */
    public function getMethodFQSEN() : FQSEN {
        return $this->method_fqsen;
    }

    /*
     * @param FullyQualifiedFunctionName $fqsen
     * A fully-qualified structural element name describing
     * the current closure in scope.
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withClosureFQSEN(FullyQualifiedFunctionName $fqsen = null) : Context {
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
     * @return FullyQualifiedFunctionName
     * A fully-qualified structural element name describing
     * the current closure in scope
     */
    public function getClosureFQSEN() : FullyQualifiedFunctionName {
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
        return $this->scope ?? new Scope();
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
     * Add a variable to this context's scope. Note that
     * this does not create a new context. You're actually
     * injecting the variable into the context. Use with
     * caution.
     *
     * @param Variable $variable
     * A variable to inject into this context
     *
     * @return null
     */
    public function addScopeVariable(
        Variable $variable
    ) {
        $this->scope =
            $this->getScope()->withVariable($variable);
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
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Clazz
     * Get the class in this scope, or fail real hard
     */
    public function getClassInScope(CodeBase $code_base) : Clazz {
        assert($this->isClassScope(),
            "Must be in class scope to get class");

        if (!$code_base->hasClassWithFQSEN($this->getClassFQSEN())) {
            Log::err(
                Log::EFATAL,
                "Cannot find class with FQSEN {$this->getClassFQSEN()} in context {$this}",
                $this->getFile(),
                0
            );
        }

        return $code_base->getClassByFQSEN(
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
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Method
     * Get the method in this scope or fail real hard
     */
    public function getMethodInScope(CodeBase $code_base) : Method {
        assert($this->isMethodScope(),
            "Must be in method scope to get method. Actually in {$this}");

        return $code_base->getMethodByFQSEN(
            $this->getMethodFQSEN()
        );
    }

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return bool
     * True if we're within a closure scope
     */
    public function isClosureScope() : bool {
        return !empty($this->closure_fqsen);
    }

    /**
     * @return Method
     * Get the closure in this scope or fail real hard
     */
    public function getClosureInScope(CodeBase $code_base) : Method {
        assert($this->isClosureScope(),
            "Must be in closure scope to get closure. Actually in {$this}");

        return $code_base->getMethodByFQSEN(
            $this->getClosureFQSEN()
        );
    }
}
