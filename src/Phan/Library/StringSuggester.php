<?php

declare(strict_types=1);

namespace Phan\Library;

use Phan\Config;
use Phan\IssueFixSuggester;

use function strlen;

/**
 * Used to suggest prefixes and similar length strings, given a set of strings.
 */
class StringSuggester
{
    /**
     * @var array<string,string> sorted map of lowercase to uppercase strings
     * This is sorted to make looking for prefixes efficient.
     */
    private $strings;

    /**
     * @var associative-array<int,array<string,string>>
     * Maps (requested approximate length to (lowercase element name => real element name))
     */
    private $strings_near_length = [];

    /** @var list<string> the sorted keys, for binary searching */
    private $key_set;

    /** @param array<string, string> $strings map of lowercase element names to uppercase element names */
    public function __construct(array $strings)
    {
        \uksort($strings, 'strcmp');
        $this->strings = $strings;
        $this->key_set = \array_keys($strings);
    }

    /**
     * @return array<string,string> map of lowercase name to original name in namespace
     */
    public function getSuggestions(string $name): array
    {
        if (!$this->strings || $name === '') {
            return [];
        }

        $len = strlen($name);
        $similar_length_names = $this->getSimilarLengthStrings($len);
        if (\count($similar_length_names) > 1 && $len <= 1) {
            // Only suggest single-character names if there's exactly one suggestion of length 1 or 2
            $similar_length_names = [];
        }
        if (\count($similar_length_names) > Config::getValue('suggestion_check_limit')) {
            $similar_length_names = [];
        }
        $name_lower = \strtolower($name);
        $similar_length_names += $this->findStringsBeginningWith($name_lower);
        $name_lower = \strtolower($name);
        if (\count($similar_length_names) > Config::getValue('suggestion_check_limit')) {
            return [];
        }

        $suggestion_set = $similar_length_names;
        unset($suggestion_set[$name_lower]);
        if (\count($suggestion_set) === 0) {
            return [];
        }

        // We're looking for similar names, not identical names
        return IssueFixSuggester::getSuggestionsForStringSet($name_lower, $suggestion_set);
    }

    /**
     * @return array<string,string> maps lowercase string to original string,
     * for those with names which case-insensitively begin with $name
     */
    public function findStringsBeginningWith(string $name): array
    {
        $name = \strtolower($name);
        $name_len = strlen($name);
        $start = 0;
        $arr = $this->key_set;
        $N = \count($arr);
        $end = $N;
        $cmp = static function (string $value) use ($name, $name_len): int {
            return \strncmp($value, $name, $name_len);
        };
        // Binary search for the start of the matches
        while ($start < $end) {
            $mid = ($start + $end) >> 1;
            if ($cmp($arr[$mid]) >= 0) {
                $end = $mid - 1;
            } else {
                $start = $mid + 1;
            }
        }
        if ($start >= $N) {
            // No exact matches
            return [];
        }
        // No exact matches
        if ($cmp($arr[$start]) !== 0) {
            return [];
        }
        $limit = Config::getValue('suggestion_check_limit');
        if ($start + $limit < \count($arr)) {
            if ($cmp($arr[$start + $limit]) !== 0) {
                // Too many suggestions
                return [];
            }
        }
        $result = [];
        for ($i = $start; $i < $N; $i++) {
            $val = $arr[$i];
            if ($cmp($val) !== 0) {
                break;
            }
            $result[$val] = $this->strings[$val];
        }
        return $result;
    }

    /**
     * @return array<string,string> a mapping from lowercase name to uppercase name for those strings near the requested length.
     */
    private function getSimilarLengthStrings(int $strlen): array
    {
        return $this->strings_near_length[$strlen] ?? ($this->strings_near_length[$strlen] = $this->computeSimilarLengthStrings($strlen));
    }

    /**
     * @return array<string,string> a newly computed mapping from lowercase name to uppercase name for those strings near the requested length.
     */
    private function computeSimilarLengthStrings(int $strlen): array
    {
        $max_levenshtein_distance = (int)(1 + $strlen / 6);
        $results = [];

        foreach ($this->strings as $name_lower => $name) {
            if (\abs(strlen($name) - $strlen) <= $max_levenshtein_distance) {
                $results[$name_lower] = $name;
            }
        }
        return $results;
    }
}
