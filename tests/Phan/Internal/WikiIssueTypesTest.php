<?php

declare(strict_types=1);

namespace Phan\Tests\Internal;

use InvalidArgumentException;
use Phan\Issue;
use Phan\Tests\BaseTest;

use function array_key_exists;
use function array_merge;
use function dirname;
use function file_put_contents;
use function fwrite;
use function glob;
use function krsort;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function realpath;
use function rtrim;
use function strcmp;
use function strlen;
use function strnatcmp;
use function uasort;
use function usort;

use const STDERR;

/**
 * Parts of this are based on https://github.com/phan/phan/issues/445#issue-195541058 by algo13
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */
class WikiIssueTypesTest extends BaseTest
{
    /** @var array<string,array>|null an example for a subset of the issue types */
    private static $examples;

    /**
     * @return array<string,Issue>
     */
    private static function getSortedIssueMap(): array
    {
        $map = Issue::issueMap();
        uasort($map, static function (Issue $lhs, Issue $rhs): int {
            // Order by category, then by the issue name (natural order)
            return ($lhs->getCategory() <=> $rhs->getCategory())
                // ?: ($rhs->getSeverity() <=> $lhs->getSeverity())
                ?: strnatcmp($lhs->getType(), $rhs->getType());
        });
        return $map;
    }

    /**
     * Updates the markdown document of issue types with minimal documentation of missing issue types.
     * @throws InvalidArgumentException (uncaught) if the documented issue types can't be found.
     */
    public function testMarkdownDocumentUpToDate(): void
    {
        $wiki_filename = dirname(__DIR__, 3) . '/internal/Issue-Types-Caught-by-Phan.md';

        [$original_contents, $old_text_for_section] = WikiWriter::extractOldTextForSections($wiki_filename);

        $writer = new WikiWriter(false);
        $writer->append($old_text_for_section['global']);

        $map = self::getSortedIssueMap();

        $category = null;
        foreach ($map as $issue) {
            // Print each new category as we see it.
            if ($category !== $issue->getCategory()) {
                self::documentIssueCategorySection($writer, $issue, $old_text_for_section);
                $category = $issue->getCategory();
            }
            self::documentIssue($writer, $issue, $old_text_for_section);
        }

        // Get the new file contents, and normalize the whitespace at the end of the file.
        $contents = rtrim($writer->getContents()) . "\n";
        $wiki_filename_new = $wiki_filename . '.new';
        if ($contents !== $original_contents) {
            fwrite(STDERR, "Saving expected contents to '$wiki_filename_new'\n");
            file_put_contents($wiki_filename_new, $contents);
        }
        $this->assertSame($contents, $original_contents, "Unexpected contents (can be solved by copying $wiki_filename_new to $wiki_filename if referenced files are tracked in git)");
    }

    /**
     * Start documenting a brand-new category of issues where the first issue in that category is $issue
     * @param WikiWriter $writer
     * @param Issue $issue
     * @param array<string,string> $old_text_for_section
     * @throws InvalidArgumentException
     */
    private static function documentIssueCategorySection(WikiWriter $writer, Issue $issue, array $old_text_for_section): void
    {
        $category = $issue->getCategory();
        if (!$category) {
            throw new InvalidArgumentException("Failed to find category for issue {$issue->getType()}");
        }
        $category_name = Issue::getNameForCategory($category);
        if ($category_name === '') {
            throw new InvalidArgumentException("Failed to find category name for category $category of {$issue->getType()}");
        }

        $header = '# ' . $category_name;
        $writer->append($header . "\n");
        if (array_key_exists($header, $old_text_for_section)) {
            $writer->append($old_text_for_section[$header]);
        } else {
            $writer->append("\nTODO: Document issue category $category_name\n\n");
        }
    }

    /**
     * @param array<string,string> $old_text_for_section
     */
    private static function documentIssue(WikiWriter $writer, Issue $issue, array $old_text_for_section): void
    {
        // TODO: Print each severity as we see it?
        $header = '## ' . $issue->getType();
        // TODO: echo '## \[', $issue->getSeverityName(), '\] ', $issue->getType(), "\n";
        if (array_key_exists($header, $old_text_for_section)) {
            $writer->append($header . "\n");

            // Fill this in with the prior contents of the header
            $writer->append(self::updateTextForSection($old_text_for_section[$header], $header));
        } else {
            $message = $issue->getTemplateRaw();
            $placeholder = <<<EOT

```
$message
```


EOT;
            $writer->append($header . "\n");
            // Append both the issue message and an example instance of that issue message (if possible)
            $writer->append(self::updateTextForSection($placeholder, $header));
        }
    }

    /**
     * Returns the section with an updated issue template string (if there already was an issue template)
     */
    private static function updateTextForSection(string $text, string $header): string
    {
        $issue_map = Issue::issueMap();
        $issue_name = preg_replace('@^[# ]*@', '', $header);
        $issue = $issue_map[$issue_name] ?? null;

        if ($issue instanceof Issue) {
            // fwrite(STDERR, "Found $issue_name\n");
            /** @param list<string> $unused_match */
            $text = preg_replace_callback('@\n```\n[^\n]*\n```@', static function (array $unused_match) use ($issue): string {
                return "\n```\n{$issue->getTemplateRaw()}\n```";
            }, $text);
            if (!preg_match('@```php|https?://@i', $text)) {
                $example = self::findExamples()[$issue->getType()] ?? null;
                if ($example) {
                    $text = rtrim($text, "\n") . "\n\n" . self::textForExample($example);
                    // } else {
                    // fwrite(STDERR, "Failed to find text for {$issue->getType()}\n");
                }
            }
        }
        return $text;
    }

    /**
     * @param array{0:UnitTestRecord,1:int,2:int} $example
     */
    private static function textForExample(array $example): string
    {
        [$record, $src_file_lineno, $expected_file_lineno] = $example;
        $src_url = preg_replace('@.*/tests/@', 'https://github.com/phan/phan/tree/v4/tests/', $record->src_filename);
        $expected_url = preg_replace('@.*/tests/@', 'https://github.com/phan/phan/tree/v4/tests/', $record->expected_filename);

        return <<<EOT
e.g. [this issue]($expected_url#L$expected_file_lineno) is emitted when analyzing [this PHP file]($src_url#L$src_file_lineno).


EOT;
    }

    /**
     * @return array<string,array>
     */
    private static function findExamples(): array
    {
        return self::$examples ?? self::$examples = self::calculateExamples();
    }

    /**
     * @return array<string,array>
     */
    private static function calculateExamples(): array
    {
        // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
        $base = dirname(realpath(__DIR__), 3);
        $files = array_merge(
            glob($base . '/tests/files/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/ast/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/config_override_test/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/empty_methods_plugin_test/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/fallback_test/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/intl_files/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/rewriting_test/expected/*.php.expected') ?: [],
            glob($base . '/tests/misc/soap_test/expected/*.php.expected') ?: [],
            glob($base . '/tests/php70_files/expected/*.php.expected') ?: [],
            glob($base . '/tests/php72_files/expected/*.php.expected') ?: [],
            glob($base . '/tests/php73_files/expected/*.php.expected') ?: [],
            glob($base . '/tests/php74_files/expected/*.php.expected') ?: [],
            glob($base . '/tests/php80_files/expected/*.php.expected') ?: [],
            glob($base . '/tests/plugin_test/expected/*.php.expected') ?: [],
            glob($base . '/tests/rasmus_files/expected/*.php.expected') ?: []
            //glob($base . '/tests/multi_files/expected/*.php.expected') ?: []
        );
        $records = [];
        foreach ($files as $expected_filename) {
            $src_filename = preg_replace('@/expected/(.*\.php)\.expected$@D', '/src/\1', $expected_filename);
            // try {
            $records[] = new UnitTestRecord($src_filename, $expected_filename);
            // } catch (RuntimeException $e) { fwrite(STDERR, $e->getMessage()); }
        }
        // Put the longest files first, we overwrite issue names even if they were seen already
        usort($records, static function (UnitTestRecord $a, UnitTestRecord $b): int {
            return (strlen($b->src_contents) <=> strlen($a->src_contents)) ?: strcmp($a->src_filename, $b->src_filename);
        });

        $examples = [];
        foreach ($records as $record) {
            // Process these backwards so we use the first issue occurrence in a file as the finally chosen example.
            $issues = $record->getIssues();
            krsort($issues);
            foreach ($issues as $expected_file_lineno => [$ref, $issue_name, $unused_description]) {
                if (preg_match('/[0-9]+$/D', $ref, $matches)) {
                    $src_file_lineno = (int)$matches[0];
                    $examples[$issue_name] = [$record, $src_file_lineno, $expected_file_lineno];
                }
            }
        }
        return $examples;
    }
}
