<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\CLI;
use Phan\IssueInstance;
use Phan\Library\Paths;
use Phan\PluginV3;
use Phan\PluginV3\SubscribeEmitIssueCapability;

/**
 * Suppresses issues based on the contents of a baseline file.
 *
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class BaselineLoadingPlugin extends PluginV3 implements
    SubscribeEmitIssueCapability
{
    /**
     * @var array<string,list<string>>
     * Maps relative file paths to a list of issue kinds that are suppressed everywhere in the file by the baseline.
     */
    private $file_suppressions = [];

    /**
     * @var array<string,list<string>>
     * Maps relative directory paths to a list of issue kinds that are suppressed everywhere in the file by the baseline.
     */
    private $directory_suppressions = [];

    public function __construct(string $baseline_path)
    {
        // Evaluate the php file with the baseline contents.
        // Other file formats or a safe evaluation may be implemented later.
        $baseline = require($baseline_path);
        if (!\is_array($baseline)) {
            CLI::printWarningToStderr("Phan read an invalid baseline from '$baseline_path' : Expected it to return an array, got " . \gettype($baseline_path) . "\n");
            return;
        }
        if (!\array_key_exists('file_suppressions', $baseline) && !\array_key_exists('directory_suppressions', $baseline)) {
            CLI::printWarningToStderr("Phan read an invalid baseline from '$baseline_path' : Expected the returned array to contain the key 'file_suppressions' or 'directory_suppressions' (new baselines can be generated with --save-baseline)\n");
            return;
        }

        // file_suppressions and directory suppressions are currently the only way to suppress issues in a baseline. Other ways may be added later.
        $this->file_suppressions = $baseline['file_suppressions'] ?? [];
        $this->directory_suppressions = self::normalizeDirectorySuppressions($baseline['directory_suppressions'] ?? []);
    }

    /**
     * This will be called if both of these conditions hold:
     *
     * 1. Phan's file and element-based suppressions did not suppress the issue
     * 2. Earlier plugins didn't suppress the issue.
     *
     * @param IssueInstance $issue_instance the issue that would be emitted
     *
     * @return bool true if the issue should be suppressed for the baseline.
     * @override
     */
    public function onEmitIssue(IssueInstance $issue_instance): bool {
        return $this->shouldSuppressIssueTypeInFile(
            $issue_instance->getIssue()->getType(),
            $issue_instance->getFile()
        );
    }

    /**
     * Check if the given issue type should be suppressed in the given file path.
     * @internal - used for testing
     */
    public function shouldSuppressIssueTypeInFile(string $issue_type, string $file) : bool {
        $suppressed_by_file = \in_array($issue_type, $this->file_suppressions[$file] ?? [], true);
        if ($suppressed_by_file) {
            return true;
        }

        // Support suppressing '.' in a baseline (may be useful when plugins affecting type inference get enabled)
        $normalized_file = self::normalizeDirectoryPathString($file);
        if (\in_array($issue_type, $this->directory_suppressions[''] ?? [], true)) {
            if (!Paths::isAbsolutePath($issue_type) && \substr($normalized_file, 0, 3) !== '../') {
                return true;
            }
        }

        // Not suppressed by file, check for suppression by directory

        $parts = self::normalizeDirectoryPathString($file);
        $parts = \explode('/', $parts);
        \array_pop($parts); // Remove file name

        $dirPath = '';
        // Check from least specific path to most specific path if any should be suppressed

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $dirPath .= $part;
            if (\in_array($issue_type, $this->directory_suppressions[$dirPath] ?? [], true)) {
                return true;
            }
            $dirPath .= '/';
        }

        return false;
    }

    /**
     * Normalize directory path entries in directory_suppressions from baseline.
     *
     * @param array<string,list<string>> $dir_suppressions
     * @return array<string,list<string>>
     */
    private static function normalizeDirectorySuppressions(array $dir_suppressions): array
    {
        foreach ($dir_suppressions as $file_path => $rules) {
            $new_file_path = self::normalizeDirectoryPathString($file_path);

            if ($new_file_path !== $file_path) {
                $old_suppressions = $dir_suppressions[$new_file_path] ?? null;
                if ($old_suppressions) {
                    $dir_suppressions[$new_file_path] = \array_merge($old_suppressions, $rules);
                } else {
                    $dir_suppressions[$new_file_path] = $rules;
                }
                unset($dir_suppressions[$file_path]);
            }
        }
        return $dir_suppressions;
    }

    /**
     * Normalize path string.
     *
     * @param string $path
     */
    private static function normalizeDirectoryPathString(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $path = \rtrim($path, '/');
        $path = \preg_replace('@^(\./)+@', '', $path);
        if ($path === '.') {
            return '';
        }
        return $path;
    }
}
