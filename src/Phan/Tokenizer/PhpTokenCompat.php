<?php

declare(strict_types=1);

namespace Phan\Tokenizer;

use PhpToken;

/**
 * Compatibility wrapper for PHP tokenization using PhpToken::tokenize()
 * instead of the deprecated token_get_all() function.
 *
 * This class provides static methods to tokenize PHP code and convert
 * between PhpToken objects and the legacy token_get_all() array format.
 */
class PhpTokenCompat
{
    /**
     * Tokenize PHP code and return tokens.
     *
     * This replaces token_get_all() with PhpToken::tokenize() for PHP 8.0+
     * All returned tokens are PhpToken objects (no mixed array/string returns).
     *
     * @param string $code The PHP code to tokenize
     * @param int $flags Optional flags (TOKEN_PARSE, etc)
     * @return list<PhpToken>
     */
    public static function tokenize(string $code, int $flags = 0): array
    {
        return PhpToken::tokenize($code, $flags);
    }

    /**
     * Get the token type ID from a token (supports both PhpToken and legacy array format)
     *
     * @param PhpToken|array{0:int,1:string,2:int}|string|mixed $token
     * @return int|null The token ID, or null if not a valid token
     */
    public static function getTokenId(mixed $token): ?int
    {
        if ($token instanceof PhpToken) {
            return $token->id;
        }
        if (is_array($token) && isset($token[0]) && is_int($token[0])) {
            return $token[0];
        }
        return null;
    }

    /**
     * Get the token text from a token
     *
     * @param PhpToken|array{0:int,1:string,2:int}|string|mixed $token
     * @return string The token text/value
     */
    public static function getTokenText(mixed $token): string
    {
        if ($token instanceof PhpToken) {
            return $token->text;
        }
        if (is_array($token) && isset($token[1]) && is_string($token[1])) {
            return $token[1];
        }
        if (is_string($token)) {
            return $token;
        }
        return '';
    }

    /**
     * Get the line number from a token
     *
     * @param PhpToken|array{0:int,1:string,2:int}|mixed $token
     * @return int The line number
     */
    public static function getTokenLine(mixed $token): int
    {
        if ($token instanceof PhpToken) {
            return $token->line;
        }
        if (is_array($token) && isset($token[2]) && is_int($token[2])) {
            return $token[2];
        }
        return 1;
    }

    /**
     * Check if a token matches a specific token type constant
     *
     * @param PhpToken|array{0:int,1:string,2:int}|mixed $token
     * @param int $kind The token type constant (e.g., T_COMMENT)
     * @return bool True if the token matches the given kind
     */
    public static function isTokenKind(mixed $token, int $kind): bool
    {
        if ($token instanceof PhpToken) {
            return $token->is($kind);
        }
        if (is_array($token) && isset($token[0]) && is_int($token[0])) {
            return $token[0] === $kind;
        }
        return false;
    }

    /**
     * Check if a token is an "array" token (not a single character)
     *
     * With token_get_all(), tokens are returned as either:
     * - array{0:int,1:string,2:int} for named tokens
     * - string for single-character tokens
     *
     * With PhpToken::tokenize(), all tokens are PhpToken objects.
     * This method is for compatibility when the old code checks !is_array($token)
     *
     * @param PhpToken|array|string|mixed $token
     * @return bool True if the token is a named token (PhpToken or array), false for single-char strings
     */
    public static function isArrayToken(mixed $token): bool
    {
        if ($token instanceof PhpToken) {
            return true;
        }
        return is_array($token);
    }
}
