<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

/**
 * FilePositionMap can be used to get the line number for a large number of nodes (starting from 1).
 * It works the most efficiently when the requested node is close to the previously requested node.
 *
 * Other designs that weren't chosen:
 * - Precomputing all of the start/end offsets when initializing was slower - Some offsets weren't needed, and walking the tree was slower.
 * - Caching line numbers for previously requested offsets wasn't really necessary, since offsets are usually close together and weren't requested repeatedly.
 */
class FilePositionMap {
    /** @var string the full file contents */
    private $fileContents;

    /** @var int - Precomputed strlen($file_contents) */
    private $fileContentsLength;

    /** @var int the 0-based byte offset of the most recent request for a line number. */
    private $currentOffset;

    /** @var int the 1-based line number for $this->currentOffset (updated whenever currentOffset is updated) */
    private $lineForCurrentOffset;

    public function __construct(string $file_contents) {
        $this->fileContents = $file_contents;
        $this->fileContentsLength = \strlen($file_contents);
        $this->currentOffset = 0;
        $this->lineForCurrentOffset = 1;
    }

    /**
     * @param Node|Token $node
     */
    public function getStartLine($node) : int {
        return $this->getLineNumberForOffset($node->getStartPosition());
    }

    /**
     * @param Node|Token $node
     * Similar to getStartLine but includes the column
     */
    public function getStartLineCharacterPositionForOffset($node) : LineCharacterPosition {
        return $this->getLineCharacterPositionForOffset($node->getStartPosition());
    }

    /** @param Node|Token $node */
    public function getEndLine($node) : int {
        return $this->getLineNumberForOffset($node->getEndPosition());
    }

    /**
     * @param Node|Token $node
     * Similar to getStartLine but includes the column
     */
    public function getEndLineCharacterPosition($node) : LineCharacterPosition {
        return $this->getLineCharacterPositionForOffset($node->getEndPosition());
    }

    /**
     * @param int $offset
     * Similar to getStartLine but includes both the line and the column
     */
    public function getLineCharacterPositionForOffset(int $offset) : LineCharacterPosition {
        $line = $this->getLineNumberForOffset($offset);
        $character = $this->getColumnForOffset($offset);
        return new LineCharacterPosition($line, $character);
    }

    /**
     * @param int $offset - A 0-based byte offset
     * @return int - gets the 1-based line number for $offset
     */
    public function getLineNumberForOffset(int $offset) : int {
        if ($offset < 0) {
            $offset = 0;
        } elseif ($offset > $this->fileContentsLength) {
            $offset = $this->fileContentsLength;
        }
        $currentOffset = $this->currentOffset;
        if ($offset > $currentOffset) {
            $this->lineForCurrentOffset += \substr_count($this->fileContents, "\n", $currentOffset, $offset - $currentOffset);
            $this->currentOffset = $offset;
        } elseif ($offset < $currentOffset) {
            $this->lineForCurrentOffset -= \substr_count($this->fileContents, "\n", $offset, $currentOffset - $offset);
            $this->currentOffset = $offset;
        }
        return $this->lineForCurrentOffset;
    }

    /**
     * @param int $offset - A 0-based byte offset
     * @return int - gets the 1-based column number for $offset
     */
    public function getColumnForOffset(int $offset) : int {
        $length = $this->fileContentsLength;
        if ($offset <= 1) {
            return 1;
        } elseif ($offset > $length) {
            $offset = $length;
        }
        // Postcondition: offset >= 1, ($lastNewlinePos < $offset)
        // If there was no previous newline, lastNewlinePos = 0

        // Start strrpos check from the character before the current character,
        // in case the current character is a newline.
        $lastNewlinePos = \strrpos($this->fileContents, "\n", -$length + $offset - 1);
        return 1 + $offset - ($lastNewlinePos === false ? 0 : $lastNewlinePos + 1);
    }
}
