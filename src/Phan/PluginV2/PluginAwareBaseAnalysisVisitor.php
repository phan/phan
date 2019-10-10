<?php declare(strict_types=1);

namespace Phan\PluginV2;

use ast\Node;
use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use Phan\Issue;
use Phan\PluginV3\IssueEmitter;

/**
 * This augments AnalysisVisitor with public and internal methods.
 * @deprecated use PluginV3
 */
abstract class PluginAwareBaseAnalysisVisitor extends AnalysisVisitor
{
    use IssueEmitter;  // defines emitPluginIssue

    /**
     * This is an empty visit() body.
     * Don't override this unless you need to, analysis is more efficient if Phan knows it doesn't need to call a plugin on a node type.
     * @see self::isDefinedInSubclass()
     * @param Node $node @phan-unused-param (unused because the body is empty)
     *
     * @return void
     */
    public function visit(Node $node)
    {
    }

    /**
     * See documentation for PluginV3
     * @param list<string> $issue_message_args
     * @return void
     * @suppress PhanPluginCanUsePHP71Void
     * @suppress PhanUnreferencedPublicMethod
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

    // Internal methods used by ConfigPluginSet are below.
    // They aren't useful for plugins.

    /**
     * @return list<int> The list of $node->kind values this plugin is capable of analyzing.
     */
    final public static function getHandledNodeKinds() : array
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
    private static function isDefinedInSubclass(string $method_name) : bool
    {
        $method = new \ReflectionMethod(static::class, $method_name);
        return \is_subclass_of($method->class, self::class);
    }
    // End of methods for internal use.
}
