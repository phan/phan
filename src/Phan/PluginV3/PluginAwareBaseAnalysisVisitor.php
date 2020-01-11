<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use ast\Node;
use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use Phan\Issue;
use Phan\Language\Element\TypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * This augments AnalysisVisitor with public and internal methods.
 */
abstract class PluginAwareBaseAnalysisVisitor extends AnalysisVisitor
{
    use IssueEmitter;  // defines emitPluginIssue

    /**
     * This is an empty visit() body.
     * Don't override this unless you need to, analysis is more efficient if Phan knows it doesn't need to call a plugin on a node type.
     * @see self::isDefinedInSubclass()
     * @param Node $node @phan-unused-param (unused because the body is empty)
     */
    public function visit(Node $node): void
    {
    }

    /**
     * Emit an issue with the provided arguments,
     * unless that issue is suppressed.
     *
     * @param string $issue_type
     * A name for the type of issue such as 'PhanPluginMyIssue'
     *
     * @param string $issue_message_fmt
     * The complete issue message format string to emit such as
     * 'class with fqsen {CLASS} is broken in some fashion' (preferred)
     * or 'class with fqsen %s is broken in some fashion'
     * The list of placeholders for between braces can be found
     * in \Phan\Issue::UNCOLORED_FORMAT_STRING_FOR_TEMPLATE.
     *
     * @param list<string|Type|UnionType|FQSEN|TypedElement> $issue_message_args
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
     * @suppress PhanUnreferencedPublicMethod (this plugin type is deprecated)
     */
    public function emit(
        string $issue_type,
        string $issue_message_fmt,
        array $issue_message_args = [],
        int $severity = Issue::SEVERITY_NORMAL,
        int $remediation_difficulty = Issue::REMEDIATION_B,
        int $issue_type_id = Issue::TYPE_ID_UNKNOWN
    ): void {
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

    // Internal methods used by ConfigPluginSet are below.
    // They aren't useful for plugins.

    /**
     * @return list<int> The list of $node->kind values this plugin is capable of analyzing.
     */
    final public static function getHandledNodeKinds(): array
    {
        $defines_visit = self::isDefinedInSubclass('visit');
        $kinds = [];
        foreach (Element::VISIT_LOOKUP_TABLE as $kind => $method_name) {
            if ($defines_visit || self::isDefinedInSubclass($method_name)) {
                $kinds[] = $kind;
            }
        }
        return $kinds;
    }

    /**
     * @return bool true if $method_name is defined by the subclass of PluginAwareBaseAnalysisVisitor,
     * and not by PluginAwareBaseAnalysisVisitor or one of its parents.
     */
    private static function isDefinedInSubclass(string $method_name): bool
    {
        $method = new \ReflectionMethod(static::class, $method_name);
        return \is_subclass_of($method->class, self::class);
    }
    // End of methods for internal use.
}
