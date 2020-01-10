<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Plugin\ConfigPluginSet;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeClassCapability;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AnalyzePropertyCapability;
use Phan\PluginV3\BeforeAnalyzeFileCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\SuppressionCapability;

/**
 * Check for unused (at)suppress annotations.
 *
 * NOTE! This plugin only produces correct results when Phan
 *       is run on a single processor (via the `-j1` flag).
 */
class UnusedSuppressionPlugin extends PluginV3 implements
    BeforeAnalyzeFileCapability,
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AnalyzePropertyCapability,
    FinalizeProcessCapability
{

    /**
     * @var AddressableElement[] - Analysis is postponed until finalizeProcess.
     * Issues may have been emitted after `$this->analyze*()` were called,
     * which is why those methods postpone the check until analysis is finished.
     *
     * Also, looping over all elements again would be slow.
     *
     * These are currently unique, even when quick_mode is false.
     */
    private $elements_for_postponed_analysis = [];

    /**
     * @var string[] a list of files where checks for unused suppressions was postponed
     * (Because of non-quick mode, we may emit issues in a file after analysis has run on that file)
     */
    private $files_for_postponed_analysis = [];

    /**
     * @var array<string,array<string,array<string,array<int,int>>>> stores the suppressions for active plugins
     *   maps plugin class to
     *     file name to
     *       issue type to
     *         unique list of line numbers of suppressions
     */
    private $plugin_active_suppression_list;

    /**
     * @param CodeBase $code_base
     * The code base in which the element exists
     *
     * @param AddressableElement $element
     * Any element such as function, method, class
     * (which has an FQSEN)
     */
    private static function analyzeAddressableElement(
        CodeBase $code_base,
        AddressableElement $element
    ): void {
        // Get the set of suppressed issues on the element
        $suppress_issue_list =
            $element->getSuppressIssueList();

        if (\array_key_exists('UnusedSuppression', $suppress_issue_list)) {
            // The element's doc comment is suppressing everything emitted by this plugin.
            return;
        }

        // Check to see if any are unused
        foreach ($suppress_issue_list as $issue_type => $use_count) {
            if (0 !== $use_count) {
                continue;
            }
            if (in_array($issue_type, self::getUnusedSuppressionIgnoreList(), true)) {
                continue;
            }
            self::emitIssue(
                $code_base,
                $element->getContext(),
                'UnusedSuppression',
                "Element {FUNCTIONLIKE} suppresses issue {ISSUETYPE} but does not use it",
                [(string)$element->getFQSEN(), $issue_type]
            );
        }
    }

    private function postponeAnalysisOfElement(AddressableElement $element): void
    {
        if (count($element->getSuppressIssueList()) === 0) {
            // There are no suppressions, so there's no reason to check this
            return;
        }
        $this->elements_for_postponed_analysis[] = $element;
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     * @override
     */
    public function analyzeClass(
        CodeBase $unused_code_base,
        Clazz $class
    ): void {
        $this->postponeAnalysisOfElement($class);
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     * @override
     */
    public function analyzeMethod(
        CodeBase $unused_code_base,
        Method $method
    ): void {

        // Ignore methods inherited by subclasses
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }

        $this->postponeAnalysisOfElement($method);
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     * @override
     */
    public function analyzeFunction(
        CodeBase $unused_code_base,
        Func $function
    ): void {
        $this->postponeAnalysisOfElement($function);
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     * @override
     */
    public function analyzeProperty(
        CodeBase $unused_code_base,
        Property $property
    ): void {
        $this->elements_for_postponed_analysis[] = $property;
    }

    /**
     * NOTE! This plugin only produces correct results when Phan
     *       is run on a single processor (via the `-j1` flag).
     *       Putting this hook in finalizeProcess() just minimizes the incorrect result counts.
     * @override
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        foreach ($this->elements_for_postponed_analysis as $element) {
            self::analyzeAddressableElement($code_base, $element);
        }
        $this->analyzePluginSuppressions($code_base);
    }

    private function analyzePluginSuppressions(CodeBase $code_base): void
    {
        $suppression_plugin_set = ConfigPluginSet::instance()->getSuppressionPluginSet();
        if (count($suppression_plugin_set) === 0) {
            return;
        }

        foreach ($this->files_for_postponed_analysis as $file_path) {
            foreach ($suppression_plugin_set as $plugin) {
                $this->analyzePluginSuppressionsForFile($code_base, $plugin, $file_path);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function getUnusedSuppressionIgnoreList(): array
    {
        return Config::getValue('plugin_config')['unused_suppression_ignore_list'] ?? [];
    }

    private static function getReportOnlyWhitelisted(): bool
    {
        return Config::getValue('plugin_config')['unused_suppression_whitelisted_only'] ?? false;
    }

    private static function shouldReportUnusedSuppression(string $issue_type): bool
    {
        $ignore_list = self::getUnusedSuppressionIgnoreList();
        $only_whitelisted = self::getReportOnlyWhitelisted();
        $issue_whitelist = Config::getValue('whitelist_issue_types') ?? [];

        return !in_array($issue_type, $ignore_list, true) &&
            (!$only_whitelisted || in_array($issue_type, $issue_whitelist, true));
    }

    private function analyzePluginSuppressionsForFile(CodeBase $code_base, SuppressionCapability $plugin, string $relative_file_path): void
    {
        $absolute_file_path = Config::projectPath($relative_file_path);
        $plugin_class = \get_class($plugin);
        $name_pos = \strrpos($plugin_class, '\\');
        if ($name_pos !== false) {
            $plugin_name = \substr($plugin_class, $name_pos + 1);
        } else {
            $plugin_name = $plugin_class;
        }
        $plugin_suppressions = $plugin->getIssueSuppressionList($code_base, $absolute_file_path);
        $plugin_successful_suppressions = $this->plugin_active_suppression_list[$plugin_class][$absolute_file_path] ?? null;

        foreach ($plugin_suppressions as $issue_type => $line_list) {
            foreach ($line_list as $lineno => $lineno_of_comment) {
                if (isset($plugin_successful_suppressions[$issue_type][$lineno])) {
                    continue;
                }
                // TODO: finish letting plugins suppress UnusedSuppression on other plugins
                $issue_kind = 'UnusedPluginSuppression';
                $message = 'Plugin {STRING_LITERAL} suppresses issue {ISSUETYPE} on this line but this suppression is unused or suppressed elsewhere';
                if ($lineno === 0) {
                    $issue_kind = 'UnusedPluginFileSuppression';
                    $message = 'Plugin {STRING_LITERAL} suppresses issue {ISSUETYPE} in this file but this suppression is unused or suppressed elsewhere';
                }
                if (isset($plugin_suppressions['UnusedSuppression'][$lineno_of_comment])) {
                    continue;
                }
                if (isset($plugin_suppressions[$issue_kind][$lineno_of_comment])) {
                    continue;
                }
                if (!self::shouldReportUnusedSuppression($issue_type)) {
                    continue;
                }
                self::emitIssue(
                    $code_base,
                    (new Context())->withFile($relative_file_path)->withLineNumberStart($lineno_of_comment),
                    $issue_kind,
                    $message,
                    [$plugin_name, $issue_type]
                );
            }
        }
        return;
    }

    public function beforeAnalyzeFile(
        CodeBase $unused_code_base,
        Context $context,
        string $unused_file_contents,
        Node $unused_node
    ): void {
        $file = $context->getFile();
        $this->files_for_postponed_analysis[$file] = $file;
    }

    /**
     * Record the fact that $plugin caused suppressions in $file_path for issue $issue_type due to an annotation around $line
     * @internal
     */
    public function recordPluginSuppression(
        SuppressionCapability $plugin,
        string $file_path,
        string $issue_type,
        int $line
    ): void {
        $file_name = Config::projectPath($file_path);
        $plugin_class = \get_class($plugin);
        $this->plugin_active_suppression_list[$plugin_class][$file_name][$issue_type][$line] = $line;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnusedSuppressionPlugin();
