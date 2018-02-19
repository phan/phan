<?php declare(strict_types=1);
namespace Phan\Language;

use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;

/**
 * Tracks a `use Foo\Bar;` statement at the top of a class.
 */
class NamespaceMapEntry
{
    /**
     * @var FullyQualifiedGlobalStructuralElement
     */
    public $fqsen;

    /**
     * @var string the original case sensitive name of the use statement
     */
    public $original_name;

    /**
     * @var int the line number of the use statement
     */
    public $lineno;

    /**
     * @var bool
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
}
