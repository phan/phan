<?php

declare(strict_types=1);

namespace Phan\Library\IncrementalAnalysis;

use function array_merge;
use function array_unique;
use function array_values;
use function count;

/**
 * Detects which files have changed and expands to include dependents.
 *
 * This is the main orchestrator for incremental analysis.
 * It uses Manifest, FileHasher, and DependencyTracker to determine
 * which files need re-analysis.
 *
 * This class has ZERO dependencies on Phan core.
 */
class ChangeDetector
{
    /** @var Manifest */
    private $manifest;

    /** @var list<string> Files that have changed */
    private $changed_files = [];

    /** @var list<string> Files that are new */
    private $new_files = [];

    /** @var list<string> Files that were deleted */
    private $deleted_files = [];

    /** @var list<string> Files that need re-analysis (changed + dependents) */
    private $files_to_analyze = [];

    public function __construct(Manifest $manifest)
    {
        $this->manifest = $manifest;
    }

    /**
     * Detect which files have changed
     *
     * @param list<string> $current_files All files in the project
     * @return array{changed:int,new:int,deleted:int,had_issues:int,to_analyze:int}
     */
    public function detectChanges(array $current_files): array
    {
        $this->changed_files = [];
        $this->new_files = [];
        $this->deleted_files = [];

        // Check each current file
        $current_file_set = \array_flip($current_files);
        foreach ($current_files as $file_path) {
            if (!$this->manifest->hasFile($file_path)) {
                // New file
                $this->new_files[] = $file_path;
                $this->changed_files[] = $file_path;
            } else {
                // Existing file - check if changed
                $old_hash = $this->manifest->getFileHash($file_path);
                $new_hash = FileHasher::hashFile($file_path);

                if ($old_hash !== $new_hash) {
                    $this->changed_files[] = $file_path;
                }
            }
        }

        // Check for deleted files
        foreach ($this->manifest->getAllFiles() as $file_path) {
            if (!isset($current_file_set[$file_path])) {
                $this->deleted_files[] = $file_path;
            }
        }

        // Expand to dependents
        $this->expandToDependents();

        // Include files that had issues in the last run
        // This ensures users always see issues until they're fixed
        $files_with_issues = $this->manifest->getFilesWithIssues();
        $had_issues_count = 0;
        foreach ($files_with_issues as $file_path) {
            // Only add if file still exists and isn't already being analyzed
            if (isset($current_file_set[$file_path]) && !\in_array($file_path, $this->files_to_analyze, true)) {
                $this->files_to_analyze[] = $file_path;
                $had_issues_count++;
            }
        }

        return [
            'changed' => count($this->changed_files),
            'new' => count($this->new_files),
            'deleted' => count($this->deleted_files),
            'had_issues' => $had_issues_count,
            'to_analyze' => count($this->files_to_analyze),
        ];
    }

    /**
     * Expand changed files to include all dependents
     */
    private function expandToDependents(): void
    {
        // Start with changed files
        $to_analyze = $this->changed_files;

        // Get FQSENs declared in changed files
        $changed_fqsens = [];
        foreach ($this->changed_files as $file_path) {
            $fqsens = $this->manifest->getDeclaredFQSENs($file_path);
            \array_push($changed_fqsens, ...$fqsens);
        }

        // Also include FQSENs from deleted files
        foreach ($this->deleted_files as $file_path) {
            $fqsens = $this->manifest->getDeclaredFQSENs($file_path);
            \array_push($changed_fqsens, ...$fqsens);
        }

        // Find files that depend on these FQSENs
        if (count($changed_fqsens) > 0) {
            $dependent_files = $this->manifest->getDependentFiles($changed_fqsens);
            $to_analyze = array_merge($to_analyze, $dependent_files);
        }

        // Namespace-level invalidation: Extract namespace prefixes from changed FQSENs
        // and re-analyze all files that declare symbols in those namespaces.
        // This is a very conservative over-invalidation strategy to catch cross-directory
        // function calls and dependencies that aren't explicitly tracked.
        $changed_namespaces = [];
        foreach ($changed_fqsens as $fqsen) {
            // Extract namespace from FQSEN (e.g., \Foo\Bar\Baz\MyClass -> Foo\Bar\Baz)
            $namespace = self::extractNamespace($fqsen);
            if ($namespace !== '') {
                $changed_namespaces[$namespace] = true;

                // Also invalidate parent namespaces to catch broader dependencies
                // e.g., if Foo\Bar\Baz changes, also invalidate Foo\Bar and Foo
                $parts = \explode('\\', $namespace);
                $current = '';
                foreach ($parts as $part) {
                    $current = $current === '' ? $part : $current . '\\' . $part;
                    $changed_namespaces[$current] = true;
                }
            }
        }

        // Add all files that declare symbols in affected namespaces
        if (count($changed_namespaces) > 0) {
            foreach ($this->manifest->getAllFiles() as $file_path) {
                $file_fqsens = $this->manifest->getDeclaredFQSENs($file_path);
                foreach ($file_fqsens as $fqsen) {
                    $namespace = self::extractNamespace($fqsen);
                    if ($namespace !== '' && isset($changed_namespaces[$namespace])) {
                        $to_analyze[] = $file_path;
                        break; // File already added, no need to check more FQSENs
                    }
                }
            }
        }

        // Deduplicate and store
        $this->files_to_analyze = array_values(array_unique($to_analyze));
    }

    /**
     * Extract namespace from FQSEN
     *
     * @param string $fqsen Fully qualified structural element name
     * @return string Namespace (without leading backslash)
     */
    private static function extractNamespace(string $fqsen): string
    {
        // Remove leading backslash if present
        $fqsen = \ltrim($fqsen, '\\');

        // Find last backslash to separate namespace from element name
        $last_backslash = \strrpos($fqsen, '\\');
        if ($last_backslash === false) {
            // No namespace (global namespace)
            return '';
        }

        return \substr($fqsen, 0, $last_backslash);
    }

    /**
     * Get list of files that need re-analysis
     *
     * @return list<string>
     */
    public function getFilesToAnalyze(): array
    {
        return $this->files_to_analyze;
    }

    /**
     * Get list of changed files
     *
     * @return list<string>
     */
    public function getChangedFiles(): array
    {
        return $this->changed_files;
    }

    /**
     * Get list of new files
     *
     * @return list<string>
     */
    public function getNewFiles(): array
    {
        return $this->new_files;
    }

    /**
     * Get list of deleted files
     *
     * @return list<string>
     */
    public function getDeletedFiles(): array
    {
        return $this->deleted_files;
    }

    /**
     * Check if any files changed
     */
    public function hasChanges(): bool
    {
        return count($this->changed_files) > 0 || count($this->deleted_files) > 0;
    }
}
