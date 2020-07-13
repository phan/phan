<?php

declare(strict_types=1);

namespace Phan\Tests\Internal;

use InvalidArgumentException;
use Phan\Config\Initializer;
use Phan\Tests\BaseTest;

use function array_key_exists;
use function dirname;
use function file_put_contents;
use function fwrite;
use function rtrim;
use function strcasecmp;
use function uasort;

use const STDERR;

/**
 * Parts of this are based on https://github.com/phan/phan/issues/445#issue-195541058 by algo13
 */
class WikiConfigTest extends BaseTest
{
    /**
     * @return array<string,ConfigEntry>
     */
    private static function getSortedConfigMap(): array
    {
        $map = Initializer::computeCommentNameDocumentationMap();
        $results = [];
        foreach ($map as $config_name => $lines) {
            $entry = new ConfigEntry($config_name, $lines);
            if ($entry->isHidden()) {
                continue;
            }
            $results[$config_name] = $entry;
        }
        uasort($results, static function (ConfigEntry $a, ConfigEntry $b): int {
            return
                $a->getCategoryIndex() <=> $b->getCategoryIndex() ?:
                strcasecmp($a->getCategory(), $b->getCategory()) ?:
                strcasecmp($a->getConfigName(), $b->getConfigName());
        });
        return $results;
    }

    /**
     * Updates the markdown document of issue types with minimal documentation of missing issue types.
     * @throws InvalidArgumentException (uncaught) if the documented issue types can't be found.
     */
    public function testMarkdownUpToDate(): void
    {
        $wiki_filename = dirname(__DIR__, 3) . '/internal/Phan-Config-Settings.md';

        [$original_contents, $old_text_for_section] = WikiWriter::extractOldTextForSections($wiki_filename);

        $writer = new WikiWriter(false);
        $writer->append($old_text_for_section['global']);

        $map = self::getSortedConfigMap();

        $category = null;
        foreach ($map as $config_entry) {
            // Print each new category as we see it.
            if ($category !== $config_entry->getCategory()) {
                self::documentConfigCategorySection($writer, $config_entry, $old_text_for_section);
                $category = $config_entry->getCategory();
            }
            self::documentConfig($writer, $config_entry);
        }

        // Get the new file contents, and normalize the whitespace at the end of the file.
        $contents = rtrim($writer->getContents()) . "\n";

        $wiki_filename_new = $wiki_filename . '.new';
        if ($contents !== $original_contents) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall deliberate
            fwrite(STDERR, "Saving expected contents to '$wiki_filename_new'\n");
            file_put_contents($wiki_filename_new, $contents);
        }
        $this->assertSame($contents, $original_contents, "Unexpected contents (can be solved by copying $wiki_filename_new to $wiki_filename if referenced files are tracked in git)");
    }

    /**
     * Start documenting a brand-new category of configs where the first ConfigEntry in that category is $config_entry
     * @param WikiWriter $writer
     * @param array<string,string> $old_text_for_section
     * @throws InvalidArgumentException
     */
    private static function documentConfigCategorySection(WikiWriter $writer, ConfigEntry $config_entry, array $old_text_for_section): void
    {
        $category_name = $config_entry->getCategory();
        if ($category_name === '') {
            throw new InvalidArgumentException("Failed to find category for config {$config_entry->getConfigName()}");
        }

        $header = '# ' . $category_name;
        $writer->append($header . "\n");
        if (array_key_exists($header, $old_text_for_section)) {
            $writer->append($old_text_for_section[$header]);
        } else {
            $writer->append("\nTODO: Document config category $category_name (see tests/Phan/Internal/WikiConfigTest.php and tests/Phan/Internal/ConfigEntry.php)\n\n");
        }
    }

    private static function documentConfig(WikiWriter $writer, ConfigEntry $config_entry): void
    {
        $header = '## ' . $config_entry->getConfigName();

        $message = $config_entry->getMarkdown();
        $default = $config_entry->getRepresentationOfDefault();
        $message = rtrim($message, "\n");
        $placeholder = <<<EOT

$message

(Default: $default)


EOT;
        $writer->append($header . "\n");
        $writer->append($placeholder);
    }
}
