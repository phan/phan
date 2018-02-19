<?php declare(strict_types=1);

namespace Phan;

use Phan\AST\Parser;
use Phan\Language\Context;

use ast\Node;

/**
 * This class is used by 'phan --init' to generate a phan config for a composer project.
 */
class ConfigInitializer
{
    public static function initPhanConfig(CLI $cli, array $opts) : int
    {
        Config::setValue('use_polyfill_parser', true);
        $cwd = getcwd();

        $config_path = "$cwd/.phan/config.php";
        if (!isset($opts['init-overwrite'])) {
            if (file_exists($config_path)) {
                printf("phan --init refuses to run: The Phan config already exists at '$config_path'\n(Can pass --init-overwrite to force Phan to overwrite that file)\n");
                return 1;
            }
        }
        if (isset($opts['init-no-composer'])) {
            $composer_settings = [];
            $vendor_path = null;
        } else {
            $composer_json_path = "$cwd/composer.json";
            if (!file_exists($composer_json_path)) {
                printf("phan --init assumes that there will be a composer.json file (at '$composer_json_path')\n(Can pass --init-no-composer if this is not a composer project)\n");
                return 1;
            }
            $composer_settings = json_decode(file_get_contents($composer_json_path), true);
            if (!is_array($composer_settings)) {
                printf("Failed to load '%s'\n", $composer_json_path);
                return 1;
            }

            $vendor_path = "$cwd/vendor";
            if (!is_dir($vendor_path)) {
                printf("phan --init assumes that 'composer.phar install' was run already (expected to find '$vendor_path')\n");
                return 1;
            }
        }
        $init_level = 3;
        $phan_settings = self::createPhanSettingsForComposerSettings($composer_settings, $vendor_path, $opts, $init_level);
        if (!is_array($phan_settings)) {
            echo "phan --init failed to generate settings\n";
            return 1;
        }

        $phan_dir = dirname($config_path);
        if (!file_exists($phan_dir)) {
            if (!mkdir($phan_dir)) {
                echo "Failed to create directory '$phan_dir'\n";
                return 1;
            }
        }
        $settings_file_contents = self::generatePhanConfigFileContents($phan_settings, $init_level);
        file_put_contents($config_path, $settings_file_contents);
        echo $settings_file_contents;
        return 0;
    }

    /**
     * @return array<string,string[]> maps a config name to a list of comment lines about that config
     */
    private static function computeCommentNameDocumentationMap() : array
    {
        // Hackish way of extracting comment lines from Config::DEFAULT_CONFIGURATION
        $config_file_lines = explode("\n", file_get_contents(__DIR__ . '/Config.php'));
        $prev_lines = [];
        $result = [];
        foreach ($config_file_lines as $line) {
            if (preg_match("/^        '([a-z0-9A-Z_]+)'\s*=>/", $line, $matches)) {
                $config_name = $matches[1];
                if (count($prev_lines) > 0) {
                    $result[$config_name] = $prev_lines;
                }
                $prev_lines = [];
                continue;
            }
            if (preg_match('@^\s*//@', $line)) {
                $prev_lines[] = trim($line);
            } else {
                $prev_lines = [];
            }
        }
        return $result;
    }

    public static function generateCommentForSetting(string $setting_name) : string
    {
        static $comment_source = null;
        if (is_null($comment_source)) {
            $comment_source = self::computeCommentNameDocumentationMap();
        }
        // TODO: Read from src/Phan/Config.php
        $lines = $comment_source[$setting_name] ?? null;
        if (empty($lines)) {
            return '';
        }
        return implode('', array_map(function (string $line) : string {
            return "    $line\n";
        }, $lines));
    }

    public static function generateEntrySnippetForSetting(string $setting_name, $setting_value) : string
    {
        $source = self::generateCommentForSetting($setting_name);
        $source .= '    ';
        $source .= var_export($setting_name, true) . ' => ';
        if (is_array($setting_value)) {
            if (count($setting_value) > 0) {
                $source .= "[\n";
                foreach ($setting_value as $key => $element) {
                    assert(is_int($key));
                    $source .= '        ' . var_export($element, true) . ",\n";
                }
                $source .= "    ],\n";
            } else {
                $source .= "[],\n";
            }
        } else {
            $encoded_value = var_export($setting_value, true);
            if ($setting_name === 'minimum_severity') {
                switch ($setting_value) {
                    case Issue::SEVERITY_LOW:
                        $encoded_value = 'Issue::SEVERITY_LOW';
                        break;
                    case Issue::SEVERITY_NORMAL:
                        $encoded_value = 'Issue::SEVERITY_NORMAL';
                        break;
                    case Issue::SEVERITY_CRITICAL:
                        $encoded_value = 'Issue::SEVERITY_CRITICAL';
                        break;
                }
            }

            $source .= "$encoded_value,\n";
        }
        return $source;
    }

    public static function generatePhanConfigFileContents(array $phan_settings, int $init_level) : string
    {
        $source = <<<EOT
<?php

use Phan\Issue;

/**
 * This configuration file was automatically generated by 'phan --init --init-level=$init_level'
 *
 * TODOs (added by 'phan --init'):
 *
 * - Go through this file and verify that there are no missing/unnecessary files/directories.
 *   (E.g. this only includes direct composer dependencies - You may have to manually add indirect composer dependencies to 'directory_list')
 * - Look at 'plugins' and add or remove plugins if appropriate (see https://github.com/phan/phan/tree/master/.phan/plugins#plugins)
 * - Add global suppressions for pre-existing issues to suppress_issue_types (https://github.com/phan/phan/wiki/Tutorial-for-Analyzing-a-Large-Sloppy-Code-Base)
 *
 * This configuration will be read and overlayed on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * A Note About Paths
 * ==================
 *
 * Files referenced from this file should be defined as
 *
 * ```
 *   Config::projectPath('relative_path/to/file')
 * ```
 *
 * where the relative path is relative to the root of the
 * project which is defined as either the working directory
 * of the phan executable or a path passed in via the CLI
 * '-d' flag.
 */
return [

EOT;
        foreach ($phan_settings as $setting_name => $setting_value) {
            $source .= "\n";
            $source .= self::generateEntrySnippetForSetting($setting_name, $setting_value);
        }
        $source .= "];\n";
        return $source;
    }

    const LEVEL_MAP = [
        'strict'  => 1,
        'strong'  => 2,
        'average' => 3,
        'normal'  => 3,
        'weak'    => 4,
        'weakest' => 3,
    ];

    /**
     * @param array $composer_settings (can be empty for --init-no-composer)
     * @param ?string $vendor_path (can be null for --init-no-composer)
     * @param array $opts parsed from getopt
     * @return ?array
     */
    private static function createPhanSettingsForComposerSettings(array $composer_settings, $vendor_path, array $opts, int &$level = 3)
    {
        $level = $opts['init-level'] ?? 3;
        $level = self::LEVEL_MAP[strtolower((string)$level)] ?? $level;
        if (filter_var($level, FILTER_VALIDATE_INT) === false) {
            echo "Invalid --init-level=$level\n";
            return null;
        }
        $level = max(1, min(5, (int)$level));
        $is_strong_or_weaker_level = $level >= 2;
        $is_average_level = $level >= 3;
        $is_weak_level    = $level >= 4;
        $is_weakest_level = $level >= 5;

        $cwd = getcwd();
        list($project_directory_list, $project_file_list) = self::extractAutoloadFilesAndDirectories('', $composer_settings);
        if ($vendor_path !== null && count($project_directory_list) === 0 && count($project_file_list) === 0) {
            echo "phan --init expects composer.json to contain 'autoload' psr-4 directories\n";
            return null;
        }
        $minimum_severity = $is_weak_level ? Issue::SEVERITY_NORMAL : Issue::SEVERITY_LOW;
        if ($is_weakest_level) {
            $plugins = [];
        } elseif ($is_average_level) {
            $plugins = [
                'AlwaysReturnPlugin',
                'PregRegexCheckerPlugin',
                'UnreachableCodePlugin',
            ];
        } else {
            $plugins = [
                'AlwaysReturnPlugin',
                'DollarDollarPlugin',
                'DuplicateArrayKeyPlugin',
                'PregRegexCheckerPlugin',
                'PrintfCheckerPlugin',
                'UnreachableCodePlugin',
            ];
        }

        $phan_settings = [
            'allow_missing_properties' => $is_weak_level,
            'null_casts_as_any_type'   => $is_weak_level,
            'null_casts_as_array'      => $is_average_level,
            'array_casts_as_null'      => $is_average_level,
            'scalar_implicit_cast'     => $is_weak_level,
            'scalar_array_key_cast'    => $is_average_level,
            // TODO: Migrate to a smaller subset scalar_implicit_partial as analysis gets stricter?
            'scalar_implicit_partial'  => [],
            'ignore_undeclared_variables_in_global_scope' => $is_average_level,
            'backward_compatibility_checks' => false,  // this is slow
            'check_docblock_signature_return_type_match' => !$is_average_level,
            'prefer_narrowed_phpdoc_param_type' => true,
            'prefer_narrowed_phpdoc_return_type' => true,
            'ensure_signature_compatibility' => !$is_weak_level,
            'phpdoc_type_mapping' => [],
            'dead_code_detection' => false,  // this is slow
            'quick_mode' => $is_weakest_level,
            'simplify_ast' => true,
            'generic_types_enabled' => true,
            'globals_type_map' => [],
            'minimum_severity' => $minimum_severity,
            'suppress_issue_types' => [],
            'exclude_file_regex' => $vendor_path !== null ? '@^vendor/.*/(tests?|Tests?)/@' : null,
            'exclude_file_list' => [],
            'exclude_analysis_directory_list' => $vendor_path !== null ? [
                'vendor/'
            ] : [],
            'processes' => 1,
            'analyzed_file_extensions' => ['php'],
            'autoload_internal_extension_signatures' => [],
            'plugins' => $plugins,
        ];

        $phan_directory_list = $project_directory_list;
        $phan_file_list = $project_file_list;

        // TODO: Figure out which require-dev directories can be skipped
        $require_directories = $composer_settings['require'] ?? [];
        $require_dev_directories = $composer_settings['require-dev'] ?? [];
        foreach (array_merge($require_directories, $require_dev_directories) as $requirement => $_) {
            if (substr_count($requirement, '/') !== 1) {
                // e.g. ext-ast, php >= 7.0, etc.
                continue;
            }
            $path_to_require = "$vendor_path/$requirement";
            if (!is_dir($path_to_require)) {
                $requirement = strtolower($requirement);
                $path_to_require = "$vendor_path/$requirement";
                if (!is_dir($path_to_require)) {
                    echo "Warning: $path_to_require does not exist, continuing\n";
                    continue;
                }
            }
            $path_to_composer_json = "$path_to_require/composer.json";
            if (!file_exists($path_to_composer_json)) {
                echo "Warning: $path_to_composer_json does not exist, continuing\n";
                continue;
            }
            $library_composer_settings = json_decode(file_get_contents($path_to_composer_json), true);
            if (!is_array($library_composer_settings)) {
                echo "Warning: $path_to_composer_json is invalid JSON, continuing\n";
                continue;
            }

            list($library_directory_list, $library_file_list) = self::extractAutoloadFilesAndDirectories("vendor/$requirement", $library_composer_settings);
            $phan_directory_list = array_merge($phan_directory_list, $library_directory_list);
            $phan_file_list = array_merge($phan_file_list, $library_file_list);
        }
        foreach (self::getArrayOption($opts, 'init-analyze-dir') as $extra_dir) {
            $path_to_require = "$cwd/$extra_dir";
            if (!is_dir($path_to_require)) {
                echo "phan --init-analyze-dir was given a missing/invalid relative directory '$extra_dir'\n";
                return null;
            }
            $phan_directory_list[] = $extra_dir;
        }

        foreach ($composer_settings['bin'] ?? [] as $relative_path_to_binary) {
            if (self::isPHPBinary($relative_path_to_binary)) {
                $phan_file_list[] = $relative_path_to_binary;
            }
        }
        foreach (self::getArrayOption($opts, 'init-analyze-file') as $extra_file) {
            $path_to_require = "$cwd/$extra_file";
            if (!is_file($path_to_require)) {
                echo "phan --init-analyze-file was given a missing/invalid relative file '$extra_file'\n";
                return null;
            }
            $phan_file_list[] = $extra_file;
        }

        if (count($phan_file_list) === 0 && count($phan_directory_list) === 0) {
            echo "phan --init failed to find any directories or files to analyze, giving up.\n";
            return null;
        }
        sort($phan_directory_list);
        sort($phan_file_list);

        $phan_settings['directory_list'] = array_unique($phan_directory_list);
        $phan_settings['file_list'] = array_unique($phan_file_list);
        return $phan_settings;
    }

    /**
     * @return array<int,array<int,string>> [$directory_list, $file_list]
     */
    private static function extractAutoloadFilesAndDirectories(string $relative_dir, array $composer_settings)
    {
        $directory_list = [];
        $file_list = [];
        $autoload_setting = $composer_settings['autoload'] ?? [];
        $autoload_directories = array_merge(
            $autoload_setting['psr-4'] ?? [],
            $autoload_setting['psr-0'] ?? [],
            $autoload_setting['classmap'] ?? []
        );

        foreach ($autoload_directories as $lib_list) {
            if (is_string($lib_list)) {
                $lib_list = [$lib_list];
            }
            foreach ($lib_list as $lib) {
                if (!is_string($lib)) {
                    echo "Warning: unexpected autoload field in '$relative_dir/composer.json'\n";
                    continue;
                }
                $composer_lib_relative_path = "$relative_dir/$lib";
                $composer_lib_absolute_path = getcwd() . "/$composer_lib_relative_path";
                if (!file_exists($composer_lib_absolute_path)) {
                    echo "Warning: could not find '$composer_lib_relative_path'\n";
                    continue;
                }

                if (is_dir($composer_lib_absolute_path)) {
                    $directory_list[] = trim($composer_lib_relative_path, '/');
                } elseif (is_file($composer_lib_relative_path)) {
                    $file_list[] = trim($composer_lib_relative_path, '/');
                    ;
                }
            }
        }
        return [array_unique($directory_list), array_unique($file_list)];
    }

    private static function getArrayOption(array $opts, string $key)
    {
        $values = $opts[$key] ?? [];
        if (is_string($values)) {
            return [$values];
        }
        return is_array($values) ? $values : [];
    }

    public static function isPHPBinary(string $relative_path) : bool
    {
        $cwd = getcwd();
        $absolute_path = "$cwd/$relative_path";
        if (!file_exists($absolute_path)) {
            printf("Failed to find '%s', continuing\n", $absolute_path);
            return false;
        }
        $contents = file_get_contents($absolute_path);
        try {
            // PHP binaries can have many forms, may begin with #/usr/bin/env php.
            // We assume that if it's parseable and contains at least one PHP executable line, it's valid.
            $ast = Parser::parseCode(new CodeBase([], [], [], [], []), new Context(), $relative_path, $contents, true);
            if (!($ast instanceof Node)) {
                return false;
            }
            $child_nodes = $ast->children;
            if (count($child_nodes) !== 1) {
                return true;
            }
            $node = $child_nodes[0];
            return $node->kind !== \ast\AST_ECHO || !is_string($node->children['expr']);
        } catch (\ParseError $e) {
            return false;
        } catch (\Phan\AST\TolerantASTConverter\ParseException $e) {
            return false;
        }
    }
}
