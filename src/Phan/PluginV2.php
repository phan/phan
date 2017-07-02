<?php declare(strict_types=1);
namespace Phan;

use Phan\Language\Context;
use Phan\PluginV2\IssueEmitter;
use ast\Node;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extends this class
 * (And at least one of the interfaces corresponding to plugin capabilities)
 * and return an instance of themselves.
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
 *  4. public static function getAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwareAnalysisVisitor, which will be used to analyze nodes in the analysis phase.
 *     (implement \Phan\PluginV2\AnalyzeNodeCapability)
 *
 *  5. public static function getPreAnalyzeNodeVisitorClassName() : string
 *     Returns the name of a class extending PluginAwarePreAnalysisVisitor, which will be used to pre-analyze nodes in the analysis phase.
 *     (implement \Phan\PluginV2\PreAnalyzeNodeCapability)
 *
 * List of deprecated legacy capabilities
 *
 *  1. public static function analyzeNode(CodeBase $code_base, Context $context, Node $node, Node $parent_node = null)
 *     Analyzes $node
 *     (implement \Phan\PluginV2\LegacyAnalyzeNodeCapability)
 *     (Deprecated in favor of \Phan\PluginV2\AnalyzeNodeCapability, which is faster)
 *
 *  2. public static function preAnalyzeNode(CodeBase $code_base, Context $context, Node $node)
 *     Pre-analyzes $node
 *     (implement \Phan\PluginV2\LegacyPreAnalyzeNodeCapability)
 *     (Deprecated in favor of \Phan\PluginV2\PreAnalyzeNodeCapability, which is faster)
 */
abstract class PluginV2 {
    /**
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
    use IssueEmitter {
        emitPluginIssue as emitIssue;
    }
}
