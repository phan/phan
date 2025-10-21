<?php

namespace Microsoft\PhpParser\Tests\Unit\Node;

use Generator;
use Microsoft\PhpParser\Node\StringLiteral;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;

class StringLiteralTest extends TestCase
{
    /**
     * @dataProvider provideGetStringContentsText
     */
    public function testGetStringContentsText(string $source, string $expected): void
    {
        $stringLiteral = (new Parser())->parseSourceFile($source)->getFirstDescendantNode(StringLiteral::class);
        self::assertInstanceOf(StringLiteral::class, $stringLiteral);
        self::assertEquals($expected, $stringLiteral->getStringContentsText());
    }

    /**
     * @return Generator<string,array{string,string}>
     */
    public function provideGetStringContentsText(): Generator
    {
        yield 'empty string double quotes' => [
            '<?php "";',
            '',
        ];
        yield 'empty string single quotes' => [
            "<?php '';",
            '',
        ];
        yield 'string double quotes' => [
            '<?php "hello world";',
            'hello world',
        ];
        yield 'string single quotes' => [
            "<?php 'hello world';",
            'hello world',
        ];
        yield 'string that starts and ends with double quotes' => [
            '<?php \'"hello world"\'',
            '"hello world"',
        ];
        yield 'string that starts and ends with single quotes' => [
            '<?php "\'hello world\'"',
            '\'hello world\'',
        ];
        yield 'backtick string' => [
            '<?php `hello world`',
            'hello world',
        ];
    }
}
