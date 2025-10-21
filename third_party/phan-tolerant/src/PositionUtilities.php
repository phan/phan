<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class PositionUtilities {
    /**
     * Gets a Range from 0-indexed position into $text.

     * Out of bounds positions are handled gracefully. Positions greater than the length of text length
     * are resolved to the end of the text, and negative positions are resolved to the beginning.
     */
    public static function getRangeFromPosition(int $pos, int $length, string $text): Range {
        $start = self::getLineCharacterPositionFromPosition($pos, $text);
        $end = self::getLineCharacterPositionFromPosition($pos + $length, $text);

        return new Range($start, $end);
    }

    /**
     * Gets 0-indexed LineCharacterPosition from 0-indexed position into $text.
     *
     * Out of bounds positions are handled gracefully. Positions greater than the length of text length
     * are resolved to text length, and negative positions are resolved to 0.
     * TODO consider throwing exception instead.
     */
    public static function getLineCharacterPositionFromPosition(int $pos, string $text) : LineCharacterPosition {
        $textLength = \strlen($text);
        if ($pos >= $textLength) {
            $pos = $textLength;
        } elseif ($pos < 0) {
            $pos = 0;
        }

        // Start strrpos check from the character before the current character,
        // in case the current character is a newline
        $startAt = max(-($textLength - $pos) - 1, -$textLength);
        $lastNewlinePos = \strrpos($text, "\n", $startAt);
        $char = $pos - ($lastNewlinePos === false ? 0 : $lastNewlinePos + 1);
        $line = $pos > 0 ? \substr_count($text, "\n", 0, $pos) : 0;
        return new LineCharacterPosition($line, $char);
    }
}
