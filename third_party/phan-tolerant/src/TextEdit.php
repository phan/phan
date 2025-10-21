<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class TextEdit {
    /** @var int */
    public $start;

    /** @var int */
    public $length;

    /** @var string */
    public $content;

    public function __construct(int $start, int $length, string $content) {
        $this->start = $start;
        $this->length = $length;
        $this->content = $content;
    }

    /**
     * Applies array of edits to the document, and returns the resulting text.
     * Supplied $edits must not overlap, and be ordered by increasing start position.
     *
     * Note that after applying edits, the original AST should be invalidated.
     *
     * @param TextEdit[] $edits
     * @param string $text
     * @return string
     */
    public static function applyEdits(array $edits, string $text) : string {
        $prevEditStart = PHP_INT_MAX;
        for ($i = \count($edits) - 1; $i >= 0; $i--) {
            $edit = $edits[$i];
            \assert(
                $prevEditStart > $edit->start && $prevEditStart > $edit->start + $edit->length,
                "Supplied TextEdit[] must not overlap, and be in increasing start position order."
            );
            if ($edit->start < 0 || $edit->length < 0 || $edit->start + $edit->length > \strlen($text)) {
                throw new \OutOfBoundsException("Applied TextEdit range out of bounds.");
            }
            $prevEditStart = $edit->start;
            $head = \substr($text, 0, $edit->start);
            $tail = \substr($text, $edit->start + $edit->length);
            $text = $head . $edit->content . $tail;
        }
        return $text;
    }
}
