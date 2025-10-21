<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\Range;
use Microsoft\PhpParser\LineCharacterPosition;
use Microsoft\PhpParser\PositionUtilities;
use PHPUnit\Framework\TestCase;

class PositionUtilitiesTest extends TestCase {

    const text = <<< 'PHP'
hello
there


awesome
PHP;

    public function getRangeFromPositionDataProvider(): array {
        return [
            // Empty range at start
            [0, 0, new Range(new LineCharacterPosition(0, 0), new LineCharacterPosition(0, 0))],
            // Entire range
            [0, 21, new Range(new LineCharacterPosition(0, 0), new LineCharacterPosition(4, 7))],
            // Empty range at end
            [21, 0, new Range(new LineCharacterPosition(4, 7), new LineCharacterPosition(4, 7))],
            // Full line
            [6, 5, new Range(new LineCharacterPosition(1, 0), new LineCharacterPosition(1, 5))],

            [2, 11, new Range(new LineCharacterPosition(0, 2), new LineCharacterPosition(3, 0))],
            [10, 11, new Range(new LineCharacterPosition(1, 4), new LineCharacterPosition(4, 7))]
        ];
    }

    /**
     * @dataProvider getRangeFromPositionDataProvider
     */
    public function testGetRangeFromPosition($position, $length, $expectedRange) {
        $this->assertEquals(
            $expectedRange,
            PositionUtilities::getRangeFromPosition($position, $length, PositionUtilitiesTest::text));
    }

    public function getLineCharacterPositionFromPositionDataProvider(): array {
        return [
            [0, new LineCharacterPosition(0, 0)],
            [6, new LineCharacterPosition(1, 0)],
            [9, new LineCharacterPosition(1, 3)],

            // At EOL
            [5, new LineCharacterPosition(0, 5)],

            // At end
            [21, new LineCharacterPosition(4, 7)]
        ];
    }

    /**
     * @dataProvider getLineCharacterPositionFromPositionDataProvider
     */
    public function testGetLineCharacterPositionFromPosition($position, $expectedLineCharPos) {
        $this->assertEquals(
            $expectedLineCharPos,
            PositionUtilities::getLineCharacterPositionFromPosition($position, PositionUtilitiesTest::text)
        );
    }

    public function testGetLineCharacterPositionFromPosition_Bounds() {
        $text = PositionUtilitiesTest::text;

        $this->assertEquals(
            PositionUtilities::getLineCharacterPositionFromPosition(\strlen($text), $text),
            PositionUtilities::getLineCharacterPositionFromPosition(\strlen($text)+1, $text),
            "Positions greater than text length should resolve to maximum position in text."
        );

        $this->assertEquals(
            PositionUtilities::getLineCharacterPositionFromPosition(0, $text),
            PositionUtilities::getLineCharacterPositionFromPosition(-1, $text),
            "Positions less than zero should resolve to minimum position in text."
        );
    }


    public function testGetLineCharacterPositionFromPosition_AlwaysValid() {
        // Go past the bounds of the string - should still be valid
        for ($i=-3; $i < \strlen(PositionUtilitiesTest::text) + 3; $i++) {
            $lineChar = PositionUtilities::getLineCharacterPositionFromPosition($i, PositionUtilitiesTest::text);
            $this->assertGreaterThanOrEqual(0, $lineChar->line);
            $this->assertLessThanOrEqual(4, $lineChar->line);

            $this->assertGreaterThanOrEqual(0, $lineChar->character);
            $this->assertLessThanOrEqual(7, $lineChar->character);
        }
    }
}
