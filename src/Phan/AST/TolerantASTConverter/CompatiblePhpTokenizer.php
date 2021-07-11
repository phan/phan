<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Microsoft\PhpParser\PhpTokenizer;

use const PHP_VERSION_ID;

/**
 * Like PhpTokenizer but supports the following:
 *
 * 1. Converting older tokens to new token types
 * 2. Supporting new tokens in new php versions not yet released in microsoft/tolerant-php-parser
 *
 * @suppress PhanUndeclaredConstant TODO:
 */
class CompatiblePhpTokenizer extends PhpTokenizer
{
    /** @suppress PhanUndeclaredConstant */
    protected const T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG = PHP_VERSION_ID >= 80100 ? \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG : -1;
    /** @suppress PhanUndeclaredConstant */
    protected const T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG = PHP_VERSION_ID >= 80100 ? \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG : -1;

    /**
     * @return list<string|array{0:int,1:string,2:int}>
     * @override
     */
    protected static function tokenGetAll(string $content, $parseContext): array
    {
        $tokens = parent::tokenGetAll($content, $parseContext);
        if (PHP_VERSION_ID < 80100) {
            return $tokens;
        }
        foreach ($tokens as $i => $token) {
            if (\is_array($token)) {
                switch ($token[0]) {
                    case self::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG:
                    case self::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG:
                        $tokens[$i] = '&';
                        break;
                }
            }
        }
        return $tokens;
    }
}
