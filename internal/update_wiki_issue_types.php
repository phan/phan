#!/usr/bin/env php
<?php
declare(strict_types=1);
// @phan-file-suppress PhanNativePHPSyntaxCheckPlugin

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/WikiWriter.php';

use Phan\Issue;

/**
 * Parts of this are based on https://github.com/phan/phan/issues/445#issue-195541058 by algo13
 */
class WikiIssueTypeUpdater
{
    /**
     * @var bool whether to print debugging messages to stderr
     */
    private static $verbose = false;

    private static function printUsageAndExit(int $exit_code = 1)
    {
        global $argv;
        $program = $argv[0];
        $help = <<<EOT
Usage: $program [-v/--verbose]

EOT;
        fwrite(STDERR, $help);
        exit($exit_code);
    }

    /**
     * @return array<string,Issue>
     */
    private static function getSortedIssueMap() : array
    {
        $map = Issue::issueMap();
        uasort($map, function (Issue $lhs, Issue $rhs) : int {
            // Order by category, then by the issue name (natural order)
            return ($lhs->getCategory() <=> $rhs->getCategory())
                // ?: ($rhs->getSeverity() <=> $lhs->getSeverity())
                ?: strnatcmp($lhs->getType(), $rhs->getType());
        });
        return $map;
    }

    /**
     * @return array<string,string> maps section header to the contents for that section header
     */
    private static function extractOldTextForSections(string $wiki_filename) : array
    {
        if (!file_exists($wiki_filename)) {
            fwrite(STDERR, "Failed to load '$wiki_filename'\n");
            exit(1);
        }
        $wiki_lines = explode("\n", file_get_contents($wiki_filename));
        $text_for_section = [];
        $title = 'global';
        $text_for_section[$title] = '';
        // Skip heading titles
        foreach ($wiki_lines as $line) {
            if (strpos($line, '#') === 0) {
                // TODO: If before md has Severity, delete it.
                // TODO: $title = preg_replace('/\\\\\[.*\\\\\]\s*/', '', $line);
                $title = $line;
                $text_for_section[$title] = '';
            } else {
                $text_for_section[$title] .= $line . "\n";
            }
        }
        return $text_for_section;
    }

    /**
     * Updates the markdown document of issue types with minimal documentation of missing issue types.
     *
     * @return void
     * @throws InvalidArgumentException (uncaught) if the documented issue types can't be found.
     */
    public static function main()
    {
        global $argv;
        if (count($argv) !== 1) {
            if (count($argv) === 2 && in_array($argv[1], ['-v', '--verbose'])) {
                self::$verbose = true;
            } else {
                self::printUsageAndExit();
            }
        }
        error_reporting(E_ALL);

        $wiki_filename = __DIR__ . '/Issue-Types-Caught-by-Phan.md';

        $old_text_for_section = self::extractOldTextForSections($wiki_filename);

        $writer = new WikiWriter(true);
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
        self::debugLog(str_repeat('-', 80) . "\nSaving to '$wiki_filename_new'\n");
        file_put_contents($wiki_filename_new, $contents);
    }

    /**
     * Start documenting a brand new category of issues where the first issue in that category is $issue
     * @param WikiWriter $writer
     * @param Issue $issue
     * @param array<string,string> $old_text_for_section
     * @throws InvalidArgumentException
     */
    private static function documentIssueCategorySection(WikiWriter $writer, Issue $issue, array $old_text_for_section)
    {
        $category = $issue->getCategory();
        if (!$category) {
            throw new InvalidArgumentException("Failed to find category for {$issue->getType()}");
        }
        $category_name = Issue::getNameForCategory($category);
        if (!$category_name) {
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

    private static function documentIssue(WikiWriter $writer, Issue $issue, array $old_text_for_section)
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

    private static function debugLog(string $message)
    {
        // Uncomment the below line to enable debugging
        if (self::$verbose) {
            fwrite(STDERR, $message);
        }
    }

    /**
     * Returns the section with an updated issue template string (if there already was an issue template)
     */
    private static function updateTextForSection(string $text, string $header) : string
    {
        $issue_map = Issue::issueMap();
        $issue_name = preg_replace('@^[# ]*@', '', $header);
        $issue = $issue_map[$issue_name] ?? null;

        if ($issue instanceof Issue) {
            self::debugLog("Found $issue_name\n");
            /** @param array<int,string> $unused_match */
            $text = preg_replace_callback('@\n```\n[^\n]*\n```@', function ($unused_match) use ($issue) : string {
                return "\n```\n{$issue->getTemplateRaw()}\n```";
            }, $text);
            if (!preg_match('@```php|https?://@i', $text)) {
                $example = self::findExamples()[$issue->getType()] ?? null;
                if ($example) {
                    $text = rtrim($text, "\n") . "\n\n" . self::textForExample($example);
                }
            }
        }
        return $text;
    }

    /**
     * @param array{0:UnitTestRecord,1:int,2:int} $example
     */
    private static function textForExample(array $example) : string
    {
        list($record, $src_file_lineno, $expected_file_lineno) = $example;
        $src_url = preg_replace('@.*/tests/@', 'https://github.com/phan/phan/tree/1.0.7/tests/', $record->src_filename);
        $expected_url = preg_replace('@.*/tests/@', 'https://github.com/phan/phan/tree/1.0.7/tests/', $record->expected_filename);

        return <<<EOT
e.g. [this issue]($expected_url#L$expected_file_lineno) is emitted when analyzing [this PHP file]($src_url#L$src_file_lineno).


EOT;
    }

    /** @var array|null */
    private static $examples;

    private static function findExamples() : array
    {
        return self::$examples ?? self::$examples = self::calculateExamples();
    }

    private static function calculateExamples() : array
    {
        $base = dirname(realpath(__DIR__));
        $files = array_merge(
            glob($base . '/tests/files/expected/*.php.expected'),
            glob($base . '/tests/misc/fallback_test/expected/*.php.expected'),
            glob($base . '/tests/plugin_test/expected/*.php.expected')
        );
        $records = [];
        foreach ($files as $expected_filename) {
            $src_filename = preg_replace('@/expected/(.*\.php)\.expected$@', '/src/\1', $expected_filename);
            try {
                $records[] = new UnitTestRecord($src_filename, $expected_filename);
            } catch (RuntimeException $e) {
                fwrite(STDERR, $e->getMessage());
            }
        }
        // Put the longest files first, we overwrite issue names even if they were seen already
        usort($records, function (UnitTestRecord $a, UnitTestRecord $b) : int {
            return (strlen($b->src_contents) <=> strlen($a->src_contents)) ?: strcmp($a->src_filename, $b->src_filename);
        });

        $examples = [];
        foreach ($records as $record) {
            // Process these backwards so we use the first issue occurrence in a file as the finally chosen example.
            $issues = $record->getIssues();
            krsort($issues);
            foreach ($issues as $expected_file_lineno => list($ref, $issue_name, $unused_description)) {
                if (preg_match('/[0-9]+$/', $ref, $matches)) {
                    $src_file_lineno = (int)$matches[0];
                    $examples[$issue_name] = [$record, $src_file_lineno, $expected_file_lineno];
                }
            }
        }
        return $examples;
    }
}

/**
 * This represents a record of a unit test with a single source file and a single expectation file.
 */
class UnitTestRecord
{
    /** @var string the file name of the source of the unit test */
    public $src_filename;
    /** @var string the file name of the expected errors of the unit test */
    public $expected_filename;
    /** @var string the contents of the source of the unit test */
    public $src_contents;
    /** @var string the expected text errors of that unit test */
    public $expected_contents;

    public function __construct(string $src_filename, string $expected_filename)
    {
        $this->src_filename = $src_filename;
        $this->expected_filename = $expected_filename;
        $this->src_contents = self::getContents($src_filename);
        $this->expected_contents = self::getContents($expected_filename);
    }

    public function getIssues() : array
    {
        $issues = [];
        foreach (explode("\n", $this->expected_contents) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $lineno = $i + 1;
            $issues[$lineno] = explode(' ', $line, 3);
        }
        return $issues;
    }

    private static function getContents(string $filename) : string
    {
        $contents = file_get_contents($filename);
        if (!is_string($contents)) {
            throw new RuntimeException("Failed to read $filename");
        }
        return $contents;
    }
}
WikiIssueTypeUpdater::main();
