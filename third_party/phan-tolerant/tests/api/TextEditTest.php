<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use Microsoft\PhpParser\TextEdit;
use PHPUnit\Framework\TestCase;

class TextEditTest extends TestCase {
    const INPUT_TEXT = <<< 'PHP'
<?php

function a () { }

function b () { }
PHP;

    public function testReplaceAllTextInDocument() {
        $content = self::INPUT_TEXT;
        $edits = [
            new TextEdit(0, \strlen($content), $content)
        ];

        $this->assertEquals($content, TextEdit::applyEdits($edits, $content));
    }

    public function testDeleteAllTextFromDocument() {
        $content = self::INPUT_TEXT;
        $edits = [
            new TextEdit(0, \strlen($content), "")
        ];

        $this->assertEquals("", TextEdit::applyEdits($edits, $content));
    }

    public function testAddTextToEndOfDocument() {
        $content = self::INPUT_TEXT;

        $edits = [
            new TextEdit(\strlen($content), 0, "hello")
        ];

        $this->assertEquals($content . "hello", TextEdit::applyEdits($edits, $content));
    }

    public function testAddTextToBeginningOfDocument() {
        $content = self::INPUT_TEXT;

        $edits = [
            new TextEdit(0, 0, "hello")
        ];

        $this->assertEquals("hello" . $content, TextEdit::applyEdits($edits, $content));
    }

    public function testApplyMultipleEdits() {
        $content = self::INPUT_TEXT;

        $expected = <<< 'PHP'
hello
<?php

awesome a () { }

 b () { }nice!
PHP;
        $edits = [
            new TextEdit(0, 0, "hello\n"),
            new TextEdit(7, 8, "awesome"),
            new TextEdit(26, 8, ""),
            new TextEdit(\strlen($content), 0, "nice!")
        ];

        $this->assertEquals($expected, TextEdit::applyEdits($edits, $content));
    }

    public function testApplyingEmptyTextEditArray() {
        $content = self::INPUT_TEXT;

        $this->assertEquals($content, TextEdit::applyEdits([], $content));
    }

    public function testOutOfOrderTextEdits() {
        $content = self::INPUT_TEXT;

        $edits = [
            new TextEdit(0, 10, 10),
            new TextEdit(0, 4 ,3)
        ];
        $this->expectException(AssertionError::class);
        TextEdit::applyEdits($edits, $content);
    }

    public function testOverlappingTextEdits() {
        $content = self::INPUT_TEXT;
        $edits = [
            new TextEdit(0, 4, 10),
            new TextEdit(0, 10, 10)
        ];
        $this->expectException(AssertionError::class);
        TextEdit::applyEdits($edits, $content);
    }

    public function testOutOfBoundsTextEdit() {
        $content = self::INPUT_TEXT;
        $edits = [
            new TextEdit(0, -1, -1)
        ];
        $this->expectException(OutOfBoundsException::class);
        TextEdit::applyEdits($edits, $content);
    }
}
