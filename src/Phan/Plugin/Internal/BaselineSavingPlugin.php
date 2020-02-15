<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\CLI;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\FileRef;
use Phan\Phan;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\SubscribeEmitIssueCapability;

/**
 * This plugin generates a baseline from the issues that weren't suppressed by other plugins or config settings.
 *
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class BaselineSavingPlugin extends PluginV3 implements
    SubscribeEmitIssueCapability,
    FinalizeProcessCapability
{
    /** @var string the path of the file to save to. */
    private $baseline_path;

    /**
     * Maps project file paths to a set of issue types emitted in that file.
     * @var array<string,array<string, true>>
     */
    private $suppressions_by_file = [];

    /**
     * Maps issue name to a set of hashes of unique issues.
     *
     * This is used to determine the count of issues to show in the baseline being saved.
     *
     * This is approximate because BufferingCollector deduplicates issues slightly differently.
     *
     * @var array<string,array<string,true>>
     */
    private $suppressions_by_type = [];

    public function __construct(string $baseline_path)
    {
        $this->baseline_path = $baseline_path;
    }

    /**
     * This method is called before Phan emits an issue not suppressed elsewhere.
     *
     * @param IssueInstance $issue_instance
     *
     * @return false this plugin does not suppress anything - it just records issues to generate a file that can be used by BaselineReadingPlugin in subsequent runs.
     */
    public function onEmitIssue(
        IssueInstance $issue_instance
    ): bool {
        $file_path = $issue_instance->getFile();
        $file_path = FileRef::getProjectRelativePathForPath($file_path);
        if (Phan::isExcludedAnalysisFile($file_path)) {
            return false;
        }

        // Would prefer to use formatSortableKey, but this doesn't provide the IssueInstance, and plugins have issues that can't be fetched with Issue::fromType.
        $hash = \sha1($issue_instance->__toString());
        $issue_type = $issue_instance->getIssue()->getType();
        $this->suppressions_by_file[$file_path][$issue_type] = true;
        $this->suppressions_by_type[$issue_type][$hash] = true;
        return false;
    }

    public function finalizeProcess(CodeBase $unused_code_base): void
    {
        CLI::printToStderr("Saving a new issue baseline to '$this->baseline_path'\nSubsequent Phan runs can read from this file with --load-baseline='$this->baseline_path' to ignore pre-existing issues.\n");
        $contents = $this->generateAllBaselineContents();
        \file_put_contents($this->baseline_path, $contents);
    }

    private function generateAllBaselineContents(): string
    {
        $contents = <<<'EOT'
<?php
/**
 * This is an automatically generated baseline for Phan issues.
 * When Phan is invoked with --load-baseline=path/to/baseline.php,
 * The pre-existing issues listed in this file won't be emitted.
 *
 * This file can be updated by invoking Phan with --save-baseline=path/to/baseline.php
 * (can be combined with --load-baseline)
 */
return [

EOT;
        $contents .= $this->generateSuppressIssueSummary();
        $contents .= $this->generateSuppressFileEntries();
        $contents .= "];\n";
        return $contents;
    }

    private static function roundSuppressCount(int $count): int
    {
        if ($count <= 10) {
            return $count;
        }
        if ($count <= 100) {
            return $count - $count % 5;
        }
        return $count - $count % 10;
    }

    private static function getSuppressCountLabel(int $count): string
    {
        if ($count <= 10) {
            return (string)$count;
        }
        // Round counts over 100 down to a multiple of 10, etc.
        return \sprintf('%d+', self::roundSuppressCount($count));
    }

    /**
     * Generates a summary of the suppressed issues, sorted by approximate counts.
     *
     * This is useful for checking if issues that you don't want in your project
     * have been added into a large baseline.
     */
    private function generateSuppressIssueSummary(): string
    {
        $entries = [];
        foreach ($this->suppressions_by_type as $issueType => $hashes) {
            $count = \count($hashes);
            $count_name = self::getSuppressCountLabel($count);
            $entries[] = [
                -self::roundSuppressCount($count),
                $issueType,
                \sprintf("    // %s : %s %s\n", $issueType, $count_name, $count !== 1 ? "occurrences" : "occurrence"),
            ];
        }
        if (!$entries) {
            return "    // This baseline has no suppressions\n";
        }
        // Sort by most common issues first, breaking ties by the name of the issue.
        \sort($entries);
        $result = "    // # Issue statistics:\n";
        foreach ($entries as [2 => $entry_text]) {
            $result .= $entry_text;
        }
        $result .= "\n";
        return $result;
    }

    /**
     * Generates the per-file suppressions of the baseline.
     *
     * This is useful for checking if issues that you don't want in your project
     * have been added into a large baseline.
     */
    private function generateSuppressFileEntries(): string
    {
        $result = '';
        $result .= "    // Currently, file_suppressions and directory_suppressions are the only supported suppressions\n";
        $result .= "    'file_suppressions' => [\n";
        \uksort($this->suppressions_by_file, 'strcmp');
        foreach ($this->suppressions_by_file as $fileName => $type_set) {
            $types = \array_map('strval', \array_keys($type_set));
            \usort($types, 'strcmp');
            $result .= "        '$fileName' => [" . \implode(', ', \array_map(static function (string $type): string {
                    return "'" . $type . "'";
            }, $types)) . "],\n";
        }
        $result .= "    ],\n";
        $result .= "    // 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.\n";
        $result .= "    // (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)\n";
        return $result;
    }
}
