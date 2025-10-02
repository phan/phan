<?php

declare(strict_types=1);

namespace Phan\Library\IncrementalAnalysis;

use function file_exists;
use function file_put_contents;
use function is_array;
use function is_dir;
use function mkdir;
use function rename;

/**
 * Manages the incremental analysis manifest file.
 *
 * The manifest stores file hashes, dependencies, and metadata
 * to determine which files need re-analysis.
 *
 * This class has ZERO dependencies on Phan core - it's a pure
 * data structure manager.
 */
class Manifest
{
    /** @var int Manifest format version */
    private const VERSION = 1;

    /** @var string */
    private $manifest_path;

    /** @var array<string,array{hash:string,size:int,mtime:int,dependencies:array,has_issues:bool}> */
    private $files = [];

    /** @var array<string,list<string>> Reverse dependency map (FQSEN -> files that use it) */
    private $reverse_dependencies = [];

    /** @var string Hash of configuration that affects analysis */
    private $config_hash;

    /** @var string Phan version when manifest was created */
    private $phan_version;

    /** @var string PHP version when manifest was created */
    private $php_version;

    /** @var int AST version when manifest was created */
    private $ast_version;

    /**
     * @param string $manifest_path Absolute path to manifest file
     * @param string $config_hash Hash of relevant configuration
     * @param string $phan_version Current Phan version
     * @param string $php_version Current PHP version
     * @param int $ast_version Current AST version
     */
    public function __construct(
        string $manifest_path,
        string $config_hash,
        string $phan_version,
        string $php_version,
        int $ast_version
    ) {
        $this->manifest_path = $manifest_path;
        $this->config_hash = $config_hash;
        $this->phan_version = $phan_version;
        $this->php_version = $php_version;
        $this->ast_version = $ast_version;
    }

    /**
     * Load manifest from disk
     *
     * @return bool True if loaded successfully, false if needs full analysis
     */
    public function load(): bool
    {
        if (!file_exists($this->manifest_path)) {
            return false; // First run, need full analysis
        }

        // Use include for fast, native PHP loading
        $data = @include($this->manifest_path);
        if (!is_array($data)) {
            return false; // Corrupted manifest or load failure
        }

        // Check for invalidation conditions
        if (!$this->isManifestValid($data)) {
            return false;
        }

        $this->files = $data['files'] ?? [];
        $this->reverse_dependencies = $data['reverse_dependencies'] ?? [];

        return true;
    }

    /**
     * Save manifest to disk
     */
    public function save(): void
    {
        $data = [
            'version' => self::VERSION,
            'phan_version' => $this->phan_version,
            'config_hash' => $this->config_hash,
            'php_version' => $this->php_version,
            'ast_version' => $this->ast_version,
            'timestamp' => \date('c'),
            'files' => $this->files,
            'reverse_dependencies' => $this->reverse_dependencies,
        ];

        // Use var_export() for faster, more robust serialization
        // No UTF-8 encoding issues, no JSON overhead, just native PHP
        $php_code = "<?php\nreturn " . \var_export($data, true) . ";\n";

        // Ensure directory exists
        $dir = \dirname($this->manifest_path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return;
            }
        }

        // Atomic write
        $temp_path = $this->manifest_path . '.tmp';
        if (file_put_contents($temp_path, $php_code) === false) {
            return; // Failed to write
        }
        if (!rename($temp_path, $this->manifest_path)) {
            return;
        }
    }

    /**
     * Check if manifest is valid for current environment
     *
     * @param array<string,mixed> $data
     */
    private function isManifestValid(array $data): bool
    {
        // Version mismatch
        if (($data['version'] ?? 0) !== self::VERSION) {
            return false;
        }

        // Phan version changed (major/minor only, patch is ok)
        if (!self::isSameMajorMinorVersion($data['phan_version'] ?? '', $this->phan_version)) {
            return false;
        }

        // Config changed
        if (($data['config_hash'] ?? '') !== $this->config_hash) {
            return false;
        }

        // PHP version changed (major/minor only)
        if (!self::isSameMajorMinorVersion($data['php_version'] ?? '', $this->php_version)) {
            return false;
        }

        // AST version changed
        if (($data['ast_version'] ?? 0) !== $this->ast_version) {
            return false;
        }

        return true;
    }

    /**
     * Compare major.minor versions (ignore patch)
     */
    private static function isSameMajorMinorVersion(string $v1, string $v2): bool
    {
        $parts1 = \explode('.', $v1);
        $parts2 = \explode('.', $v2);
        return ($parts1[0] ?? '') === ($parts2[0] ?? '') &&
               ($parts1[1] ?? '') === ($parts2[1] ?? '');
    }

    /**
     * Check if file exists in manifest
     */
    public function hasFile(string $file_path): bool
    {
        return isset($this->files[$file_path]);
    }

    /**
     * Get stored hash for file
     *
     * @return string Empty string if file not in manifest
     */
    public function getFileHash(string $file_path): string
    {
        return $this->files[$file_path]['hash'] ?? '';
    }

    /**
     * Update file metadata
     *
     * @param string $file_path
     * @param string $hash
     * @param int $size
     * @param int $mtime
     * @param array{declares:list<string>,extends:list<string>,implements:list<string>} $dependencies
     */
    public function updateFile(
        string $file_path,
        string $hash,
        int $size,
        int $mtime,
        array $dependencies
    ): void {
        // Preserve has_issues flag if it exists
        $has_issues = $this->files[$file_path]['has_issues'] ?? false;

        $this->files[$file_path] = [
            'hash' => $hash,
            'size' => $size,
            'mtime' => $mtime,
            'dependencies' => $dependencies,
            'has_issues' => $has_issues,
        ];
    }

    /**
     * Remove file from manifest
     */
    public function removeFile(string $file_path): void
    {
        unset($this->files[$file_path]);
    }

    /**
     * Get all files in manifest
     *
     * @return list<string>
     */
    public function getAllFiles(): array
    {
        return \array_keys($this->files);
    }

    /**
     * Get FQSENs declared in a file
     *
     * @return list<string>
     */
    public function getDeclaredFQSENs(string $file_path): array
    {
        if (!isset($this->files[$file_path])) {
            return [];
        }
        return $this->files[$file_path]['dependencies']['declares'] ?? [];
    }

    /**
     * Build reverse dependency map
     *
     * This maps each FQSEN to the list of files that depend on it.
     * Must be called after all files are updated, before querying dependencies.
     */
    public function buildReverseDependencies(): void
    {
        $this->reverse_dependencies = [];

        foreach ($this->files as $file_path => $file_data) {
            $deps = $file_data['dependencies'] ?? [];

            // Track which files depend on which FQSENs (via extends/implements)
            // Note: We only track structural dependencies, not usage dependencies
            foreach (['extends', 'implements'] as $type) {
                foreach ($deps[$type] ?? [] as $fqsen) {
                    if (!isset($this->reverse_dependencies[$fqsen])) {
                        $this->reverse_dependencies[$fqsen] = [];
                    }
                    $this->reverse_dependencies[$fqsen][] = $file_path;
                }
            }
        }

        // Deduplicate
        foreach ($this->reverse_dependencies as $fqsen => $files) {
            $this->reverse_dependencies[$fqsen] = \array_values(\array_unique($files));
        }
    }

    /**
     * Get files that depend on the given FQSENs
     *
     * @param list<string> $fqsens
     * @return list<string>
     */
    public function getDependentFiles(array $fqsens): array
    {
        $dependent_files = [];
        foreach ($fqsens as $fqsen) {
            if (isset($this->reverse_dependencies[$fqsen])) {
                \array_push($dependent_files, ...$this->reverse_dependencies[$fqsen]);
            }
        }
        return \array_values(\array_unique($dependent_files));
    }

    /**
     * Get statistics about the manifest
     *
     * @return array{file_count:int,total_size:int,total_dependencies:int}
     */
    public function getStats(): array
    {
        $total_size = 0;
        $total_deps = 0;

        foreach ($this->files as $file_data) {
            $total_size += $file_data['size'] ?? 0;
            $deps = $file_data['dependencies'] ?? [];
            foreach (['declares', 'extends', 'implements'] as $type) {
                $total_deps += \count($deps[$type] ?? []);
            }
        }

        return [
            'file_count' => \count($this->files),
            'total_size' => $total_size,
            'total_dependencies' => $total_deps,
        ];
    }

    /**
     * Mark whether a file has unsuppressed issues
     */
    public function markFileHasIssues(string $file_path, bool $has_issues): void
    {
        if (isset($this->files[$file_path])) {
            $this->files[$file_path]['has_issues'] = $has_issues;
        }
    }

    /**
     * Check if a file had issues in the last run
     */
    public function fileHadIssues(string $file_path): bool
    {
        return $this->files[$file_path]['has_issues'] ?? false;
    }

    /**
     * Get all files that had issues in the last run
     *
     * @return list<string>
     */
    public function getFilesWithIssues(): array
    {
        $files_with_issues = [];
        foreach ($this->files as $file_path => $file_data) {
            if ($file_data['has_issues'] ?? false) {
                $files_with_issues[] = $file_path;
            }
        }
        return $files_with_issues;
    }
}
