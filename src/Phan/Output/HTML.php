<?php

declare(strict_types=1);

namespace Phan\Output;

use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Contains utilities for colorizing Phan's issue messages to html (and colorized CLI output in general)
 *
 * @see internal/dump_html_styles for a way to generate CSS styles for this html
 */
class HTML
{
    /**
     * Returns a version of $template where template strings (e.g. `{FILE}`
     * are replaced with `<span class="phan_file">path/to/file.php</span>`
     *
     * @param string $template
     * @param list<int|string|float|FQSEN|Type|UnionType|TypedElementInterface|UnaddressableTypedElement> $template_parameters
     */
    public static function htmlTemplate(
        string $template,
        array $template_parameters
    ): string {
        $template = \htmlentities($template);

        $i = 0;
        /** @param list<string> $matches */
        return \preg_replace_callback('/(\$?){([A-Z_]+)}|%[sdf]/', static function (array $matches) use ($template, $template_parameters, &$i): string {
            $j = $i++;
            if ($j >= \count($template_parameters)) {
                \error_log("Missing argument for colorized output ($template), offset $j");
                return '(MISSING)';
            }
            $arg = $template_parameters[$j];
            if (\is_object($arg)) {
                $arg = (string)$arg;
            }
            $arg = \htmlentities((string)$arg);
            if ($matches[2] ?? null) {
                $format_str = $matches[2];
            } else {
                $format_str = 'unknown';
            }
            if ($matches[1] ?? null) {
                $arg = $matches[1] . $arg;
            }
            $format_str = \htmlentities($format_str);
            return \sprintf('<span class="phan_%s">%s</span>', \strtolower($format_str), $arg);
        }, $template);
    }
}
