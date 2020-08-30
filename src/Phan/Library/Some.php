<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * `Some<T>` is a sub-type of `Option<T>` representing an option with a value.
 * @see Option
 *
 * @template T
 * The type of the element. Should implement __toString()
 *
 * @inherits Option<T>
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * @phan-pure
 */
class Some extends Option
{
    /** @var T the value wrapped by this Some<T>*/
    private $_;

    /**
     * @param T $_
     * @phan-file-suppress PhanParamNameIndicatingUnused
     */
    public function __construct($_)
    {
        $this->_ = $_;
    }

    public function isDefined(): bool
    {
        return true;
    }

    /**
     * @return T
     */
    public function get()
    {
        return $this->_;
    }

    /**
     * @param T $else used in the None sibling class (@phan-unused-param)
     * @return T
     */
    public function getOrElse($else)
    {
        return $this->_;
    }

    /**
     * @return string
     * @suppress PhanTypeSuspiciousStringExpression this should be used with T where __toString() is defined.
     * A string representation of this object
     */
    public function __toString(): string
    {
        return 'Some(' . $this->_ . ')';
    }
}
