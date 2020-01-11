<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use AssertionError;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Scope;

/**
 * Phan's representation of the scope within a class declaration.
 */
class ClassScope extends ClosedScope
{
    public const IN_CLASS_OR_PROPERTY_SCOPE = Scope::IN_CLASS_LIKE_SCOPE | Scope::IN_PROPERTY_SCOPE;

    public function __construct(
        Scope $parent_scope,
        FullyQualifiedClassName $fqsen,
        int $ast_flags
    ) {
        $this->parent_scope = $parent_scope;
        $this->fqsen = $fqsen;
        $flags = ($parent_scope->flags & ~self::IN_CLASS_OR_PROPERTY_SCOPE) | Scope::IN_CLASS_SCOPE;
        if ($ast_flags & \ast\flags\CLASS_TRAIT) {
            $flags |= Scope::IN_TRAIT_SCOPE;
        } elseif ($ast_flags & \ast\flags\CLASS_INTERFACE) {
            $flags |= Scope::IN_INTERFACE_SCOPE;
        }
        $this->flags = $flags;
    }

    /**
     * @return bool
     * True if we're in a class scope
     * @override
     */
    public function isInClassScope(): bool
    {
        return true;
    }

    /**
     * @return bool
     * True if we're in a class scope
     * @override
     */
    public function isInPropertyScope(): bool
    {
        return false;
    }

    /**
     * @return FullyQualifiedClassName
     * Get the FullyQualifiedClassName of the class whose scope
     * we're in.
     */
    public function getClassFQSEN(): FullyQualifiedClassName
    {
        if ($this->fqsen instanceof FullyQualifiedClassName) {
            return $this->fqsen;
        }

        throw new AssertionError("FQSEN must be a FullyQualifiedClassName");
    }

    /**
     * @return FullyQualifiedClassName
     * Get the FullyQualifiedClassName of the class whose scope
     * we're in. This subclass does not return null.
     */
    public function getClassFQSENOrNull(): FullyQualifiedClassName
    {
        if ($this->fqsen instanceof FullyQualifiedClassName) {
            return $this->fqsen;
        }

        throw new AssertionError("FQSEN must be a FullyQualifiedClassName");
    }
}
