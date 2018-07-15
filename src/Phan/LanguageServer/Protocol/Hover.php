<?php
declare(strict_types = 1);

namespace Phan\LanguageServer\Protocol;

/**
 * The result of a hover request.
 * @phan-file-suppress PhanWriteOnlyPublicProperty
 */
class Hover
{
    /**
     * @var MarkupContent The hover's content
     */
    public $contents;

    /**
     * @var Range|null an optional range inside a text document
     * that is used to visualize a hover, e.g. by changing the background color.
     */
    public $range;

    public function __construct(MarkupContent $contents, Range $range = null)
    {
        $this->contents = $contents;
        $this->range = $range;
    }
}
