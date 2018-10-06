#!/usr/bin/env php
<?php
declare(strict_types=1);
// @phan-file-suppress PhanNativePHPSyntaxCheckPlugin

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/WikiWriter.php';

use Phan\Config\Initializer;

/**
 * Information that can be inferred about the config name from the source code and other data
 */
class ConfigEntry
{
    /** @var string the configuration name (e.g. 'null_casts_as_any_type') */
    private $config_name;
    /** @var array<int,string> the raw comment lines */
    private $lines;
    /** @var string the category of configuration settings */
    private $category;

    /**
     * @param string $config_name the name of the config setting
     * @param array<int,string> $lines
     */
    public function __construct(string $config_name, array $lines)
    {
        $this->config_name = $config_name;
        $this->lines = $lines;
        $this->category = 'misc';
    }

    public function getConfigName() : string
    {
        return $this->config_name;
    }

    public function getMarkdown() : string
    {
        $result = '';
        foreach ($this->lines as $line) {
            $line = preg_replace('@^//( |$)@', '', trim($line));
            $result .= $line . "\n";
        }
        return $result;
    }

    /**
     * @return array<int,string> the raw lines
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getLines() : array
    {
        return $this->lines;
    }

    public function getCategory() : string
    {
        return $this->category;
    }
}

/**
 * Parts of this are based on https://github.com/phan/phan/issues/445#issue-195541058 by algo13
 */
class WikiConfigUpdater
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
     * @return array<string,ConfigEntry>
     */
    private static function getSortedConfigMap() : array
    {
        $map = Initializer::computeCommentNameDocumentationMap();
        ksort($map);
        $results = [];
        foreach ($map as $config_name => $lines) {
            $results[$config_name] = new ConfigEntry($config_name, $lines);
        }
        return $results;
    }

    /**
     * @return array<string,string> maps section header to the contents for that section header
     * TODO: Deduplicate
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

        $wiki_filename = __DIR__ . '/Phan-Config-Settings.md';

        $old_text_for_section = self::extractOldTextForSections($wiki_filename);

        $writer = new WikiWriter(true);
        $writer->append($old_text_for_section['global']);

        $map = self::getSortedConfigMap();

        $category = null;
        foreach ($map as $config_entry) {
            // Print each new category as we see it.
            if ($category !== $config_entry->getCategory()) {
                self::documentConfigCategorySection($writer, $config_entry, $old_text_for_section);
                $category = $config_entry->getCategory();
            }
            self::documentConfig($writer, $config_entry, $old_text_for_section);
        }

        // Get the new file contents, and normalize the whitespace at the end of the file.
        $contents = rtrim($writer->getContents()) . "\n";

        $wiki_filename_new = $wiki_filename . '.new';
        self::debugLog(str_repeat('-', 80) . "\nSaving to '$wiki_filename_new'\n");
        file_put_contents($wiki_filename_new, $contents);
    }

    /**
     * Start documenting a brand new category of configs where the first ConfigEntry in that category is $config_entry
     * @param WikiWriter $writer
     * @param array<string,string> $old_text_for_section
     * @throws InvalidArgumentException
     */
    private static function documentConfigCategorySection(WikiWriter $writer, ConfigEntry $config_entry, array $old_text_for_section)
    {
        $category = $config_entry->getCategory();
        if (!$category) {
            throw new InvalidArgumentException("Failed to find category for {$config_entry->getConfigName()}");
        }
        $category_name = $category;
        if (!$category_name) {
            throw new InvalidArgumentException("Failed to find category name for category $category of {$config_entry->getConfigName()}");
        }

        $header = '# ' . $category_name;
        $writer->append($header . "\n");
        if (array_key_exists($header, $old_text_for_section)) {
            $writer->append($old_text_for_section[$header]);
        } else {
            $writer->append("\nTODO: Document issue category $category_name\n\n");
        }
    }

    private static function documentConfig(WikiWriter $writer, ConfigEntry $config_entry, array $old_text_for_section)
    {
        $header = '## ' . $config_entry->getConfigName();
        if (array_key_exists($header, $old_text_for_section)) {
            $writer->append($header . "\n");

            // Fill this in with the prior contents of the header
            $writer->append(self::updateTextForSection($old_text_for_section[$header], $header));
        } else {
            $message = $config_entry->getMarkdown();
            $placeholder = <<<EOT

$message


EOT;
            $writer->append($header . "\n");
            $writer->append($placeholder);
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
        $config_name = preg_replace('@^[# ]*@', '', $header);
        self::debugLog("TODO: Write code to update text for $config_name\n");
        return $text;
    }
}

WikiConfigUpdater::main();
