<?php declare(strict_types=1);

namespace Phan\Library;

use InvalidArgumentException;
use function strlen;
use function strpos;

/**
 * This contains a heuristic for guessing the offsets and groups that are possible for a given regular expression.
 *
 * This may not be aware of all edge cases.
 */
class RegexKeyExtractor
{
    /**
     * @var string the inner pattern of the regular expression
     */
    private $pattern;

    /**
     * @var int the byte offset in $this->pattern
     */
    private $offset = 0;

    /**
     * @var associative-array<string|int,true> the offsets or names of patterns
     */
    private $matches = [];

    private function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @param string|mixed $regex
     * @return array<string|int,true> the best guess at the keys that would be parsed into $matches by preg_match, based on the regex and flags passed to preg_match
     * @throws InvalidArgumentException if the regex could not be parsed by these heuristics
     */
    public static function getKeys($regex) : array
    {
        if (!\is_string($regex)) {
            throw new InvalidArgumentException("regex is not a string");
        }
        $inner_pattern = self::extractInnerRegexPattern($regex);

        $matcher = new self($inner_pattern . ')');
        $matcher->extractGroup();
        $expected_length = strlen($inner_pattern);
        $parsed_length = $matcher->offset - 1;

        if ($parsed_length !== $expected_length) {
            throw new InvalidArgumentException("Only matched $parsed_length of $expected_length for '$inner_pattern'");
        }
        return $matcher->getMatchKeys();
    }

    private function consumeUntil(string $next_char) : void
    {
        $end = strpos($this->pattern, $next_char, $this->offset);
        if ($end === false) {
            throw new InvalidArgumentException('Unparseable');
        }
        $this->offset = $end + 1;
    }

    /**
     * @throws InvalidArgumentException if an invalid pattern was seen
     * @suppress PhanPossiblyInfiniteRecursionSameParams this issue type does not attempt to check for changes to properties or global state.
     */
    private function extractGroup() : void
    {
        $pattern = $this->pattern;
        if ($pattern[$this->offset] === '?') {
            switch ($pattern[++$this->offset] ?? ':') {
                case ':':
                    break;
                case "'":
                    // NOTE: Subpattern names must not be the empty strings, and must start with non-digits,
                    // PregRegexPlugin would tell the user that.
                    // Could add that check.
                    $old_offset = $this->offset++;
                    $this->consumeUntil("'");
                    // Add both a positional subgroup and a named subgroup
                    $this->matches[\substr($pattern, $old_offset + 1, $this->offset - $old_offset - 2)] = true;
                    $this->matches[] = true;
                    break;
                case '<':
                    $old_offset = $this->offset;
                    $this->consumeUntil(">");
                    // Add both a positional subgroup and a named subgroup
                    $this->matches[\substr($pattern, $old_offset + 1, $this->offset - $old_offset - 2)] = true;
                    $this->matches[] = true;
                    break;
                case 'P':
                    if (($pattern[++$this->offset] ?? '') !== '<') {
                        throw new InvalidArgumentException('Unparseable named pattern');
                    }
                    $old_offset = $this->offset;
                    $this->consumeUntil('>');
                    // Add both a positional subgroup and a named subgroup
                    $this->matches[\substr($pattern, $old_offset + 1, $this->offset - $old_offset - 2)] = true;
                    $this->matches[] = true;
                    break;
                    // Internal option setting
                case '-':
                case 'i':
                case 'm':
                case 's':
                case 'x':
                case 'U':
                case 'X':
                case 'J':
                    // Comments
                case '#':
                    $this->consumeUntil(')');
                    return;
                case '|':
                    $this->extractCombinationGroup();
                    return;
                default:
                    throw new InvalidArgumentException('Support for complex patterns is not implemented');
            }
        } else {
            $this->matches[] = true;
        }

        $len = strlen($pattern);
        while ($this->offset < $len) {
            $c = $pattern[$this->offset++];
            if ($c === '\\') {
                // Skip over escaped characters
                $this->offset++;
                continue;
            }
            if ($c === ')') {
                // We have reached the end of this group
                return;
            }
            if ($c === '(') {
                // TODO: Handle ?: and the general case

                $this->extractGroup();
            }
        }
        throw new InvalidArgumentException('Reached the end of the pattern before extracting the group');
    }

    private function extractCombinationGroup() : void
    {
        $original_matches = $this->matches;
        $possible_matches = $original_matches;
        $pattern = $this->pattern;
        $len = strlen($pattern);
        while ($this->offset < $len) {
            $c = $pattern[$this->offset++];
            if ($c === '\\') {
                // Skip over escaped characters
                $this->offset++;
                continue;
            }
            if ($c === '|') {
                $possible_matches += $this->matches;
                $this->matches = $original_matches;
                continue;
            }
            if ($c === ')') {
                $possible_matches += $this->matches;
                $this->matches = $possible_matches;
                // We have reached the end of this group
                return;
            }
            if ($c === '(') {
                // TODO: Handle ?: and the general case

                $this->extractGroup();
            }
        }
    }

    /** @return associative-array<int|string,true> */
    private function getMatchKeys() : array
    {
        return $this->matches;
    }


    /**
     * Extracts everything between the pattern delimiters.
     * @throws InvalidArgumentException if the length mismatched
     */
    private static function extractInnerRegexPattern(string $pattern) : string
    {
        $pattern = \trim($pattern);

        $start_chr = $pattern[0] ?? '/';
        // @phan-suppress-next-line PhanParamSuspiciousOrder this is deliberate
        $i = \stripos('({[', $start_chr);
        if ($i !== false) {
            $end_chr = ')}]'[$i];
        } else {
            $end_chr = $start_chr;
        }
        // TODO: Reject characters that preg_match would reject
        $end_pos = \strrpos($pattern, $end_chr);
        if ($end_pos === false) {
            throw new InvalidArgumentException("Failed to find match for '$start_chr'");
        }

        $inner = (string)\substr($pattern, 1, $end_pos - 1);
        if ($i !== false) {
            // Unescape '/x\/y/' as 'x/y'
            $inner = \str_replace('\\' . $start_chr, $start_chr, $inner);
        }
        return $inner;
    }
}
