<?php declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use function chr;
use function hexdec;
use function octdec;

use Error;

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
    const REPLACEMENTS = [
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
     * @param bool $parse_unicode_escape Whether to parse PHP 7 \u escapes
     *
     * @return string The parsed string
     */
    public static function parse(string $str, bool $parse_unicode_escape = true) : string
    {
        $binary_length = 0;
        if ('b' === $str[0] || 'B' === $str[0]) {
            $binary_length = 1;
        }

        if ('\'' === $str[$binary_length]) {
            return \str_replace(
                ['\\\\', '\\\''],
                ['\\', '\''],
                \substr($str, $binary_length + 1, -1)
            );
        } else {
            return self::parseEscapeSequences(
                substr($str, $binary_length + 1, -1),
                '"',
                $parse_unicode_escape
            );
        }
    }

    /**
     * @internal
     *
     * Parses escape sequences in strings (all string types apart from single quoted).
     *
     * @param string      $str   String without quotes
     * @param null|string $quote Quote type
     * @param bool $parse_unicode_escape Whether to parse PHP 7 \u escapes
     *
     * @return string String with escape sequences parsed
     */
    public static function parseEscapeSequences(string $str, $quote, bool $parse_unicode_escape = true) : string
    {
        if (null !== $quote) {
            $str = \str_replace('\\' . $quote, $quote, $str);
        }

        $extra = '';
        if ($parse_unicode_escape) {
            $extra = '|u\{([0-9a-fA-F]+)\}';
        }

        return \preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}' . $extra . ')~',
            /**
             * @param array<int,string> $matches
             * @return string
             */
            function ($matches) {
                $str = $matches[1];

                if (isset(self::REPLACEMENTS[$str])) {
                    return self::REPLACEMENTS[$str];
                } elseif ('x' === $str[0] || 'X' === $str[0]) {
                    return chr(hexdec($str));
                } elseif ('u' === $str[0]) {
                    return self::codePointToUtf8(hexdec($matches[2]));
                } else {
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
     * @throws \Error for invalid code points
     */
    private static function codePointToUtf8(int $num) : string
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
        throw new Error('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
    }
}
