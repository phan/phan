<?php declare(strict_types = 1);

namespace Phan\Tests\AST;

use Phan\Tests\BaseTest;

use Phan\AST\ASTReverter;
use Phan\Config;

final class ASTReverterTest extends BaseTest
{
    /**
     * @param string $snippet
     * @dataProvider revertShorthandProvider
     */
    public function testRevertShorthand(string $snippet, string $expected = null)
    {
        $expected = $expected ?? $snippet;
        $file_contents = '<' . '?php ' . $snippet . ';';
        $statements = \ast\parse_code($file_contents, Config::AST_VERSION);
        $this->assertSame(1, count($statements->children));
        $snippet_node = $statements->children[0];

        $reverter = new ASTReverter();
        $this->assertSame($expected, $reverter->toShortString($snippet_node));
    }

    public function revertShorthandProvider() : array
    {
        return [
            ["'2'"],
            ['2'],
            ['false'],
            ['null'],
            ['NULL'],
            ['PHP_VERSION_ID'],
            ['\\PHP_VERSION_ID'],
            ['namespace\\PHP_VERSION_ID'],
            ['array(2,3=>4)', '[2,3=>4]'],
            ["['x'=>'var']"],
            ['[2]'],
        ];
    }
}
