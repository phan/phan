<?php declare(strict_types=1);
namespace Phan;

use Phan\Language\Context;

/**
 * This trait offers a convenience method for emitting ad hoc issues.
 */
trait PluginIssue {
    /**
     * Emit an issue if it is not suppressed
     *
     * @param CodeBase $code_base
     * The code base in which the issue was found
     *
     * @param Context $context
     * The context in which the issue was found
     *
     * @param string $issue_type
     * A name for the type of issue such as 'PhanPluginMyIssue'
     *
     * @param string $issue_message
     * The complete issue message to emit such as 'class with
     * fqsen \NS\Name is broken in some fashion'.
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
    public function emitPluginIssue(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        string $issue_message,
        int $severity = Issue::SEVERITY_NORMAL,
        int $remediation_difficulty = Issue::REMEDIATION_B,
        int $issue_type_id = Issue::TYPE_ID_UNKNOWN
    ) {
        $issue = new Issue(
            $issue_type,
            Issue::CATEGORY_PLUGIN,
            $severity,
            $issue_message,
            $remediation_difficulty,
            $issue_type_id
        );

        $issue_instance = new IssueInstance(
            $issue,
            $context->getFile(),
            $context->getLineNumberStart(),
            []
        );

        Issue::maybeEmitInstance(
            $code_base,
            $context,
            $issue_instance
        );
    }
}
