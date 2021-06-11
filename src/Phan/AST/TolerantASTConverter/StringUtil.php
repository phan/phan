<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use function chr;
use function hexdec;
use function is_string;
use function octdec;
use function preg_replace;
use function str_replace;
use function strpos;
use function strrpos;
use function strspn;
use function substr;

/**
 * This class is based on code from https://github.com/nikic/PHP-Parser/blob/master/lib/PhpParser/Node/Scalar/String_.php
 *
 * Original License
 * ----------------
 *
 * Copyright (c) 2011-2018 by Nikita Popov.
 *
 * Some rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above
 *       copyright notice, this list of conditions and the following
 *       disclaimer in the documentation and/or other materials provided
 *       with the distribution.
 *
 *     * The names of the contributors may not be used to endorse or
 *       promote products derived from this software without specific
 *       prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
final class StringUtil
{
    private const REPLACEMENTS = [
        '\\' => '\\',
        '$'  =>  '$',
        'n'  => "\n",
        'r'  => "\r",
        't'  => "\t",
        'f'  => "\f",
        'v'  => "\v",
        'e'  => "\x1B",
    ];

    /**
     * @internal
     *
     * Parses a string token.
     *
     * @param string $str String token content
     *
     * @return string The parsed string
     */
    public static function parse(string $str): string
    {
        if ($str === '') {
            return '';
        }
        $c = $str[0];
        if ($c === '<') {
            return self::parseHeredoc($str);
        }
        $binary_length = 0;
        if ('b' === $c || 'B' === $c) {
            $binary_length = 1;
        }

        if ('\'' === $str[$binary_length]) {
            return str_replace(
                ['\\\\', '\\\''],
                ['\\', '\''],
                // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
                substr($str, $binary_length + 1, -1)
            );
        } else {
            return self::parseEscapeSequences(
                substr($str, $binary_length + 1, -1),
                '"'
            );
        }
    }

    /**
     * Converts a fragment of raw (possibly indented)
     * heredoc to the string that the PHP interpreter would treat it as.
     */
    public static function parseHeredoc(string $str): string
    {
        // TODO: handle dos newlines
        // TODO: Parse escape sequences
        $first_line_index = (int)strpos($str, "\n");
        $last_line_index = (int)strrpos($str, "\n");
        // $last_line = substr($str, $last_line_index + 1);
        $spaces = strspn($str, " \t", $last_line_index + 1);

        // On Windows, the "\r" must also be removed from the last line of the heredoc
        $inner = (string)substr($str, $first_line_index + 1, $last_line_index - ($first_line_index + 1) - ($str[$last_line_index - 1] === "\r" ? 1 : 0));

        if ($spaces > 0) {
            $inner = preg_replace("/^" . substr($str, $last_line_index + 1, $spaces) . "/m", '', $inner);
        }
        if (strpos(substr($str, 0, $first_line_index), "'") === false) {
            // If the start of the here/nowdoc doesn't contain a "'", it's heredoc.
            // The contents have to be unescaped.
            return self::parseEscapeSequences($inner, null);
        }
        return $inner;
    }

    /**
     * Parses escape sequences in strings (all string types apart from single quoted).
     *
     * @param string|false $str  String without quotes
     * @param null|string $quote Quote type
     *
     * @return string String with escape sequences parsed
     * @throws InvalidNodeException for invalid code points
     */
    public static function parseEscapeSequences($str, ?string $quote): string
    {
        if (!is_string($str)) {
            // Invalid AST input; give up
            return '';
        }
        if (null !== $quote) {
            $str = str_replace('\\' . $quote, $quote, $str);
        }

        return \preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            /**
             * @param list<string> $matches
             */
            static function (array $matches): string {
                $str = $matches[1];

                if (isset(self::REPLACEMENTS[$str])) {
                    return self::REPLACEMENTS[$str];
                } elseif ('x' === $str[0] || 'X' === $str[0]) {
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal, PhanPossiblyFalseTypeArgumentInternal
                    return chr(hexdec(substr($str, 1)));
                } elseif ('u' === $str[0]) {
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                    return self::codePointToUtf8(hexdec($matches[2]));
                } else {
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                    return chr(octdec($str));
                }
            },
            $str
        );
    }

    /**
     * Converts a Unicode code point to its UTF-8 encoded representation.
     *
     * @param int $num Code point
     *
     * @return string UTF-8 representation of code point
     *
     * @throws InvalidNodeException for invalid code points
     */
    private static function codePointToUtf8(int $num): string
    {
        if ($num <= 0x7F) {
            return chr($num);
        }
        if ($num <= 0x7FF) {
            return chr(($num >> 6) + 0xC0) . chr(($num & 0x3F) + 0x80);
        }
        if ($num <= 0xFFFF) {
            return chr(($num >> 12) + 0xE0) . chr((($num >> 6) & 0x3F) + 0x80) . chr(($num & 0x3F) + 0x80);
        }
        if ($num <= 0x1FFFFF) {
            return chr(($num >> 18) + 0xF0) . chr((($num >> 12) & 0x3F) + 0x80)
                 . chr((($num >> 6) & 0x3F) + 0x80) . chr(($num & 0x3F) + 0x80);
        }
        throw new InvalidNodeException('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
    }
}
