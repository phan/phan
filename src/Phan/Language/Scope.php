<?php
declare(strict_types=1);

namespace Phan\Language;

use AssertionError;
use Phan\Config;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type\TemplateType;

/**
 * Represents the scope of a Context.
 *
 * This includes the current element which it is found in,
 * variables (etc.) found in that scope,
 * as well as any functionality to use/modify this information.
 *
 * A scope is either the global scope or a child scope of another scope.
 */
abstract class Scope
{
    const IN_FUNCTION_LIKE_SCOPE = 0x01;
    // If this is set, and neither IN_TRAIT_SCOPE and IN_INTERFACE_SCOPE are set, this is
    const IN_CLASS_SCOPE         = 0x02;
    // If this is set, this is a trait (self::IN_CLASS_SCOPE will also be set)
    const IN_TRAIT_SCOPE         = 0x04;
    // If this is set, this is an interface (self::IN_CLASS_SCOPE will also be set)
    const IN_INTERFACE_SCOPE     = 0x08;
    const IN_CLASS_LIKE_SCOPE    = self::IN_CLASS_SCOPE | self::IN_TRAIT_SCOPE | self::IN_INTERFACE_SCOPE;
    const IN_PROPERTY_SCOPE      = 0x10;

    /**
     * @var Scope the parent scope, if this is not the global scope
     */
    protected $parent_scope = null;

    /**
     * @var FQSEN|null the FQSEN that this scope is within,
     * if this scope is within an element such as a function body or class definition.
     *
     * This is null only in the subclass GlobalScope
     */
    protected $fqsen = null;

    /**
     * @var int flags - Combination of self::IN_*
     * This allows us to check if we are in a class-like scope, function-like scope, etc. without recursing.
     */
    protected $flags = 0;

    /**
     * @var array<string,Variable> the map of variable names to variables within this scope.
     * Some variable definitions must be retrieved from parent scopes.
     */
    protected $variable_map = [];

    /**
     * @var array<string,TemplateType>
     * A map from template type identifiers to the
     * TemplateType that parameterizes the generic class
     * in this scope.
     */
    private $template_type_map = [];

    /**
     * @param Scope $parent_scope
     * @param ?FQSEN $fqsen
     * @param int $flags
     */
    public function __construct(
        Scope $parent_scope,
        $fqsen,
        int $flags
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
        $this->flags = $flags;
    }

    /**
     * @return bool
     * True if this scope has a parent scope
     * @suppress PhanUnreferencedPublicMethod this was optimized out within the class
     */
    public function hasParentScope() : bool
    {
        return true;
    }

    /**
     * @return Scope
     * Get the parent scope of this scope
     */
    public function getParentScope() : Scope
    {
        return $this->parent_scope;
    }

    /**
     * @return bool
     * True if this scope has an FQSEN
     * @suppress PhanUnreferencedPublicMethod
     */
    public function hasFQSEN() : bool
    {
        return $this->fqsen !== null;
    }

    /**
     * @return FQSEN in which this scope was declared
     * (e.g. a FullyQualifiedFunctionName, FullyQualifiedClassName, etc.)
     * @suppress PhanPossiblyNullTypeReturn, PhanPartialTypeMismatchReturn callers should call hasFQSEN
     */
    public function getFQSEN()
    {
        return $this->fqsen;
    }

    /**
     * @return bool
     * True if we're in a class-like's scope
     */
    public function isInClassScope() : bool
    {
        return ($this->flags & self::IN_CLASS_LIKE_SCOPE) !== 0;
    }

    /**
     * True if we're in a class-like's scope and that class-like is a trait.
     */
    public function isInTraitScope() : bool
    {
        return ($this->flags & self::IN_TRAIT_SCOPE) !== 0;
    }

    /**
     * Checks if we're in an interface's scope.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function isInInterfaceScope() : bool
    {
        return ($this->flags & self::IN_INTERFACE_SCOPE) !== 0;
    }

    /**
     * Returns true if we're in an element scope (i.e. not in the global scope)
     */
    public function isInElementScope() : bool
    {
        return $this->flags !== 0;
    }

    /**
     * @return bool
     * True if we're in a method-like scope
     */
    public function isInMethodLikeScope() : bool
    {
        return ($this->flags & self::IN_CLASS_LIKE_SCOPE) !== 0 && $this->flags & self::IN_FUNCTION_LIKE_SCOPE !== 0;
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        return $this->parent_scope->getClassFQSEN();
    }

    /**
     * @return ?FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSENOrNull()
    {
        return $this->parent_scope->getClassFQSENOrNull();
    }

    /**
     * @return bool
     * True if we're in a property scope
     */
    public function isInPropertyScope() : bool
    {
        return (self::IN_PROPERTY_SCOPE & $this->flags) !== 0;
    }

    /**
     * @return FullyQualifiedPropertyName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getPropertyFQSEN() : FullyQualifiedPropertyName
    {
        return $this->parent_scope->getPropertyFQSEN();
    }

    /**
     * @return bool
     * True if we're in a method/function/closure scope
     */
    public function isInFunctionLikeScope() : bool
    {
        return ($this->flags & self::IN_FUNCTION_LIKE_SCOPE) !== 0;
    }

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Crawl the scope hierarchy to get a method FQSEN.
     */
    public function getFunctionLikeFQSEN()
    {
        return $this->parent_scope->getFunctionLikeFQSEN();
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool
    {
        return \array_key_exists($name, $this->variable_map);
    }

    /**
     * Locates the variable with name $name.
     * Callers should check $this->hasVariableWithName() first.
     */
    public function getVariableByName(string $name) : Variable
    {
        return $this->variable_map[$name];
    }

    /**
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array
    {
        return $this->variable_map;
    }

    /**
     * @param Variable $variable
     * A variable to add to the local scope
     *
     * @return Scope a clone of this scope with $variable added
     */
    public function withVariable(Variable $variable) : Scope
    {
        $scope = clone($this);
        $scope->addVariable($variable);
        return $scope;
    }

    /**
     * @param string $variable_name
     * The name of a variable to unset in the local scope
     *
     * @return Scope
     *
     * TODO: Make this work properly and merge properly when the variable is in a branch
     *
     * @suppress PhanUnreferencedPublicMethod unused, but adding to be consistent with `withVariable`
     */
    public function withUnsetVariable(string $variable_name) : Scope
    {
        $scope = clone($this);
        $scope->unsetVariable($variable_name);
        return $scope;
    }

    /**
     * @param string $variable_name
     * The name of a variable to unset in the local scope
     *
     * @return void
     *
     * TODO: Make this work properly and merge properly when the variable is in a branch (BranchScope)
     */
    public function unsetVariable(string $variable_name)
    {
        unset($this->variable_map[$variable_name]);
    }

    /**
     * Add $variable to the current scope.
     *
     * @see self::withVariable() for creating a clone of a scope with $variable instead
     * @return void
     */
    public function addVariable(Variable $variable)
    {
        // uncomment to debug issues with variadics
        /*
        if ($variable->isVariadic() && !$variable->isCloneOfVariadic()) {
            throw new \Error("Bad variable {$variable->getName()}\n");
        }
         */
        $this->variable_map[$variable->getName()] = $variable;
    }

    /**
     * Add $variable to the set of global variables
     *
     * @param Variable $variable
     * A variable to add to the set of global variables
     *
     * @return void
     */
    public function addGlobalVariable(Variable $variable)
    {
        $this->parent_scope->addGlobalVariable($variable);
    }

    /**
     * @return bool
     * True if a global variable with the given name exists
     */
    public function hasGlobalVariableWithName(string $name) : bool
    {
        return $this->parent_scope->hasGlobalVariableWithName($name);
    }

    /**
     * @return Variable
     * The global variable with the given name
     */
    public function getGlobalVariableByName(string $name) : Variable
    {
        return $this->parent_scope->getGlobalVariableByName($name);
    }

    /**
     * @return bool
     * True if there are any template types parameterizing a
     * generic class in this scope.
     */
    public function hasAnyTemplateType() : bool
    {
        if (!Config::getValue('generic_types_enabled')) {
            return false;
        }

        return \count($this->template_type_map) > 0
            || $this->parent_scope->hasAnyTemplateType();
    }

    /**
     * @return array<string,TemplateType>
     * The set of all template types parameterizing this generic
     * class
     */
    public function getTemplateTypeMap() : array
    {
        return \array_merge(
            $this->template_type_map,
            $this->parent_scope->getTemplateTypeMap()
        );
    }

    /**
     * @return bool
     * True if the given template type identifier is defined within
     * this context
     */
    public function hasTemplateType(
        string $template_type_identifier
    ) : bool {

        return isset(
            $this->template_type_map[$template_type_identifier]
        ) || $this->parent_scope->hasTemplateType(
            $template_type_identifier
        );
    }

    /**
     * Adds a template type to the current scope.
     *
     * The TemplateType is resolved during analysis based on the passed in union types
     * for the parameters (e.g. of __construct()) using those template types
     *
     * @param TemplateType $template_type
     * A template type parameterizing the generic class in scope
     *
     * @return void
     */
    public function addTemplateType(TemplateType $template_type)
    {
        $this->template_type_map[$template_type->getName()] = $template_type;
    }

    /**
     * @param string $template_type_identifier
     * The identifier for a generic type
     *
     * @return TemplateType
     * A TemplateType parameterizing the generic class in scope
     */
    public function getTemplateType(
        string $template_type_identifier
    ) : TemplateType {

        if (!$this->hasTemplateType($template_type_identifier)) {
            throw new AssertionError("Cannot get template type with identifier $template_type_identifier");
        }

        return $this->template_type_map[$template_type_identifier]
            ?? $this->parent_scope->getTemplateType($template_type_identifier);
    }

    /**
     * @return string
     * A string representation of this scope
     */
    public function __toString() : string
    {
        return $this->getFQSEN() . "\t" . \implode(',', $this->getVariableMap());
    }
}
