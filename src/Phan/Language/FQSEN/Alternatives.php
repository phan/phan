<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

use Phan\Language\FQSEN;

/**
 * This trait allows an FQSEN to have an alternate ID for when
 * there are multiple colliding definitions of the same name.
 * An alternate ID will be appended to a name such as in
 * `\Name\Space\class,1` or `\Name\Space\class::function,1`
 * or when composed as `\Name\Space\class,1::function,1`.
 */
trait Alternatives
{

    /**
     * Implementers must have a getName() method
     */
    abstract public function getName(): string;

    /**
     * Implementers should use the \Phan\Memoize trait
     */
    abstract protected function memoizeFlushAll(): void;

    /**
     * @var int
     * An alternate ID for the element for use when
     * there are multiple definitions of the element
     */
    protected $alternate_id = 0;

    /**
     * @return int
     * An alternate identifier associated with this
     * FQSEN or zero if none if this is not an
     * alternative.
     */
    public function getAlternateId(): int
    {
        return $this->alternate_id;
    }

    /**
     * @return string
     * Get the name of this element with its alternate id
     * attached
     */
    public function getNameWithAlternateId(): string
    {
        if ($this->alternate_id) {
            return "{$this->getName()},{$this->alternate_id}";
        }

        return $this->getName();
    }

    /**
     * @return bool
     * True if this is an alternate
     */
    public function isAlternate(): bool
    {
        return (0 !== $this->alternate_id);
    }

    /**
     * @return static
     * A FQSEN with the given alternate_id set
     * @suppress PhanTypeMismatchDeclaredReturn (static vs FQSEN)
     */
    abstract public function withAlternateId(
        int $alternate_id
    ): FQSEN;

    /**
     * @return static
     * Get the canonical (non-alternate) FQSEN associated
     * with this FQSEN
     *
     * @suppress PhanTypeMismatchReturn (Alternatives is a trait, cannot directly implement the FQSEN interface. Related to #800)
     */
    public function getCanonicalFQSEN()
    {
        if ($this->alternate_id === 0) {
            return $this;
        }

        return $this->withAlternateId(0);
    }
}
