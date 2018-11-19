#!/usr/bin/env php
<?php
declare(strict_types=1);
// @phan-file-suppress PhanNativePHPSyntaxCheckPlugin

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/WikiWriter.php';

use Phan\Config;
use Phan\Config\Initializer;

/**
 * Information that can be inferred about the config name from the source code and other data
 */
class ConfigEntry
{
    const CATEGORY_ANALYSIS = 'Analysis';
    const CATEGORY_ANALYSIS_VERSION = 'Analysis (of a PHP Version)';
    const CATEGORY_DEAD_CODE_DETECTION = 'Dead Code Detection';
    const CATEGORY_FILES = 'Configuring Files';
    const CATEGORY_HIDDEN_CLI_ONLY = 'Hidden';
    const CATEGORY_OUTPUT = 'Output';
    const CATEGORY_ISSUE_FILTERING = 'Issue Filtering';
    const CATEGORY_TYPE_CASTING = 'Type Casting';

    const ORDER_OF_CATEGORIES = [
        self::CATEGORY_FILES,
        self::CATEGORY_ISSUE_FILTERING,
        self::CATEGORY_ANALYSIS,
        self::CATEGORY_ANALYSIS_VERSION,
        self::CATEGORY_TYPE_CASTING,
        self::CATEGORY_DEAD_CODE_DETECTION,
        self::CATEGORY_OUTPUT,
        self::CATEGORY_HIDDEN_CLI_ONLY,
    ];

    /**
     * @var array<string,string>
     */
    const CATEGORIES = [
        'target_php_version' => self::CATEGORY_ANALYSIS_VERSION,
        'pretend_newer_core_methods_exist' => self::CATEGORY_ANALYSIS_VERSION,
        'polyfill_parse_all_element_doc_comments' => self::CATEGORY_ANALYSIS_VERSION,
        'file_list' => self::CATEGORY_FILES,
        'directory_list' => self::CATEGORY_FILES,
        'analyzed_file_extensions' => self::CATEGORY_FILES,
        'exclude_file_regex' => self::CATEGORY_FILES,
        'exclude_file_list' => self::CATEGORY_FILES,
        'enable_include_path_checks' => self::CATEGORY_ANALYSIS,
        'include_paths' => self::CATEGORY_ANALYSIS,
        'warn_about_relative_include_statement' => self::CATEGORY_ANALYSIS,
        'exclude_analysis_directory_list' => self::CATEGORY_FILES,
        'include_analysis_file_list' => self::CATEGORY_FILES,
        'backward_compatibility_checks' => self::CATEGORY_ANALYSIS,
        'parent_constructor_required' => self::CATEGORY_ANALYSIS,
        'quick_mode' => self::CATEGORY_ANALYSIS,
        'analyze_signature_compatibility' => self::CATEGORY_ANALYSIS,
        'allow_method_param_type_widening' => self::CATEGORY_ANALYSIS_VERSION,
        'guess_unknown_parameter_type_using_default' => self::CATEGORY_ANALYSIS,
        'inherit_phpdoc_types' => self::CATEGORY_ANALYSIS,
        'minimum_severity' => self::CATEGORY_ISSUE_FILTERING,
        'allow_missing_properties' => self::CATEGORY_ANALYSIS,
        'null_casts_as_array' => self::CATEGORY_TYPE_CASTING,
        'array_casts_as_null' => self::CATEGORY_TYPE_CASTING,
        'null_casts_as_any_type' => self::CATEGORY_TYPE_CASTING,
        'strict_method_checking' => self::CATEGORY_TYPE_CASTING,
        'strict_param_checking' => self::CATEGORY_TYPE_CASTING,
        'strict_return_checking' => self::CATEGORY_TYPE_CASTING,
        'scalar_implicit_cast' => self::CATEGORY_TYPE_CASTING,
        'scalar_array_key_cast' => self::CATEGORY_TYPE_CASTING,
        'scalar_implicit_partial' => self::CATEGORY_TYPE_CASTING,
        'ignore_undeclared_variables_in_global_scope' => self::CATEGORY_ANALYSIS,
        'check_docblock_signature_return_type_match' => self::CATEGORY_ANALYSIS,
        'check_docblock_signature_param_type_match' => self::CATEGORY_ANALYSIS,
        'prefer_narrowed_phpdoc_param_type' => self::CATEGORY_ANALYSIS,
        'prefer_narrowed_phpdoc_return_type' => self::CATEGORY_ANALYSIS,
        'dead_code_detection' => self::CATEGORY_DEAD_CODE_DETECTION,
        'unused_variable_detection' => self::CATEGORY_DEAD_CODE_DETECTION,
        'force_tracking_references' => self::CATEGORY_DEAD_CODE_DETECTION,
        'dead_code_detection_prefer_false_negative' => self::CATEGORY_DEAD_CODE_DETECTION,
        'warn_about_redundant_use_namespaced_class' => self::CATEGORY_DEAD_CODE_DETECTION,
        'simplify_ast' => self::CATEGORY_ANALYSIS,
        'enable_class_alias_support' => self::CATEGORY_ANALYSIS,
        'read_magic_property_annotations' => self::CATEGORY_ANALYSIS,
        'read_magic_method_annotations' => self::CATEGORY_ANALYSIS,
        'read_type_annotations' => self::CATEGORY_ANALYSIS,
        'warn_about_undocumented_throw_statements' => self::CATEGORY_ANALYSIS,
        'warn_about_undocumented_exceptions_thrown_by_invoked_functions' => self::CATEGORY_ANALYSIS,
        'exception_classes_with_optional_throws_phpdoc' => self::CATEGORY_ANALYSIS,
        'phpdoc_type_mapping' => self::CATEGORY_ANALYSIS,
        'disable_suppression' => self::CATEGORY_ISSUE_FILTERING,
        'disable_line_based_suppression' => self::CATEGORY_ISSUE_FILTERING,
        'disable_file_based_suppression' => self::CATEGORY_ISSUE_FILTERING,
        'dump_ast' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'dump_signatures_file' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'dump_parsed_file_list' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'progress_bar' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'progress_bar_sample_interval' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'processes' => self::CATEGORY_ANALYSIS,
        'profiler_enabled' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'suggestion_check_limit' => self::CATEGORY_OUTPUT,
        'disable_suggestions' => self::CATEGORY_OUTPUT,
        'suppress_issue_types' => self::CATEGORY_ISSUE_FILTERING,
        'whitelist_issue_types' => self::CATEGORY_ISSUE_FILTERING,
        'runkit_superglobals' => self::CATEGORY_ANALYSIS,
        'globals_type_map' => self::CATEGORY_ANALYSIS,
        'markdown_issue_messages' => self::CATEGORY_HIDDEN_CLI_ONLY, // self::CATEGORY_OUTPUT,
        'color_issue_messages' => self::CATEGORY_HIDDEN_CLI_ONLY, // self::CATEGORY_OUTPUT,
        'color_scheme' => self::CATEGORY_OUTPUT,
        'generic_types_enabled' => self::CATEGORY_ANALYSIS,
        'randomize_file_order' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'consistent_hashing_file_order' => self::CATEGORY_FILES,
        'print_memory_usage_summary' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'skip_slow_php_options_warning' => self::CATEGORY_OUTPUT,
        'skip_missing_tokenizer_warning' => self::CATEGORY_OUTPUT,
        'autoload_internal_extension_signatures' => self::CATEGORY_ANALYSIS,
        'ignore_undeclared_functions_with_known_signatures' => self::CATEGORY_ANALYSIS,
        'use_fallback_parser' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'use_polyfill_parser' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'daemonize_socket' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'daemonize_tcp' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'daemonize_tcp_host' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'daemonize_tcp_port' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_config' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_analyze_only_on_save' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_debug_level' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_use_pcntl_fallback' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_enable_go_to_definition' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_enable_hover' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_enable_completion' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'language_server_hide_category_of_issues' => self::CATEGORY_HIDDEN_CLI_ONLY,
        'enable_internal_return_type_plugins' => self::CATEGORY_ANALYSIS,
        'max_literal_string_type_length' => self::CATEGORY_ANALYSIS,
        'plugins' => self::CATEGORY_ANALYSIS,
        'plugin_config' => self::CATEGORY_ANALYSIS,
    ];

    /** @var string the configuration name (e.g. 'null_casts_as_any_type') */
    private $config_name;
    /** @var array<int,string> the raw comment lines */
    private $lines;
    /** @var string the category of configuration settings */
    private $category;

    /**
     * @param string $config_name the name of the config setting
     * @param array<int,string> $lines
     */
    public function __construct(string $config_name, array $lines)
    {
        $this->config_name = $config_name;
        $this->lines = $lines;
        $this->category = self::CATEGORIES[$config_name] ?? 'misc';
    }

    public function getConfigName() : string
    {
        return $this->config_name;
    }

    public function getMarkdown() : string
    {
        $result = '';
        foreach ($this->lines as $line) {
            $line = preg_replace('@^//( |$)@', '', trim($line));
            $result .= $line . "\n";
        }
        $result = preg_replace_callback(
            '@(?<!\[)`([A-Za-z_0-9]+)`@',
            /** @param array{0:string,1:string} $matches */
            function (array $matches) : string {
                list($markdown, $name) = $matches;
                if ($name !== $this->config_name && isset(Config::DEFAULT_CONFIGURATION[$name])) {
                    return sprintf('[%s](#%s)', $markdown, $name);
                }
                return $markdown;
            },
            $result
        );
        return $result;
    }

    /**
     * @return array<int,string> the raw lines
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getLines() : array
    {
        return $this->lines;
    }

    public function getCategory() : string
    {
        return $this->category;
    }

    public function getCategoryIndex() : int
    {
        $category_index = array_search($this->category, ConfigEntry::ORDER_OF_CATEGORIES);
        return is_int($category_index) ? $category_index : 99999;
    }

    /**
     * Is this config setting hidden from the generated markdown document?
     */
    public function isHidden() : bool
    {
        return $this->category === self::CATEGORY_HIDDEN_CLI_ONLY;
    }

    public function getRepresentationOfDefault() : string
    {
        if ($this->config_name === 'minimum_severity') {
            return '`Issue::SEVERITY_LOW`';
        }
        $value = Config::DEFAULT_CONFIGURATION[$this->config_name];
        $result = json_encode($value, JSON_UNESCAPED_SLASHES);

        return '`' . $result . '`';
    }
}

/**
 * Parts of this are based on https://github.com/phan/phan/issues/445#issue-195541058 by algo13
 */
class WikiConfigUpdater
{
    /**
     * @var bool whether to print debugging messages to stderr
     */
    private static $verbose = false;

    private static function printUsageAndExit(int $exit_code = 1)
    {
        global $argv;
        $program = $argv[0];
        $help = <<<EOT
Usage: $program [-v/--verbose]

EOT;
        fwrite(STDERR, $help);
        exit($exit_code);
    }

    /**
     * @return array<string,ConfigEntry>
     */
    private static function getSortedConfigMap() : array
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
        uasort($results, function (ConfigEntry $a, ConfigEntry $b) : int {
            return
                $a->getCategoryIndex() <=> $b->getCategoryIndex() ?:
                strcasecmp($a->getCategory(), $b->getCategory()) ?:
                strcasecmp($a->getConfigName(), $b->getConfigName());
        });
        return $results;
    }

    /**
     * @return array<string,string> maps section header to the contents for that section header
     * TODO: Deduplicate
     */
    private static function extractOldTextForSections(string $wiki_filename) : array
    {
        if (!file_exists($wiki_filename)) {
            fwrite(STDERR, "Failed to locate '$wiki_filename'\n");
            exit(1);
        }
        $contents = file_get_contents($wiki_filename);
        if (!is_string($contents)) {
            fwrite(STDERR, "Failed to read '$wiki_filename'\n");
            exit(1);
        }
        $wiki_lines = explode("\n", $contents);
        $text_for_section = [];
        $title = 'global';
        $text_for_section[$title] = '';
        // Skip heading titles
        foreach ($wiki_lines as $line) {
            if (strpos($line, '#') === 0) {
                // TODO: If before md has Severity, delete it.
                // TODO: $title = preg_replace('/\\\\\[.*\\\\\]\s*/', '', $line);
                $title = $line;
                $text_for_section[$title] = '';
            } else {
                $text_for_section[$title] .= $line . "\n";
            }
        }
        return $text_for_section;
    }

    /**
     * Updates the markdown document of issue types with minimal documentation of missing issue types.
     *
     * @return void
     * @throws InvalidArgumentException (uncaught) if the documented issue types can't be found.
     */
    public static function main()
    {
        global $argv;
        if (count($argv) !== 1) {
            if (count($argv) === 2 && in_array($argv[1], ['-v', '--verbose'])) {
                self::$verbose = true;
            } else {
                self::printUsageAndExit();
            }
        }
        error_reporting(E_ALL);

        $wiki_filename = __DIR__ . '/Phan-Config-Settings.md';

        $old_text_for_section = self::extractOldTextForSections($wiki_filename);

        $writer = new WikiWriter(true);
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
        self::debugLog(str_repeat('-', 80) . "\nSaving to '$wiki_filename_new'\n");
        file_put_contents($wiki_filename_new, $contents);
    }

    /**
     * Start documenting a brand new category of configs where the first ConfigEntry in that category is $config_entry
     * @param WikiWriter $writer
     * @param array<string,string> $old_text_for_section
     * @throws InvalidArgumentException
     */
    private static function documentConfigCategorySection(WikiWriter $writer, ConfigEntry $config_entry, array $old_text_for_section)
    {
        $category = $config_entry->getCategory();
        if (!$category) {
            throw new InvalidArgumentException("Failed to find category for {$config_entry->getConfigName()}");
        }
        $category_name = $category;
        if (!$category_name) {
            throw new InvalidArgumentException("Failed to find category name for category $category of {$config_entry->getConfigName()}");
        }

        $header = '# ' . $category_name;
        $writer->append($header . "\n");
        if (array_key_exists($header, $old_text_for_section)) {
            $writer->append($old_text_for_section[$header]);
        } else {
            $writer->append("\nTODO: Document issue category $category_name\n\n");
        }
    }

    private static function documentConfig(WikiWriter $writer, ConfigEntry $config_entry)
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

    private static function debugLog(string $message)
    {
        // Uncomment the below line to enable debugging
        if (self::$verbose) {
            fwrite(STDERR, $message);
        }
    }
}

WikiConfigUpdater::main();
