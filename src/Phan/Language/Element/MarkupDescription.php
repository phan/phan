<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\Element\Comment\Builder;

/**
 * APIs for generating markup (markdown) description of elements
 *
 * This is used by the language server to send markup to the language client (e.g. for hover text of an element).
 */
class MarkupDescription
{
    /**
     * Generates a markup snippet with a description of the declaration of $element,
     * and its doc comment summary and description.
     */
    public static function buildForElement(
        AddressableElementInterface $element
    ) : string {
        // TODO: Use the doc comments of the ancestors if unavailable or if (at)inheritDoc is used.
        $markup = $element->getMarkupDescription();
        $result = "```php\n$markup\n```";
        $extracted_doc_comment = self::extractDescriptionFromDocComment($element);
        if ($extracted_doc_comment) {
            $result .= "\n\n" . $extracted_doc_comment;
        }

        return $result;
    }

    /**
     * Extracts a plaintext description of the element from the doc comment of an element.
     *
     * @return ?string
     */
    public static function extractDescriptionFromDocComment(AddressableElementInterface $element)
    {
        $doc_comment = $element->getDocComment();
        if (!$doc_comment) {
            return null;
        }
        $comment_category = null;
        if ($element instanceof Property) {
            $comment_category = Comment::ON_PROPERTY;
        } elseif ($element instanceof ConstantInterface) {
            $comment_category = Comment::ON_CONST;
        } elseif ($element instanceof FunctionInterface) {
            $comment_category = Comment::ON_FUNCTION;
        }
        $extracted_doc_comment = self::extractDocComment($doc_comment, $comment_category);
        return $extracted_doc_comment ?: null;
    }

    /**
     * @return array<string,string> information about the param tags
     */
    public static function extractParamTagsFromDocComment(AddressableElementInterface $element) : array
    {
        $doc_comment = $element->getDocComment();
        if (!$doc_comment) {
            return [];
        }
        if (strpos($doc_comment, '@param') === false) {
            return [];
        }
        // Trim the start and the end of the doc comment.
        //
        // We leave in the second `*` of `/**` so that every single non-empty line
        // of a typical doc comment will begin with a `*`
        $doc_comment = preg_replace('@(^/\*)|(\*/$)@', '', $doc_comment);

        $results = [];
        $lines = explode("\n", $doc_comment);
        foreach ($lines as $i => $line) {
            $line = self::trimLine($line);
            if (!is_string($line) || preg_match('/^\s*@param(\s|$)@/', $line) > 0) {
                // Extract all of the (at)param annotations.
                $param_tag_summary = self::extractTagSummary($lines, $i);
                if (end($param_tag_summary) === '') {
                    array_pop($param_tag_summary);
                }
                $full_comment = implode("\n", self::trimLeadingWhitespace($param_tag_summary));
                // @phan-suppress-next-line PhanAccessClassConstantInternal
                $matched = \preg_match(Builder::PARAM_COMMENT_REGEX, $full_comment, $match);
                if (!$matched) {
                    continue;
                }
                if (!isset($match[21])) {
                    continue;
                }

                $name = $match[21];
                // @phan-suppress-next-line PhanAccessClassConstantInternal
                $full_comment = \preg_replace(Builder::PARAM_COMMENT_REGEX, '`\0`', $full_comment);
                $results[$name] = $full_comment;
            }
        }
        return $results;
    }

    /**
     * Returns a doc comment with:
     *
     * - leading `/**` and trailing `*\/` removed
     * - leading/trailing space on lines removed,
     * - blank lines removed from the beginning and end.
     *
     * @return string simplified version of the doc comment, with leading `*` on lines preserved.
     */
    public static function getDocCommentWithoutWhitespace(string $doc_comment) : string
    {
        // Trim the start and the end of the doc comment.
        //
        // We leave in the second `*` of `/**` so that every single non-empty line
        // of a typical doc comment will begin with a `*`
        $doc_comment = preg_replace('@(^/\*)|(\*/$)@', '', $doc_comment);

        $lines = explode("\n", $doc_comment);
        $lines = array_map('trim', $lines);
        $lines = MarkupDescription::trimLeadingWhitespace($lines);
        while (\in_array(\end($lines), ['*', ''], true)) {
            \array_pop($lines);
        }
        while (\in_array(\reset($lines), ['*', ''], true)) {
            \array_shift($lines);
        }
        return implode("\n", $lines);
    }

    /**
     * Returns a markup string with the extracted description of this element (known to be a comment of an element with type $comment_category).
     * On success, this is a non-empty string.
     *
     * @return string markup string
     * @internal
     */
    public static function extractDocComment(string $doc_comment, int $comment_category = null) : string
    {
        // Trim the start and the end of the doc comment.
        //
        // We leave in the second `*` of `/**` so that every single non-empty line
        // of a typical doc comment will begin with a `*`
        $doc_comment = preg_replace('@(^/\*)|(\*/$)@', '', $doc_comment);

        $results = [];
        $lines = explode("\n", $doc_comment);
        $saw_phpdoc_tag = false;
        foreach ($lines as $i => $line) {
            $line = self::trimLine($line);
            if (!is_string($line) || preg_match('/^\s*@/', $line) > 0) {
                $saw_phpdoc_tag = true;
                if (count($results) === 0) {
                    // Special cases:
                    if (\in_array($comment_category, [Comment::ON_PROPERTY, Comment::ON_CONST])) {
                        // Treat `@var T description of T` as a valid single-line comment of constants and properties.
                        // Variables don't currently have associated comments
                        if (preg_match('/^\s*@var\s/', $line) > 0) {
                            $new_lines = self::extractTagSummary($lines, $i);
                            if (isset($new_lines[0])) {
                                // @phan-suppress-next-line PhanAccessClassConstantInternal
                                $new_lines[0] = \preg_replace(Builder::PARAM_COMMENT_REGEX, '`\0`', $new_lines[0]);
                            }
                            $results = array_merge($results, $new_lines);
                        }
                    } elseif (\in_array($comment_category, Comment::FUNCTION_LIKE)) {
                        // Treat `@return T description of return value` as a valid single-line comment of closures, functions, and methods.
                        // Variables don't currently have associated comments
                        if (preg_match('/^\s*@return(\s|$)/', $line) > 0) {
                            $new_lines = self::extractTagSummary($lines, $i);
                            if (isset($new_lines[0])) {
                                // @phan-suppress-next-line PhanAccessClassConstantInternal
                                $new_lines[0] = \preg_replace(Builder::RETURN_COMMENT_REGEX, '`\0`', $new_lines[0]);
                            }
                            $results = array_merge($results, $new_lines);
                        }
                    }
                }
            }
            if ($saw_phpdoc_tag) {
                continue;
            }
            if (\trim($line) === '') {
                $line = '';
                if (\in_array(\end($results), ['', false], true)) {
                    continue;
                }
            }
            $results[] = $line;
        }
        if (end($results) === '') {
            array_pop($results);
        }
        $results = self::trimLeadingWhitespace($results);
        return implode("\n", $results);
    }

    private static function trimLine(string $line) : string
    {
        $line = rtrim($line);
        $pos = stripos($line, '*');
        if ($pos !== false) {
            return (string)\substr($line, $pos + 1);
        } else {
            return \ltrim($line, "\n\t ");
        }
    }

    /**
     * @param array<int,string> $lines
     * @param int $i the offset of the tag in $lines
     * @return array<int,string> the trimmed lines
     * @internal
     */
    public static function extractTagSummary(array $lines, int $i): array
    {
        $summary = [];
        $summary[] = self::trimLine($lines[$i]);
        for ($j = $i + 1; $j < count($lines); $j++) {
            $line = self::trimLine($lines[$j]);
            if (preg_match('/^\s*\{?@/', $line)) {
                // Break on other annotations such as (at)internal, {(at)inheritDoc}, etc.
                break;
            }
            if ($line === '' && end($summary) === '') {
                continue;
            }
            $summary[] = $line;
        }
        if (end($summary) === '') {
            array_pop($summary);
        }
        if (count($summary) === 1 && count(preg_split('/\s+/', trim($summary[0]))) <= 2) {
            // For something uninformative such as "* (at)return int" (and nothing else),
            // don't treat it as a summary.
            //
            // The caller would already show the return type
            return [];
        }
        return $summary;
    }

    /**
     * @param array<int,string> $lines
     * @return array<int,string>
     */
    private static function trimLeadingWhitespace(array $lines) : array
    {
        if (count($lines) === 0) {
            return [];
        }
        $min_whitespace = PHP_INT_MAX;
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $min_whitespace = \min($min_whitespace, \strspn($line, ' ', 0, $min_whitespace));
            if ($min_whitespace === 0) {
                return $lines;
            }
        }
        if ($min_whitespace > 0) {
            foreach ($lines as $i => $line) {
                if ($line === '') {
                    continue;
                }
                $lines[$i] = (string)\substr($line, $min_whitespace);
            }
        }
        return $lines;
    }
}
