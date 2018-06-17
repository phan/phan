#!/usr/bin/env php
<?php
declare(strict_types=1);
<<<PHAN
@phan-file-suppress PhanNativePHPSyntaxCheckPlugin
PHAN;

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Phan\Issue;

class WikiWriter
{
    private $contents = '';
    private $print_to_stdout;

    public function __construct(bool $print_to_stdout = true)
    {
        $this->print_to_stdout = $print_to_stdout;
    }

    /** @return void */
    public function append(string $text)
    {
        $this->contents .= $text;
        if ($this->print_to_stdout) {
            echo $text;
        }
    }

    public function getContents() : string
    {
        return $this->contents;
    }
}

/**
 * Parts of this are based on https://github.com/phan/phan/issues/445#issue-195541058 by algo13
 */
class WikiIssueTypeUpdater
{
    private static function printUsageAndExit(int $exit_code = 1)
    {
        global $argv;
        $program = $argv[0];
        $help = <<<EOT
Usage: $program

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
     * @return void
     * @throws InvalidArgumentException (uncaught) if the documented issue types can't be found.
     */
    public static function main()
    {
        global $argv;
        if (count($argv) !== 1) {
            self::printUsageAndExit();
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
                // TODO: Fill this in with automatically generated contents
                // TODO: uncomment
                $writer->append($header . "\n");
                $writer->append($placeholder);
            }
        }

        // Get the new file contents, and normalize the whitespace at the end of the file.
        $contents = rtrim($writer->getContents()) . "\n";

        $wiki_filename_new = $wiki_filename . '.new';
        fwrite(STDERR, str_repeat('-', 80) . "\nSaving to '$wiki_filename_new'\n");
        file_put_contents($wiki_filename_new, $contents);
    }

    private static function updateTextForSection(string $text, string $header)
    {
        $issue_map = Issue::issueMap();
        $issue_name = preg_replace('@^[# ]*@', '', $header);
        $issue = $issue_map[$issue_name] ?? null;

        if ($issue instanceof Issue) {
            fwrite(STDERR, "Found $issue_name\n");
            $text = preg_replace_callback('@\n```\n[^\n]*\n```@', function ($unused_match) use ($issue) {
                return "\n```\n{$issue->getTemplateRaw()}\n```";
            }, $text);
        }
        return $text;
    }
}
WikiIssueTypeUpdater::main();
