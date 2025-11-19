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
     * @var array<string,array<string,array<string,true>>>
     * Maps relative file paths to a map of issue kinds to symbol names suppressed in that file.
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
        $this->file_suppressions = self::normalizeFileSuppressions($baseline['file_suppressions'] ?? []);
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
    public function onEmitIssue(IssueInstance $issue_instance): bool
    {
        return $this->shouldSuppressIssue(
            $issue_instance->getIssue()->getType(),
            $issue_instance->getFile(),
            $issue_instance->getBaselineSymbol()
        );
    }

    /**
     * Check if the given issue type should be suppressed in the given file path.
     * @internal - used for testing
     */
    public function shouldSuppressIssue(string $issue_type, string $file, string $symbol): bool
    {
        $normalized_symbol = self::normalizeSymbol($symbol);
        $issue_map = $this->file_suppressions[$file][$issue_type] ?? null;
        if ($issue_map && (isset($issue_map[$normalized_symbol]) || isset($issue_map['*']))) {
            return true;
        }

        // Support suppressing '.' in a baseline (may be useful when plugins affecting type inference get enabled)
        $normalized_file = self::normalizeDirectoryPathString($file);

        // Check normalized path to suppress file paths with backslashes on Windows
        $issue_map = $this->file_suppressions[$normalized_file][$issue_type] ?? null;
        if ($issue_map && (isset($issue_map[$normalized_symbol]) || isset($issue_map['*']))) {
            return true;
        }

        if (\in_array($issue_type, $this->directory_suppressions[''] ?? [], true)) {
            if (!Paths::isAbsolutePath($issue_type) && !str_starts_with($normalized_file, '../')) {
                return true;
            }
        }

        // Not suppressed by file, check for suppression by directory

        $parts = \explode('/', $normalized_file);
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
     * @param array<string,mixed> $file_suppressions
     * @return array<string,array<string,array<string,true>>>
     */
    private static function normalizeFileSuppressions(array $file_suppressions): array
    {
        $result = [];
        foreach ($file_suppressions as $file => $entries) {
            $issue_map = [];
            foreach ($entries as $key => $value) {
                if (\is_int($key)) {
                    $issue_type = (string)$value;
                    $issue_map[$issue_type]['*'] = true;
                } else {
                    $issue_type = (string)$key;
                    if (\is_array($value)) {
                        $symbol_map = [];
                        foreach ($value as $symbol_key => $symbol_value) {
                            if (\is_string($symbol_key)) {
                                $symbol_map[self::normalizeSymbol($symbol_key)] = true;
                            } else {
                                $symbol_map[self::normalizeSymbol((string)$symbol_value)] = true;
                            }
                        }
                        if (!$symbol_map) {
                            $symbol_map['*'] = true;
                        }
                    } else {
                        $symbol_map = ['*' => true];
                    }
                    if (isset($issue_map[$issue_type])) {
                        $issue_map[$issue_type] += $symbol_map;
                    } else {
                        $issue_map[$issue_type] = $symbol_map;
                    }
                }
            }
            $result[$file] = $issue_map;
        }
        return $result;
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

    private static function normalizeSymbol(string $symbol): string
    {
        $symbol = \preg_replace('/anonymous_class_[0-9a-f]+/i', 'anonymous_class', $symbol) ?? $symbol;
        $symbol = \preg_replace('/\\\\closure_[0-9a-f]+/i', '\\closure', $symbol) ?? $symbol;
        return $symbol;
    }
}
