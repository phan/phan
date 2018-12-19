<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * A textual edit applicable to a text document.
 *
 * TODO: Use this (e.g. for code completions) once we know what position to suggest.
 *
 * @phan-file-suppress PhanWriteOnlyPublicProperty these would be sent to a language client
 */
class TextEdit
{
    /**
     * The range of the text document to be manipulated. To insert
     * text into a document create a range where start === end.
     *
     * @var Range|null
     */
    public $range;

    /**
     * The string to be inserted. For delete operations use an
     * empty string.
     *
     * @var string|null
     */
    public $newText;

    public function __construct(Range $range = null, string $newText = null)
    {
        $this->range = $range;
        $this->newText = $newText;
    }
}
