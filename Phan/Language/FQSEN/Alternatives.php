<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use \Phan\Language\FQSEN;

/**
 * A Fully-Qualified Global Structural Element
 */
trait Alternatives {
    /**
     * @var int
     * An alternate ID for the elemnet for use when
     * there are multiple definitions of the element
     */
    protected $alternate_id = 0;

    /**
     * @return int
     * An alternate identifier associated with this
     * FQSEN or zero if none if this is not an
     * alternative.
     */
    public function getAlternateId() : int {
        return $this->alternate_id;
    }

    /**
     * @return bool
     * True if this is an alternate
     */
    public function isAlternate() : bool {
        return (0 !== $this->alternate_id);
    }

    /**
     * @return FQSEN
     * A FQSEN with the given alternate_id set
     */
    public function withAlternateId(
        int $alternate_id
    ) : FQSEN {
        if ($this->alternate_id === $alternate_id) {
            return $this;
        }

        $fqsen = clone($this);
        $fqsen->alternate_id = $alternate_id;
        return $fqsen;
    }

    /**
     * @return FQSEN
     * Get the canonical (non-alternate) FQSEN associated
     * with this FQSEN
     */
    public function getCanonicalFQSEN() : FQSEN {
        if ($this->alternate_id == 0) {
            return $this;
        }

        return $this->withAlternateId(0);
    }
}
