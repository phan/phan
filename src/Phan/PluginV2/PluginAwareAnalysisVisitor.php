<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\AST\Visitor\Element;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Issue;
use ast\Node;

/**
 * For plugins which define their own post-order analysis behaviors in the analysis phase.
 * Called on a node after PluginAwarePreAnalysisVisitor implementations.
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
abstract class PluginAwareAnalysisVisitor extends PluginAwareBaseAnalysisVisitor {
    /** @var Node|null - Set after the constructor is called */
    protected $parent_node;

    // Implementations should omit the constructor or call parent::__construct(CodeBase $code_base, Context $context)

    /**
     * @param string $issue_type
     * A name for the type of issue such as 'PhanPluginMyIssue'
     *
     * @param string $issue_message_fmt
     * The complete issue message format string to emit such as
     * 'class with fqsen {CLASS} is broken in some fashion' (preferred)
     * or 'class with fqsen %s is broken in some fashion'
     * The list of placeholders for between braces can be found
     * in \Phan\Issue::uncolored_format_string_for_template.
     *
     * @param string[] $issue_message_args
     * The arguments for this issue format.
     * If this array is empty, $issue_message_args is kept in place
     *
     * @param int $severity
     * A value from the set {Issue::SEVERITY_LOW,
     * Issue::SEVERITY_NORMAL, Issue::SEVERITY_HIGH}.
     *
     * @param int $remediation_difficulty
     * A guess at how hard the issue will be to fix from the
     * set {Issue:REMEDIATION_A, Issue:REMEDIATION_B, ...
     * Issue::REMEDIATION_F} with F being the hardest.
     */
    public function emit(
        string $issue_type,
        string $issue_message_fmt,
        array $issue_message_args = [],
        int $severity = Issue::SEVERITY_NORMAL,
        int $remediation_difficulty = Issue::REMEDIATION_B,
        int $issue_type_id = Issue::TYPE_ID_UNKNOWN
    ) {
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            $issue_type,
            $issue_message_fmt,
            $issue_message_args,
            $severity,
            $remediation_difficulty,
            $issue_type_id
        );
    }
}
