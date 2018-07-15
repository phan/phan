<?php declare(strict_types=1);
namespace Phan\Language\Element;

class MarkupDescription
{
    public static function buildForElement(
        AddressableElementInterface $element
    ) : string {
        $markup = $element->getMarkupDescription();
        $result = "```php\n$markup\n```";
        $doc_comment = $element->getDocComment();
        if ($doc_comment) {
            $comment_category = null;
            if ($element instanceof Property) {
                $comment_category = Comment::ON_PROPERTY;
            } elseif ($element instanceof ConstantInterface) {
                $comment_category = Comment::ON_CONST;
            }
            $extracted_doc_comment = self::extractDocComment($doc_comment, $comment_category);
            if ($extracted_doc_comment) {
                $result .= "\n\n" . $extracted_doc_comment;
            }
        }

        return $result;
    }

    /**
     * @return string non-empty on success
     * @internal
     */
    public static function extractDocComment(string $doc_comment, int $comment_category = null) : string
    {
        $doc_comment = preg_replace('@(^/\*\*)|(\*/$)@', '', $doc_comment);

        // TODO: Improve this extraction, add tests
        $results = [];
        foreach (explode("\n", $doc_comment) as $line) {
            $pos = stripos($line, '*');
            if ($pos !== false) {
                $line = \substr($line, $pos + 1);
            } else {
                $line = \ltrim(rtrim($line), "\n\t ");
            }
            if (!is_string($line) || preg_match('/^\s*@/', $line) > 0) {
                if (count($results) === 0) {
                    // Special case: Treat `@var T description of T` as a valid single-line comment of constants and properties.
                    // Variables don't currently have associated comments
                    if (\in_array($comment_category, [Comment::ON_PROPERTY, Comment::ON_CONST])) {
                        if (preg_match('/^\s*@var\b/', $line) > 0) {
                            $results[] = trim($line);
                        }
                    }
                }
                // Assume that the description stopped after the first phpdoc tag.
                break;
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
