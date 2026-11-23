<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope;

/**
 * The scope of the body of a property hook (`get` or `set`, PHP 8.4+).
 *
 * It is a branch of the scope of the property instead of a function-like scope,
 * because hooks are not functions with an FQSEN in the code base.
 */
class PropertyHookScope extends BranchScope
{
    /** @var string the name of the hook: 'get' or 'set' */
    private $hook_name;

    /** @var FullyQualifiedPropertyName the property the hook belongs to */
    private $property_fqsen;

    public function __construct(Scope $scope, FullyQualifiedPropertyName $property_fqsen, string $hook_name)
    {
        parent::__construct($scope);
        $this->property_fqsen = $property_fqsen;
        $this->hook_name = $hook_name;
    }

    /**
     * @return string the name of the hook: 'get' or 'set'
     */
    public function getHookName(): string
    {
        return $this->hook_name;
    }

    /**
     * @return FullyQualifiedPropertyName the property the hook belongs to
     */
    public function getHookPropertyFQSEN(): FullyQualifiedPropertyName
    {
        return $this->property_fqsen;
    }

    /**
     * @override
     */
    public function getPropertyHookScopeOrNull(): ?PropertyHookScope
    {
        return $this;
    }
}
