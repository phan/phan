<?php

declare(strict_types=1);

namespace Phan\Tests\Internal;

use AssertionError;

use function explode;
use function file_exists;
use function file_get_contents;
use function is_string;
use function strpos;

/**
 * This class will update and save config setting documentation (for .phan/config.php) in a markdown format that can be uploaded to Phan's wiki
 */
class WikiWriter
{
    /**
     * @var string the built up contents to save to the markdown file
     */
    private $contents = '';

    /**
     * @var bool should this print to stdout while building up the markdown contents?
     * Useful for debugging.
     */
    private $print_to_stdout;

    public function __construct(bool $print_to_stdout = true)
    {
        $this->print_to_stdout = $print_to_stdout;
    }

    /**
     * Append $text to the buffer of text to save.
     * @suppress PhanPluginRemoveDebugEcho
     */
    public function append(string $text): void
    {
        $this->contents .= $text;
        if ($this->print_to_stdout) {
            echo $text;
        }
    }

    /**
     * @return string the built up contents to save to the markdown file
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * @return array{0: string,1: array<string,string>} maps section header to the contents for that section header
     */
    public static function extractOldTextForSections(string $wiki_filename): array
    {
        if (!file_exists($wiki_filename)) {
            throw new AssertionError("Failed to locate '$wiki_filename'\n");
        }
        $contents = file_get_contents($wiki_filename);
        if (!is_string($contents)) {
            throw new AssertionError("Failed to read '$wiki_filename'\n");
        }
        $wiki_lines = explode("\n", $contents);
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
        return [$contents, $text_for_section];
    }
}
