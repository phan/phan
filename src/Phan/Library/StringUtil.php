<?php
declare(strict_types=1);

namespace Phan\Library;

/**
 * StringUtil contains methods to simplify working with strings in Phan and its plugins.
 */
class StringUtil
{
    /**
     * Encode a value in a compact, unambiguous representation for emitted issues.
     * The encoder used by encodeValue may change.
     *
     * @param string|int|float|bool|null $value
     */
    public static function encodeValue($value) : string
    {
        return \var_export($value, true);
    }

    /**
     * Encode a list of values in a compact, unambiguous representation for emitted issues.
     * @param array<int,string|int|float|bool> $values
     */
    public static function encodeValueList(string $separator, array $values) : string
    {
        return \implode(
            $separator,
            \array_map([self::class, 'encodeValue'], $values)
        );
    }
}
