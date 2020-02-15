<?php

declare(strict_types=1);

namespace Phan;

use Phan\PluginV3\IssueEmitter;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extend this class
 * (And at least one of the interfaces corresponding to plugin capabilities)
 * and return an instance of themselves.
 *
 * @link https://github.com/phan/phan/wiki/Writing-Plugins-for-Phan has additional resources for users writing a plugin.
 *
 * List of capabilities which a plugin may implement:
 *
 *  1. public function analyzeClass(CodeBase $code_base, Clazz $class)
 *     Analyze (and modify) a class definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV3\AnalyzeClassCapability)
 *
 *  2. public function analyzeFunction(CodeBase $code_base, Func $function)
 *     Analyze (and modify) a function definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV3\AnalyzeFunctionCapability)
 *
 *  3. public function analyzeMethod(CodeBase $code_base, Method $method)
 *     Analyze (and modify) a method definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV3\AnalyzeMethodCapability)
 *
 *  4. public static function getPostAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwarePostAnalysisVisitor, which will be used to analyze nodes in the analysis phase.
 *     If the PluginAwarePostAnalysisVisitor subclass has an instance property called parent_node_list,
 *     Phan will automatically set that property to the list of parent nodes (The nodes deepest in the AST are at the end of the list)
 *     (implement \Phan\PluginV3\PostAnalyzeNodeCapability)
 *
 *  5. public static function getPreAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwarePreAnalysisVisitor, which will be used to pre-analyze nodes in the analysis phase.
 *     (implement \Phan\PluginV3\PreAnalyzeNodeCapability)
 *
 *  6. public function analyzeProperty(CodeBase $code_base, Property $property)
 *     Analyze (and modify) a property definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV3\AnalyzePropertyCapability)
 *
 *  7. public function finalize(CodeBase $code_base)
 *     Called after the analysis phase is complete.
 *     (implement \Phan\PluginV3\FinalizeProcessCapability)
 *
 *  8. public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array<string, Closure(CodeBase,Context,Func|Method,array,?Node):void>
 *     Maps FQSEN of function or method to a closure used to analyze the function in question.
 *     'MyClass::myMethod' can be used as the FQSEN of a static or instance method.
 *     See .phan/plugins/PregRegexCheckerPlugin.php as an example.
 *
 *      Closure Type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args) : void {...}
 *
 *      (implement \Phan\PluginV3\AnalyzeFunctionCallCapability)
 *  9. public function getReturnTypeOverrides(CodeBase $code_base) : array<string,Closure(CodeBase,Context,Func|Method,array):UnionType>
 *     Maps FQSEN of function or method to a closure used to override the returned UnionType.
 *     See \Phan\Plugin\Internal\ArrayReturnTypeOverridePlugin as an example (That is automatically loaded by phan)
 *
 *     Closure type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args) : UnionType {...}
 *      (implement \Phan\PluginV3\ReturnTypeOverrideCapability)
 * 10. public function shouldSuppress(CodeBase $code_base, IssueInstance $instance, string $file_contents) : bool
 *
 *     Called in every phase when Phan is emitting an issue(parse, method, analysis, etc)
 *
 *     public function getIssueSuppressionList(CodeBase $code_base, string $file_path) : array<string,associative-array<int,int>>
 *
 *     Called by UnusedSuppressionPlugin to check if the plugin's suppressions are no longer needed.
 *
 *     (implement \Phan\PluginV3\SuppressionCapability)
 * 11. public function beforeAnalyze(CodeBase $code_base) : void
 *
 *     Called before analyzing a project (e.g. to run checks before analysis)
 *     beforeAnalyze is invoked immediately before analyzing methods and before forking analysis workers and before starting the analysis phase.
 *
 *     (implement \Phan\PluginV3\BeforeAnalyzeCapability)
 *
 *     Most plugins should use BeforeAnalyzePhaseCapability instead.
 * 12. public function beforeAnalyzeFile(CodeBase $code_base, Context $context, string $file_contents, Node $node);
 *
 *     Called before analyzing a file (with the absolute path Config::projectPath($context->getFile())).
 *     NOTE: This does not run on empty files.
 *
 *     (implement \Phan\PluginV3\BeforeAnalyzeFileCapability)
 * 13. public function afterAnalyzeFile(CodeBase $code_base, Context $context, string $file_contents, Node $node);
 *
 *     This method is called after Phan analyzes a file.
 *
 *     (implement \Phan\PluginV3\AfterAnalyzeFileCapability)
 * 14. public function handleLazyLoadInternalFunction(CodeBase $code_base, Func $function)
 *
 *     This method is called after Phan lazily loads a global internal function.
 *     This is useful to handle functions getAnalyzeFunctionCallClosures did not pick up
 *
 *     (implement \Phan\PluginV3\HandleLazyLoadInternalFunctionCapability)
 * 15. getAutomaticFixers() : array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
 *
 *     This method is called to fetch the issue names the plugin can sometimes automatically fix.
 *     Returns a map from issue name to the closure to generate a fix for instances of that issue.
 *
 *     (implement \Phan\PluginV3\AutomaticFixCapability)
 * 16. public function beforeAnalyzePhase(CodeBase $code_base) : void
 *
 *     Called before analyzing a project (e.g. to run checks before analysis)
 *     beforeAnalyze is invoked immediately after analyzing methods and before forking analysis workers and before starting the analysis phase.
 *
 *     (implement \Phan\PluginV3\BeforeAnalyzePhaseCapability)
 * 17. public function onEmitIssue(IssueInstance $issue_instance): bool
 *
 *     This method is called before Phan emits an (unsuppressed) issue.
 *     Returns true if the issue should be suppressed.
 *     Most plugins should use SuppressionCapability instead,
 *     so that more generic issues can be used to suppress specific issues,
 *     and to avoid interfering with baselines.
 *
 *     (implement \Phan\PluginV3\SubscribeEmitIssueCapability)
 */
abstract class PluginV3
{
    use IssueEmitter {
        emitPluginIssue as emitIssue;
    }

    /**
     * The above declares this function signature:
     *
     * public function emitIssue(
     *     CodeBase $code_base,
     *     Context $context,
     *     string $issue_type,
     *     string $issue_message_fmt,
     *     array $issue_message_args = [],
     *     int $severity = Issue::SEVERITY_NORMAL,
     *     int $remediation_difficulty = Issue::REMEDIATION_B,
     *     int $issue_type_id = Issue::TYPE_ID_UNKNOWN
     * )
     */
}
