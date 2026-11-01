<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope;

/**
 * Represents the Scope of a closure declaration, used by a Closure's Context.
 *
 * TODO: Wrap this with a ClosureLikeScope
 */
class ClosureScope extends FunctionLikeScope
{
    public function __construct(
        Scope $parent_scope,
        FullyQualifiedFunctionName $fqsen
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
        $this->flags = $parent_scope->flags | Scope::IN_FUNCTION_LIKE_SCOPE;
    }

    /**
     * The optional FQSEN of an (at)phan-closure-scope annotation. (an annotation used for closures that will be bound to a different class)
     * @var FullyQualifiedClassName|null
     */
    private $override_class_fqsen = null;

    /**
     * Override the class FQSEN inside this closure's scope (with an (at)phan-closure-scope annotation).
     */
    public function overrideClassFQSEN(?FullyQualifiedClassName $fqsen = null): void
    {
        // If there is an FQSEN override, then this is in a class scope.
        // If there is no override, then this inherits the class of the parent
        $this->bumpStateVersion();
        if ($fqsen) {
            $this->flags |= Scope::IN_CLASS_SCOPE;
        } else {
            if (!($this->parent_scope->flags & Scope::IN_CLASS_SCOPE)) {
                // This is probably a closure FQSEN with an missing class override outside of a method scope.
                $this->flags &= ~Scope::IN_CLASS_SCOPE;
            }
        }
        $this->override_class_fqsen = $fqsen;
    }

    /**
     * Whether a `$this` is available inside the overridden scope.
     * False when the closure was bound with `Closure::bind($closure, null, SomeClass::class)`: the scope of
     * SomeClass applies, but there is no object.
     * @var bool
     */
    private $override_has_this = true;

    /**
     * Record whether a `$this` is available inside the overridden scope.
     */
    public function setOverrideHasThis(bool $has_this): void
    {
        $this->override_has_this = $has_this;
    }

    /**
     * Whether a `$this` is available inside the overridden scope (true unless the closure was bound to null).
     */
    public function hasOverrideThis(): bool
    {
        return $this->override_has_this;
    }

    /**
     * @return FullyQualifiedClassName|null
     * If the (at)phan-closure-scope annotation is used, returns the corresponding override class FQSEN.
     * Returns the class FQSEN inside this closure's scope (with an (at)phan-closure-scope annotation).
     */
    public function getOverrideClassFQSEN(): ?FullyQualifiedClassName
    {
        return $this->override_class_fqsen;
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN(): FullyQualifiedClassName
    {
        return $this->override_class_fqsen ?? parent::getClassFQSEN();
    }

    /**
     * @return ?FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSENOrNull(): ?FullyQualifiedClassName
    {
        return $this->override_class_fqsen ?? parent::getClassFQSENOrNull();
    }
}
