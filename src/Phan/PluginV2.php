<?php declare(strict_types=1);
namespace Phan;

use Phan\PluginV2\IssueEmitter;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extend this class
 * (And at least one of the interfaces corresponding to plugin capabilities)
 * and return an instance of themselves.
 *
 * @link https://github.com/phan/phan/wiki/Writing-Plugins-for-Phan has addititional resources for users writing a plugin.
 *
 * List of capabilities which a plugin may implement:
 *
 *  1. public function analyzeClass(CodeBase $code_base, Clazz $class)
 *     Analyze (and modify) a class definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV2\AnalyzeClassCapability)
 *
 *  2. public function analyzeFunction(CodeBase $code_base, Func $function)
 *     Analyze (and modify) a function definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV2\AnalyzeFunctionCapability)
 *
 *  3. public function analyzeMethod(CodeBase $code_base, Method $method)
 *     Analyze (and modify) a method definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV2\AnalyzeMethodCapability)
 *
 *  4. public static function getPostAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwarePostAnalysisVisitor, which will be used to analyze nodes in the analysis phase.
 *     If the PluginAwarePostAnalysisVisitor subclass has an instance property called parent_node_list,
 *     Phan will automatically set that property to the list of parent nodes (The nodes deepest in the AST are at the end of the list)
 *     (implement \Phan\PluginV2\PostAnalyzeNodeCapability)
 *
 *  5. public static function getPreAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwarePreAnalysisVisitor, which will be used to pre-analyze nodes in the analysis phase.
 *     (implement \Phan\PluginV2\PreAnalyzeNodeCapability)
 *
 *  6. public function analyzeProperty(CodeBase $code_base, Property $property)
 *     Analyze (and modify) a property definition, after parsing and before analyzing.
 *     (implement \Phan\PluginV2\AnalyzePropertyCapability)
 *
 *  7. public function finalize(CodeBase $code_base)
 *     Called after the analysis phase is complete.
 *     (implement \Phan\PluginV2\FinalizeProcessCapability)
 *
 *  8. public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array<string, Closure(CodeBase,Context,Func|Method,array):void>
 *     Maps FQSEN of function or method to a closure used to analyze the function in question.
 *     'MyClass::myMethod' can be used as the FQSEN of a static or instance method.
 *     See .phan/plugins/PregRegexCheckerPlugin.php as an example.
 *
 *      Closure Type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args) : void {...}
 *
 *      (implement \Phan\PluginV2\AnalyzeFunctionCallCapability)
 *  9. public function getReturnTypeOverrides(CodeBase $code_base) : array<string,Closure(CodeBase,Context,Func|Method,array):UnionType>
 *     Maps FQSEN of function or method to a closure used to override the returned UnionType.
 *     See \Phan\Plugin\Internal\ArrayReturnTypeOverridePlugin as an example (That is automatically loaded by phan)
 *
 *     Closure type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args) : UnionType {...}
 *      (implement \Phan\PluginV2\ReturnTypeOverrideCapability)
 * 10. public function shouldSuppress(CodeBase $code_base, IssueInstance $instance, string $file_contents) : bool
 *
 *     Called in every phase when Phan is emitting an issue(parse, method, analysis, etc)
 *
 *     public function getIssueSuppressionList(CodeBase $code_base, string $file_path) : array<string,array<int,int>>
 *
 *     Called by UnusedSuppressionPlugin to check if the plugin's suppressions are no longer needed.
 *
 *     (implement \Phan\PluginV2\SuppressionCapability)
 *
 * List of deprecated legacy capabilities
 *
 *  1. public static function analyzeNode(CodeBase $code_base, Context $context, Node $node, Node $parent_node = null)
 *     Analyzes $node
 *     (implement \Phan\PluginV2\LegacyAnalyzeNodeCapability)
 *     (Deprecated in favor of postAnalyzeNode and \Phan\PluginV2\AnalyzeNodeCapability, which are faster)
 *
 *  2. public static function preAnalyzeNode(CodeBase $code_base, Context $context, Node $node)
 *     Pre-analyzes $node
 *     (implement \Phan\PluginV2\LegacyPreAnalyzeNodeCapability)
 *     (Deprecated in favor of \Phan\PluginV2\PreAnalyzeNodeCapability, which is faster)
 *
 *  3. public static function postAnalyzeNode(CodeBase $code_base, Context $context, Node $node, array<int,Node> $parent_node_list = [])
 *     Analyzes $node
 *     (implement \Phan\PluginV2\LegacyPostAnalyzeNodeCapability)
 *     (Deprecated in favor of \Phan\PluginV2\PostAnalyzeNodeCapability, which is much faster)
 *
 *  4. public static function getAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwareAnalysisVisitor, which will be used to analyze nodes in the analysis phase.
 *     Phan will automatically add the instance property parent_node to instances of that PluginAwareAnalysisVisitor,
 *     even if no such instance property was declared.
 *     (implement \Phan\PluginV2\AnalyzeNodeCapability)
 *     (Deprecated in favor of \Phan\PluginV2\PostAnalyzeNodeCapability, which is much faster)
 *
 * TODO: Implement a way to notify plugins that a parsed file is no longer valid,
 * if the replacement for pcntl is being used.
 * (Most of the plugins bundled with Phan don't need this)
 */
abstract class PluginV2
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
