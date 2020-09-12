<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\FileRef;
use Phan\Language\UnionType;
use Stringable;

/**
 * Any PHP structural element that also has a type and is
 * addressable such as a class, method, closure, property,
 * constant, variable, ...
 * @suppress PhanRedefinedInheritedInterface this uses a polyfill for Stringable
 */
interface TypedElementInterface extends Stringable
{
    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName(): string;

    /**
     * @return UnionType
     * Get the type of this structural element
     */
    public function getUnionType(): UnionType;

    /**
     * Set the type of this element
     * @param UnionType $type
     */
    public function setUnionType(UnionType $type): void;

    /**
     * @return FileRef
     * A reference to where this element was found
     */
    public function getFileRef(): FileRef;
}
