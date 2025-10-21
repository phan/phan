<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\FilePositionMap;
use Microsoft\PhpParser\LineCharacterPosition;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;
use PHPUnit\Framework\TestCase;

class FilePositionMapTest extends TestCase {
    public function expectLineCharacterPositionEquals(int $line, int $character, LineCharacterPosition $position) {
        $this->assertSame($line, $position->line, "Expected same line");
        $this->assertSame($character, $position->character, "Expected same character");
    }

    /**
     * The map keeps the current offset and line -
     * Move the requests forward, backwards, and to the same position as the previous request to verify that this is done properly.
     */
    public function testLineCharacterPosition() {
        $map = new FilePositionMap("foo\n\nbar\n");
        $this->expectLineCharacterPositionEquals(1, 1, $map->getLineCharacterPositionForOffset(0));
        $this->expectLineCharacterPositionEquals(1, 1, $map->getLineCharacterPositionForOffset(-1));
        $this->expectLineCharacterPositionEquals(1, 3, $map->getLineCharacterPositionForOffset(2));
        $this->expectLineCharacterPositionEquals(1, 4, $map->getLineCharacterPositionForOffset(3));
        $this->expectLineCharacterPositionEquals(2, 1, $map->getLineCharacterPositionForOffset(4));
        $this->expectLineCharacterPositionEquals(1, 4, $map->getLineCharacterPositionForOffset(3));
        $this->expectLineCharacterPositionEquals(3, 1, $map->getLineCharacterPositionForOffset(5));
        $this->expectLineCharacterPositionEquals(3, 4, $map->getLineCharacterPositionForOffset(8));
        $this->expectLineCharacterPositionEquals(3, 4, $map->getLineCharacterPositionForOffset(8));
        $this->expectLineCharacterPositionEquals(4, 1, $map->getLineCharacterPositionForOffset(9));
        $this->expectLineCharacterPositionEquals(4, 1, $map->getLineCharacterPositionForOffset(12));
        $this->expectLineCharacterPositionEquals(1, 4, $map->getLineCharacterPositionForOffset(3));
    }

    /**
     * The map keeps the current offset and line -
     * Move the requests forward, backwards, and to the same position as the previous request to verify that this is done properly.
     */
    public function testLineNumber() {
        $map = new FilePositionMap("foo\n\nbar\n");
        $this->assertSame(1, $map->getLineNumberForOffset(0));
        $this->assertSame(1, $map->getLineNumberForOffset(-1));
        $this->assertSame(1, $map->getLineNumberForOffset(2));
        $this->assertSame(1, $map->getLineNumberForOffset(3));
        $this->assertSame(2, $map->getLineNumberForOffset(4));
        $this->assertSame(3, $map->getLineNumberForOffset(5));
        $this->assertSame(3, $map->getLineNumberForOffset(8));
        $this->assertSame(3, $map->getLineNumberForOffset(8));
        $this->assertSame(4, $map->getLineNumberForOffset(9));
        $this->assertSame(4, $map->getLineNumberForOffset(12));
        $this->assertSame(1, $map->getLineNumberForOffset(3));
        $this->assertSame(2, $map->getLineNumberForOffset(4));
    }
}
