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

        // null = auto-detect (enable if not in daemon/language server mode or tests)
        if ($value === null) {
            // Disable during PHPUnit tests to avoid test interference
            // Check PHAN_PHPUNIT_RUNNING first as it's more reliable
            if (defined('PHAN_PHPUNIT_RUNNING') && \constant('PHAN_PHPUNIT_RUNNING')) {
                if (self::isDebugEnabled()) {
                    // @phan-suppress-next-line PhanPluginRemoveDebugCall - intentional debug output
                    \fwrite(STDERR, "Incremental analysis: Disabled (PHAN_PHPUNIT_RUNNING detected)\n");
                }
                return false;
            }
            if (\class_exists(\PHPUnit\Framework\TestCase::class, false)) {
                if (self::isDebugEnabled()) {
                    // @phan-suppress-next-line PhanPluginRemoveDebugCall - intentional debug output
                    \fwrite(STDERR, "Incremental analysis: Disabled (PHPUnit test class detected)\n");
                }
                return false;
            }
            $enabled = !CLI::isDaemonOrLanguageServer();
            if (self::isDebugEnabled()) {
                // @phan-suppress-next-line PhanPluginRemoveDebugCall - intentional debug output
                \fwrite(STDERR, \sprintf(
                    "Incremental analysis: %s (auto-detected, daemon/language server: %s)\n",
                    $enabled ? 'Enabled' : 'Disabled',
                    CLI::isDaemonOrLanguageServer() ? 'yes' : 'no'
                ));
            }
            return $enabled;
        }

        if (self::isDebugEnabled()) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall - intentional debug output
            \fwrite(STDERR, \sprintf(
                "Incremental analysis: %s (explicitly configured)\n",
                $value ? 'Enabled' : 'Disabled'
            ));
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
        $project_root = PhanConfig::getProjectRootDirectory();
        return $project_root . '/.phan/incremental-manifest.json';
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
            'baseline_path' => PhanConfig::getValue('baseline_path'),
            'quick_mode' => PhanConfig::getValue('quick_mode'),
            'backward_compatibility_checks' => PhanConfig::getValue('backward_compatibility_checks'),
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
