<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\AST\Visitor\Element;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use ast\Node;

/**
 * For plugins which define their own pre-order analysis behaviors in the analysis phase.
 * Called on a node before PluginAwareAnalysisVisitor implementations.
 *
 * Public APIs for use by plugins:
 *
 * - visit<VisitSuffix>(...) (Override these methods)
 * - emitPluginIssue(CodeBase $code_base, Config $config, ...) (Call these methods)
 * - emit(...)
 * - Public methods from Phan\AST\AnalysisVisitor
 *
 * NOTE: Subclasses should not implement the visit() method unless they absolutely need to.
 * (E.g. if the body would be empty, or if it could be replaced with a small number of more specific methods such as visitFuncDecl, visitVar, etc.)
 *
 * - Phan is able to figure out which methods a subclass implements, and only call the plugin's visitor for those types,
 *   but only when the plugin's visitor does not override the fallback visit() method.
 */
abstract class PluginAwarePreAnalysisVisitor extends PluginAwareBaseAnalysisVisitor {
    // For backwards compatibility reasons, parent_node isn't available in PreAnalysis visitors
}
