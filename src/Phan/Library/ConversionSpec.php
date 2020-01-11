<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * An object representing a conversion specifier of a format string, such as "%1$d".
 * @phan-pure
 */
class ConversionSpec
{
    /** @var string Original text of the directive */
    public $directive;
    /** @var ?int Which argument this refers to, starting from 1 */
    public $position;
    /** @var string Character used for padding (commonly confused with $position) */
    public $padding_char;
    /** @var string indicates which side is used for alignment */
    public $alignment;
    /** @var string minimum width of output */
    public $width;
    /** @var string precision of output */
    public $precision;
    /** @var string Type to print (s,d,f,etc.) */
    public $arg_type;

    /**
     * Create a conversion specifier from a match.
     * @param array{0:string,1:string,2:string,3:string,4:string,5:string,6:string} $match groups in a match.
     */
    protected function __construct(array $match)
    {
        [$this->directive, $position_str, $this->padding_char, $this->alignment, $this->width, $this->precision, $this->arg_type] = $match;
        if ($position_str !== "") {
            $this->position = \intval(\substr($position_str, 0, -1));
        }
    }

    // A padding string regex may be a space or 0.
    // Alternate padding specifiers may be specified by prefixing it with a single quote.
    public const PADDING_STRING_REGEX_PART = '[0 ]?|\'.';

    /**
     * Based on https://secure.php.net/manual/en/function.sprintf.php
     */
    public const FORMAT_STRING_INNER_REGEX_PART =
        '%'  // Every format string begins with a percent
        . '(\d+\$)?'  // Optional n$ position specifier must go immediately after percent
        . '(' . self::PADDING_STRING_REGEX_PART . ')'  // optional padding specifier
        . '([+-]?)' // optional alignment specifier
        . '(\d*)'  // optional width specifier
        . '(\.\d*)?'   // Optional precision specifier in the form of a period followed by an optional decimal digit string
        . '(?:l)?' // Optional length specifier (ignored)
        . '([bcdeEfFgGosuxX])';  // A type specifier


    public const FORMAT_STRING_REGEX = '/%%|' . self::FORMAT_STRING_INNER_REGEX_PART . '/';

    /**
     * Compute the number of additional arguments expected when sprintf is called
     * with a format string of $fmt_str.
     * @param string $fmt_str
     */
    public static function computeExpectedArgumentCount(string $fmt_str): int
    {
        $result = 0;
        foreach (self::extractAll($fmt_str) as $i => $_) {
            $result = \max($result, $i);
        }
        return $result;
    }

    /**
     * Extract a list of directives from a format string.
     * @param string $fmt_str a format string to extract directives from.
     * @return associative-array<int,non-empty-list<ConversionSpec>> array(int position => array of ConversionSpec referring to arg at that position)
     */
    public static function extractAll(string $fmt_str): array
    {
        // echo "format is $fmt_str\n";
        $directives = [];
        \preg_match_all(self::FORMAT_STRING_REGEX, $fmt_str, $matches, \PREG_SET_ORDER);
        $unnamed_count = 0;
        foreach ($matches as $match) {
            if ($match[0] === '%%') {
                continue;
            }
            $directive = new self($match);
            if (!isset($directive->position)) {
                // @phan-suppress-next-line PhanAccessReadOnlyProperty
                $directive->position = ++$unnamed_count;
            }
            $directives[$directive->position][] = $directive;
        }
        \ksort($directives);
        return $directives;
    }

    /**
     * @return string an unambiguous way of referring to this conversion spec.
     */
    public function toCanonicalString(): string
    {
        if ($this->width !== '') {
            $padding_char = $this->padding_char;
            $alignment = $this->alignment;
        } else {
            $padding_char = '';
            $alignment = '';
        }
        return '%' . $this->position . '$' . $padding_char . $alignment . $this->width . $this->precision . $this->arg_type;
    }

    /**
     * @return string the conversion spec if the width was used as a position instead.
     */
    public function toCanonicalStringWithWidthAsPosition(): string
    {
        return '%' . $this->width . '$' . $this->padding_char . $this->alignment . $this->precision . $this->arg_type;
    }
    public const ARG_TYPE_LOOKUP = [
        'b' => 'int',
        'c' => 'int',
        'd' => 'int',
        'e' => 'float',
        'E' => 'float',
        'f' => 'float',
        'F' => 'float',
        'g' => 'float',
        'G' => 'float',
        'o' => 'int',
        's' => 'string',
        'u' => 'int',
        'x' => 'int',
        'X' => 'int',
    ];

    /**
     * @return string the name of the union type expected for the arg for this conversion spec
     */
    public function getExpectedUnionTypeName(): string
    {
        return self::ARG_TYPE_LOOKUP[$this->arg_type] ?? 'string';
    }
}
