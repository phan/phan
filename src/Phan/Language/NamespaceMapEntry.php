<?php

declare(strict_types=1);

namespace Phan\Language;

use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use RuntimeException;

/**
 * Tracks a `use Foo\Bar;` statement inside of a namespace.
 */
class NamespaceMapEntry implements \Serializable
{
    /**
     * @var FullyQualifiedGlobalStructuralElement the FQSEN of the namespace map entry
     * @phan-read-only
     */
    public $fqsen;

    /**
     * @var string the original case-sensitive name of the use statement
     * @phan-read-only
     */
    public $original_name;

    /**
     * @var int the line number of the use statement
     * @phan-read-only
     */
    public $lineno;

    /**
     * @var bool has this use statement been referenced during the parse/analysis phase?
     */
    public $is_used = false;

    public function __construct(
        FullyQualifiedGlobalStructuralElement $fqsen,
        string $original_name,
        int $lineno
    ) {
        $this->fqsen = $fqsen;
        $this->original_name = $original_name;
        $this->lineno = $lineno;
    }

    public function serialize(): string
    {
        return \serialize([
            \get_class($this->fqsen),
            (string)$this->fqsen,
            $this->original_name,
            $this->lineno,
            $this->is_used
        ]);
    }

    /**
     * @param string $representation
     * @suppress PhanAccessReadOnlyProperty TODO fix #3179
     * @suppress PhanParamSignatureRealMismatchHasNoParamTypeInternal, PhanUnusedSuppression parameter type widening was allowed in php 7.2, signature changed in php 8
     */
    public function unserialize($representation): void
    {
        [$fqsen_class, $fqsen, $this->original_name, $this->lineno, $this->is_used] = \unserialize($representation);
        if (!\is_string($fqsen_class)) {
            throw new RuntimeException("Failed to unserialize a string from the representation");
        }
        if (!\is_subclass_of($fqsen_class, FullyQualifiedGlobalStructuralElement::class)) {
            // Should not happen
            throw new RuntimeException("Not a global fqsen: class " . $fqsen_class);
        }
        $this->fqsen = $fqsen_class::fromFullyQualifiedString($fqsen);
    }
}
