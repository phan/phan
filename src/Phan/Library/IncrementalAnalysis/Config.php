<?php

declare(strict_types=1);

namespace Phan\Library\IncrementalAnalysis;

use Phan\CLI;
use Phan\Config as PhanConfig;

use function hash;
use function json_encode;

/**
 * Configuration bridge for incremental analysis.
 *
 * This class connects the standalone incremental analysis library
 * with Phan's configuration system.
 */
class Config
{
    /**
     * Check if incremental analysis is enabled
     */
    public static function isEnabled(): bool
    {
        $value = PhanConfig::getValue('incremental_analysis');

        // Default is false (disabled) - must explicitly enable with -i or in config
        if ($value === null) {
            return false;
        }

        return (bool)$value;
    }

    /**
     * Check if force full analysis is requested
     */
    public static function isForceFull(): bool
    {
        return (bool)PhanConfig::getValue('force_full_analysis');
    }

    /**
     * Get path to manifest file
     */
    public static function getManifestPath(): string
    {
        // If there's a .phan/config.php in the working directory, use that location
        // Otherwise fall back to project root directory
        // This ensures manifest is saved where user expects it, even with symlinks
        $working_dir = PhanConfig::getWorkingDirectory();
        if (file_exists($working_dir . '/.phan/config.php')) {
            return $working_dir . '/.phan/incremental-manifest.php';
        }

        $project_root = PhanConfig::getProjectRootDirectory();
        return $project_root . '/.phan/incremental-manifest.php';
    }

    /**
     * Compute hash of configuration that affects analysis
     *
     * This hash is used to detect config changes that require full re-analysis.
     */
    public static function getConfigHash(): string
    {
        $config_data = [
            'target_php_version' => PhanConfig::getValue('target_php_version'),
            'minimum_target_php_version' => PhanConfig::getValue('minimum_target_php_version'),
            'directory_list' => PhanConfig::getValue('directory_list'),
            'exclude_analysis_directory_list' => PhanConfig::getValue('exclude_analysis_directory_list'),
            'exclude_file_regex' => PhanConfig::getValue('exclude_file_regex'),
            'exclude_file_list' => PhanConfig::getValue('exclude_file_list'),
            'analyzed_file_extensions' => PhanConfig::getValue('analyzed_file_extensions'),
            'plugins' => PhanConfig::getValue('plugins'),
            'plugin_config' => PhanConfig::getValue('plugin_config'),
            'suppress_issue_types' => PhanConfig::getValue('suppress_issue_types'),
            'whitelist_issue_types' => PhanConfig::getValue('whitelist_issue_types'),
            'quick_mode' => PhanConfig::getValue('quick_mode'),
            'dead_code_detection' => PhanConfig::getValue('dead_code_detection'),
            'unused_variable_detection' => PhanConfig::getValue('unused_variable_detection'),
            'redundant_condition_detection' => PhanConfig::getValue('redundant_condition_detection'),
            'simplify_ast' => PhanConfig::getValue('simplify_ast'),
        ];

        $json = json_encode($config_data);
        return hash('sha256', $json !== false ? $json : '');
    }

    /**
     * Get current Phan version
     */
    public static function getPhanVersion(): string
    {
        return CLI::PHAN_VERSION;
    }

    /**
     * Get current PHP version
     */
    public static function getPhpVersion(): string
    {
        return \PHP_VERSION;
    }

    /**
     * Get current AST version
     */
    public static function getAstVersion(): int
    {
        return PhanConfig::AST_VERSION;
    }

    /**
     * Check if debug output is enabled
     */
    public static function isDebugEnabled(): bool
    {
        return (bool)PhanConfig::getValue('debug_output');
    }
}
