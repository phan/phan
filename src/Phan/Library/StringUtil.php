<?php
declare(strict_types=1);

namespace Phan\Library;

use Phan\AST\ASTReverter;

/**
 * StringUtil contains methods to simplify working with strings in Phan and its plugins.
 */
class StringUtil
{
    /**
     * Encode a scalar value in a compact, unambiguous representation for emitted issues.
     * The encoder used by encodeValue may change.
     * This aims to fit on a single line.
     *
     * @param string|int|float|bool|null $value
     */
    public static function encodeValue($value) : string
    {
        if (\is_string($value) && \preg_match('/([\0-\15\16-\37])/', $value)) {
            // Use double quoted strings if this contains newlines, tabs, control characters, etc.
            return '"' . ASTReverter::escapeInnerString($value, '"') . '"';
        }
        return \var_export($value, true);
    }

    /**
     * JSON encodes a value - Guaranteed to return a string.
     * @param string|int|float|bool|null|array|object $value
     */
    public static function jsonEncode($value) : string
    {
        $result = \json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        return \is_string($result) ? $result : '(invalid data)';
    }

    /**
     * Encode a list of values in a compact, unambiguous representation for emitted issues.
     * @param list<string|int|float|bool> $values
     */
    public static function encodeValueList(string $separator, array $values) : string
    {
        return \implode(
            $separator,
            \array_map([self::class, 'encodeValue'], $values)
        );
    }

    /**
     * Coerce $str to valid utf-8
     */
    public static function asUtf8(string $str) : string
    {
        return \mb_convert_encoding($str, 'UTF-8', 'UTF-8') ?: $str;
    }

    /**
     * Coerce $str to valid utf-8 and replace newlines with placeholders
     */
    public static function asSingleLineUtf8(string $str) : string
    {
        if (!\preg_match("@[\\n\\r\x80-\xff]@", $str)) {
            // Around 5x faster for the common case of being ASCII without newlines.
            return $str;
        }
        return \str_replace(["\n", "\r"], "�", self::asUtf8($str));
    }
}
