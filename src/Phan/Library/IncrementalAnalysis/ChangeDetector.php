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
     * @return array{changed:int,new:int,deleted:int,to_analyze:int}
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

        return [
            'changed' => count($this->changed_files),
            'new' => count($this->new_files),
            'deleted' => count($this->deleted_files),
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

        // Deduplicate and store
        $this->files_to_analyze = array_values(array_unique($to_analyze));
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
