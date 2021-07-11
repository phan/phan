<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\TokenStreamProviderInterface;

use const PHP_VERSION_ID;

/**
 * Tokenizes content using PHP's built-in `token_get_all`, and converts to "lightweight" Token representation.
 *
 * Initially we tried hand-spinning the lexer (see `experiments/Lexer.php`), but we had difficulties optimizing
 * performance (especially when working with Unicode characters.)
 *
 * Class PhpTokenizer
 * @package Microsoft\PhpParser
 * @suppress PhanUndeclaredConstant TODO: Make it only necessary on the class constant declaration
 */
class CompatibleParser extends Parser
{
    /**
     * Create a parser to accommodate edge cases in the current php minor version and tolerant-php-parser version
     */
    public static function create(): Parser
    {
        if (PHP_VERSION_ID >= 80100) {
            return new self();
        }
        return new Parser();
    }

    /**
     * @override
     */
    protected function makeLexer(string $fileContents): TokenStreamProviderInterface
    {
        return new CompatiblePhpTokenizer($fileContents);
    }
}
