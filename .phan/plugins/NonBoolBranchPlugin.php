<?php declare(strict_types=1);

// .phan/plugins/NonBoolBranchPlugin.php

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Exception\IssueException;
use Phan\Language\Context;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;
use Phan\PluginV3\PreAnalyzeNodeCapability;

/**
 * This plugin warns if an expression which has types other than `bool` is used in an if/else if.
 *
 * Note that the 'simplify_ast' setting's default of true will interfere with this plugin.
 */
class NonBoolBranchPlugin extends PluginV3 implements PreAnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwarePreAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPreAnalyzeNodeVisitorClassName() : string
    {
        return NonBoolBranchVisitor::class;
    }
}

/**
 * This visitor checks if statements for conditions ('cond') that are non-booleans.
 */
class NonBoolBranchVisitor extends PluginAwarePreAnalysisVisitor
{
    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @override
     */
    public function visitIf(Node $node) : Context
    {
        // Here, we visit the group of if/elseif/else instead of the individuals (visitIfElem)
        // so that we have the Union types of the variables **before** the PreOrderAnalysisVisitor makes inferences
        foreach ($node->children as $if_node) {
            if (!$if_node instanceof Node) {
                throw new AssertionError("Expected if statement to be a node");
            }
            $condition = $if_node->children['cond'];

            // dig nodes to avoid the NOT('!') operation converting its value to a boolean type.
            // Also, use right-hand side of assignments such as `$x = (expr)`
            while (($condition instanceof Node) && (
                ($condition->flags === ast\flags\UNARY_BOOL_NOT && $condition->kind === ast\AST_UNARY_OP)
                || (\in_array($condition->kind, [\ast\AST_ASSIGN, \ast\AST_ASSIGN_REF], true)))
            ) {
                $condition = $condition->children['expr'];
            }

            if ($condition === null) {
                // $condition === null will be appeared in else-clause, then avoid them
                continue;
            }

            if ($condition instanceof Node) {
                $this->context = $this->context->withLineNumberStart($condition->lineno);
            }
            // evaluate the type of conditional expression
            try {
                $union_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $condition);
            } catch (IssueException $_) {
                return $this->context;
            }
            if (!$union_type->isEmpty() && !$union_type->isExclusivelyBoolTypes()) {
                $this->emit(
                    'PhanPluginNonBoolBranch',
                    'Non bool value of type {TYPE} evaluated in if clause',
                    [(string)$union_type]
                );
            }
        }
        return $this->context;
    }
}

return new NonBoolBranchPlugin();
