<?php declare(strict_types=1);
namespace Phan\Language;

use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\TypedElement;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Scope;

/**
 * An object representing the context in which any
 * structural element (such as a class or method) lives.
 */
class Context extends FileRef implements \Serializable
{

    /**
     * @var string
     * The namespace of the file
     */
    private $namespace = '';

    /**
     * @var array
     */
    private $namespace_map = [];

    /**
     * @var int
     * strict_types setting for the file
     */
    protected $strict_types = 0;

    /**
     * @var Scope
     * The current scope in this context
     */
    private $scope;

    /**
     * Create a new context
     */
    public function __construct()
    {
        $this->namespace = '';
        $this->namespace_map = [];
        $this->scope = new GlobalScope;
    }

    /*
     * @param string $namespace
     * The namespace of the file
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withNamespace(string $namespace) : Context
    {
        $context = clone($this);
        $context->namespace = $namespace;
        return $context;
    }

    /**
     * @return string
     * The namespace of the file
     */
    public function getNamespace() : string
    {
        return $this->namespace;
    }

    /**
     * @return bool
     * True if we have a mapped NS for the given named element
     */
    public function hasNamespaceMapFor(int $flags, string $name) : bool
    {
        // Look for the mapping on the part before a
        // slash
        $name_parts = explode('\\', $name, 2);
        if (count($name_parts) > 1) {
            $name = $name_parts[0];
        }

        return !empty($this->namespace_map[$flags][strtolower($name)]);
    }

    /**
     * @return FullyQualifiedGlobalStructuralElement
     * The namespace mapped name for the given flags and name
     */
    public function getNamespaceMapFor(
        int $flags,
        string $name
    ) : FullyQualifiedGlobalStructuralElement {
        $name = strtolower($name);

        // Look for the mapping on the part before a
        // slash
        $name_parts = explode('\\', $name, 2);
        $suffix = '';
        if (count($name_parts) > 1) {
            $name = $name_parts[0];
            $suffix = $name_parts[1];
        }

        assert(
            !empty($this->namespace_map[$flags][$name]),
            "No namespace defined for $name"
        );

        assert(
            $this->namespace_map[$flags][$name] instanceof FQSEN,
            "Namespace map for $flags $name was not an FQSEN"
        );

        $fqsen = $this->namespace_map[$flags][$name];

        if (!$suffix) {
            return $fqsen;
        }

        switch ($flags) {
        case T_CLASS:
            return FullyQualifiedClassName::fromFullyQualifiedString(
                (string)$fqsen . '\\' . $suffix
            );
        case T_FUNCTION:
            return FullyQualifiedFunctionName::fromFullyQualifiedString(
                (string)$fqsen . '\\' . $suffix
            );
        }

        assert(false, "Unknown flag $flags");
        return $fqsen;
    }

    /**
     * @return Context
     * This context with the given value is returned
     */
    public function withNamespaceMap(
        int $flags,
        string $alias,
        FullyQualifiedGlobalStructuralElement $target
    ) : Context {
        $this->namespace_map[$flags][strtolower($alias)] = $target;
        return $this;
    }

    /**
     * @param int $strict_types
     * The strict_type setting for the file
     *
     * @return Context
     * This context with the given value is returned
     */
    public function withStrictTypes(int $strict_types) : Context
    {
        $this->strict_types = $strict_types;
        return $this;
    }

    /**
     * @return bool
     * True if strict_types is set to 1 in this
     * context.
     */
    public function getIsStrictTypes() : bool
    {
        return (1 === $this->strict_types);
    }

    /**
     * @return Scope
     * An object describing the contents of the current
     * scope.
     */
    public function getScope() : Scope
    {
        return $this->scope;
    }

    /**
     * Set the scope on the context
     *
     * @return void
     */
    public function setScope(Scope $scope)
    {
        $this->scope = $scope;
    }

    /**
     * @return Context
     * A new context with the given scope
     */
    public function withScope(Scope $scope) : Context
    {
        $context = clone($this);
        $context->setScope($scope);
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
     * @param Variable $variable
     * A variable to add to the scope for the new
     * context
     *
     * @return void
     */
    public function addGlobalScopeVariable(Variable $variable) {
        $this->getScope()->addGlobalVariable($variable);
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
        $this->getScope()->addVariable($variable);
    }

    /**
     * @return bool
     * True if this context is currently within a class
     * scope, else false.
     */
    public function isInClassScope() : bool
    {
        return $this->getScope()->isInClassScope();
    }

    /**
     * @return FullyQualifiedClassName
     * A fully-qualified structural element name describing
     * the current class in scope.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        return $this->getScope()->getClassFQSEN();
    }

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Clazz
     * Get the class in this scope, or fail real hard
     *
     * @throws CodeBaseException
     * Thrown if we can't find the class in scope within the
     * given codebase.
     */
    public function getClassInScope(CodeBase $code_base) : Clazz
    {
        assert($this->isInClassScope(),
            "Must be in class scope to get class");

        if (!$code_base->hasClassWithFQSEN($this->getClassFQSEN())) {
            throw new CodeBaseException(
                $this->getClassFQSEN(),
                "Cannot find class with FQSEN {$this->getClassFQSEN()} in context {$this}"
            );
        }

        return $code_base->getClassByFQSEN(
            $this->getClassFQSEN()
        );
    }

    /**
     * @return bool
     * True if this context is currently within a method,
     * function or closure scope.
     */
    public function isInFunctionLikeScope() : bool
    {
        return $this->getScope()->isInFunctionLikeScope();
    }

    /**
     * @return bool
     * True if this context is currently within a method.
     */
    public function isInMethodScope() : bool
    {
        return (
            $this->isInClassScope()
            && $this->isInFunctionLikeScope()
        );
    }

    /*
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName|FullyQualifiedClosureName
     * A fully-qualified structural element name describing
     * the current function or method in scope.
     */
    public function getFunctionLikeFQSEN()
    {
        assert($this->getScope()->isInFunctionLikeScope());
        return $this->getScope()->getFunctionLikeFQSEN();
    }

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return FunctionInterface
     * Get the method in this scope or fail real hard
     */
    public function getFunctionLikeInScope(
        CodeBase $code_base
    ) : FunctionInterface {
        assert($this->isInFunctionLikeScope(),
            "Must be in method scope to get method. Actually in {$this}");

        $fqsen = $this->getFunctionLikeFQSEN();

        if ($fqsen instanceof FullyQualifiedFunctionName) {
            assert($code_base->hasFunctionWithFQSEN($fqsen),
                "The function with FQSEN $fqsen does not exist");
            return $code_base->getFunctionByFQSEN($fqsen);
        }

        if ($fqsen instanceof FullyQualifiedMethodName) {
            assert($code_base->hasMethodWithFQSEN($fqsen),
                "The method with FQSEN $fqsen does not exist");
            return $code_base->getMethodByFQSEN($fqsen);
        }

        assert(false, "FQSEN must be for a function or method");
    }

    /**
     * @return bool
     * True if we're within the scope of a class, method,
     * function or closure. False if we're in the global
     * scope
     */
    public function isInElementScope() : bool
    {
        return (
            $this->isInFunctionLikeScope()
            || $this->isInClassScope()
        );
    }

    /**
     * @return bool
     * True if we're in the global scope (not in a class,
     * method, function, closure).
     */
    public function isInGlobalScope() : bool
    {
        return !$this->isInElementScope();
    }

    /**
     * @param CodeBase $code_base
     * The code base from which to retrieve the TypedElement
     *
     * @return TypedElement
     * The element who's scope we're in. If we're in the global
     * scope this method will go down in flames and take your
     * process with it.
     */
    public function getElementInScope(CodeBase $code_base) : TypedElement
    {
        assert($this->isInElementScope(),
            "Cannot get element in scope if we're in the global scope");

        if ($this->isInFunctionLikeScope()) {
            return $this->getFunctionLikeInScope($code_base);
        } else if ($this->isInClassScope()) {
            return $this->getClassInScope($code_base);
        }

        throw new CodeBaseException(null,
            "Cannot get element in scope if we're in the global scope"
        );
    }

    /**
     * @param CodeBase $code_base
     * The code base from which to retrieve a possible TypedElement
     * that contains an issue suppression list
     *
     * @return bool
     * True if issues with the given name are suppressed within
     * this context.
     */
    public function hasSuppressIssue(
        CodeBase $code_base,
        string $issue_name
    ) : bool
    {
        if (!$this->isInElementScope()) {
            return false;
        }

        return $this->getElementInScope($code_base)->hasSuppressIssue(
            $issue_name
        );
    }

    public function serialize()
    {

        $serialized = parent::serialize();

        $serialized .= '^' . implode('|', [
            $this->getNamespace(),
            (string)$this->method_fqsen,
            (string)$this->closure_fqsen
        ]);

        return $serialized;
    }

    /**
     * @return void
     */
    public function unserialize($serialized)
    {
        list($file_ref, $serialized) = explode('^', $serialized);
        parent::unserialize($file_ref);

        list($namespace) = explode('|', $serialized);
        $this->namespace = $namespace;
    }
}
