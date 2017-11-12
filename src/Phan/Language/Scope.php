<?php
declare(strict_types=1);
namespace Phan\Language;

use Phan\Config;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type\TemplateType;

abstract class Scope
{
    /**
     * @var Scope|null
     */
    private $parent_scope = null;

    /**
     * @var FQSEN|null
     */
    protected $fqsen = null;

    /**
     * @var Variable[]
     */
    protected $variable_map = [];

    /**
     * @var TemplateType[]
     * A map from template type identifiers to the
     * TemplateType that parameterizes the generic class
     * in this scope.
     */
    private $template_type_map = [];

    /**
     * @param ?Scope $parent_scope
     * @param ?FQSEN $fqsen
     */
    public function __construct(
        Scope $parent_scope = null,
        FQSEN $fqsen = null
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
    }

    /**
     * @return bool
     * True if this scope has a parent scope
     */
    public function hasParentScope() : bool
    {
        return (
            !empty($this->parent_scope)
            && $this->parent_scope !== null
        );
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
     */
    public function hasFQSEN() : bool
    {
        return !empty($this->fqsen);
    }

    /**
     *
     */
    public function getFQSEN()
    {
        return $this->fqsen;
    }

    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInClassScope() : bool
    {
        return $this->hasParentScope()
            ? $this->getParentScope()->isInClassScope() : false;
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        \assert(
            $this->hasParentScope(),
            "Cannot get class FQSEN on scope"
        );

        return $this->getParentScope()->getClassFQSEN();
    }

    /**
     * @return bool
     * True if we're in a property scope
     */
    public function isInPropertyScope() : bool
    {
        return $this->hasParentScope()
            ? $this->getParentScope()->isInPropertyScope() : false;
    }

    /**
     * @return FullyQualifiedPropertyName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getPropertyFQSEN() : FullyQualifiedPropertyName
    {
        \assert(
            $this->hasParentScope(),
            "Cannot get class FQSEN on scope"
        );

        return $this->getParentScope()->getPropertyFQSEN();
    }

    /**
     * @return bool
     * True if we're in a method/function/closure scope
     */
    public function isInFunctionLikeScope() : bool
    {
        return $this->hasParentScope()
            ? $this->getParentScope()->isInFunctionLikeScope() : false;
    }

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Crawl the scope hierarchy to get a method FQSEN.
     */
    public function getFunctionLikeFQSEN()
    {
        \assert(
            $this->hasParentScope(),
            "Cannot get method/function/closure FQSEN on scope"
        );

        return $this->getParentScope()->getFunctionLikeFQSEN();
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool
    {
        return (!empty($this->variable_map[$name]));
    }

    /**
     * @return Variable
     */
    public function getVariableByName(string $name) : Variable
    {
        return $this->variable_map[$name];
    }

    /**
     * @return Variable[]
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
     * @return Scope;
     */
    public function withVariable(Variable $variable) : Scope
    {
        $scope = clone($this);
        $scope->addVariable($variable);
        return $scope;
    }

    /**
     * @return void
     */
    public function addVariable(Variable $variable)
    {
        $this->variable_map[$variable->getName()] = $variable;
    }

    /**
     * @param Variable $variable
     * A variable to add to the set of global variables
     *
     * @return void
     */
    public function addGlobalVariable(Variable $variable)
    {
        \assert(
            $this->hasParentScope(),
            "No global scope available. This should not happen."
        );

        $this->getParentScope()->addGlobalVariable($variable);
    }

    /**
     * @return bool
     * True if a global variable with the given name exists
     */
    public function hasGlobalVariableWithName(string $name) : bool
    {
        \assert(
            $this->hasParentScope(),
            "No global scope available. This should not happen."
        );

        return $this->getParentScope()->hasGlobalVariableWithName(
            $name
        );
    }

    /**
     * @return Variable
     * The global variable with the given name
     */
    public function getGlobalVariableByName(string $name) : Variable
    {
        \assert(
            $this->hasParentScope(),
            "No global scope available. This should not happen."
        );

        return $this->getParentScope()->getGlobalVariableByName($name);
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

        return !empty($this->template_type_map)
            || ($this->hasParentScope() && $this->getParentScope()->hasAnyTemplateType());
    }

    /**
     * @return TemplateType[]
     * The set of all template types parameterizing this generic
     * class
     */
    public function getTemplateTypeMap() : array
    {
        return \array_merge(
            $this->template_type_map,
            $this->hasParentScope()
                ? $this->getParentScope()->getTemplateTypeMap()
                : []
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
        ) || ($this->hasParentScope() ? $this->getParentScope()->hasTemplateType(
            $template_type_identifier
        ) : false);
    }

    /**
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

        \assert(
            $this->hasTemplateType($template_type_identifier),
            "Cannot get template type with identifier $template_type_identifier"
        );

        return $this->template_type_map[$template_type_identifier]
            ?? $this->getParentScope()->getTemplateType(
                $template_type_identifier
            );
    }

    /**
     * @return string
     * A string representation of this scope
     */
    public function __toString() : string
    {
        return $this->getFQSEN() . "\t" . implode(',', $this->getVariableMap());
    }
}
