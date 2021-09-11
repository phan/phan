<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Parse\ParseVisitor;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;
use Phan\PluginV3\PreAnalyzeNodeCapability;

/**
 * This plugin checks for assignments where the variable already
 * has the given value.
 *
 * - E.g. `$result = false; if (cond()) { $result = false; }`
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * DuplicateExpressionPlugin hooks into two events:
 *
 * - getPreAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed in pre-order
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class RedundantAssignmentPlugin extends PluginV3 implements
    PreAnalyzeNodeCapability
{
    /**
     * @return class-string - name of PluginAwarePreAnalysisVisitor subclass
     */
    public static function getPreAnalyzeNodeVisitorClassName(): string
    {
        return RedundantAssignmentPreAnalysisVisitor::class;
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in post-order.
 */
class RedundantAssignmentPreAnalysisVisitor extends PluginAwarePreAnalysisVisitor
{
    /**
     * @param Node $node
     * An assignment operation node to analyze
     * @override
     */
    public function visitAssign(Node $node): void
    {
        $var = $node->children['var'];
        if (!$var instanceof Node) {
            return;
        }
        if ($var->kind !== ast\AST_VAR) {
            return;
        }
        $var_name = $var->children['name'];
        if (!is_string($var_name)) {
            return;
        }
        $variable = $this->context->getScope()->getVariableByNameOrNull($var_name);
        if (!$variable || $variable instanceof PassByReferenceVariable) {
            return;
        }
        $variable_type = $variable->getUnionType();
        if ($variable_type->isPossiblyUndefined() || count($variable_type->getRealTypeSet()) !== 1) {
            return;
        }
        $old_value = $variable_type->getRealUnionType()->asValueOrNullOrSelf();
        if (is_object($old_value)) {
            return;
        }
        $expr = $node->children['expr'];
        if (!ParseVisitor::isConstExpr($expr, ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION)) {
            return;
        }
        try {
            $expr_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr, false);
        } catch (Exception $_) {
            return;
        }
        if (count($expr_type->getRealTypeSet()) !== 1) {
            return;
        }
        $expr_value = $expr_type->getRealUnionType()->asValueOrNullOrSelf();
        if ($expr_value !== $old_value) {
            return;
        }
        if ($this->context->hasSuppressIssue($this->code_base, 'PhanPluginRedundantAssignment')) {
            // Suppressing this suppresses the more specific issues.
            return;
        }
        if ($this->context->isInGlobalScope()) {
            if ($variable->getFileRef()->getFile() !== $this->context->getFile()) {
                // Don't warn if this variable was set by a different file
                return;
            }
            if (Config::getValue('__analyze_twice') && $variable->getFileRef()->getLineNumberStart() === $this->context->getLineNumberStart()) {
                // Don't warn if this variable was set by a different file
                return;
            }
            $issue_name = 'PhanPluginRedundantAssignmentInGlobalScope';
        } elseif ($this->context->isInLoop()) {
            $issue_name = 'PhanPluginRedundantAssignmentInLoop';
        } else {
            $issue_name = 'PhanPluginRedundantAssignment';
        }
        if ($this->context->isInLoop()) {
            $this->context->deferCheckToOutermostLoop(function (Context $context_after_loop) use ($issue_name, $var_name, $variable_type, $var): void {
                $new_variable = $context_after_loop->getScope()->getVariableByNameOrNull($var_name);
                if (!$new_variable) {
                    return;
                }
                $new_variable_type = $new_variable->getUnionType();
                if ($new_variable_type->isPossiblyUndefined()) {
                    return;
                }
                if ($new_variable_type->getRealTypeSet() !== $variable_type->getRealTypeSet()) {
                    return;
                }
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($var->lineno),
                    $issue_name,
                    'Assigning {TYPE} to variable ${VARIABLE} which already has that value',
                    [$variable_type, $var_name]
                );
            });
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            (clone $this->context)->withLineNumberStart($var->lineno),
            $issue_name,
            'Assigning {TYPE} to variable ${VARIABLE} which already has that value',
            [$expr_type, $var_name]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new RedundantAssignmentPlugin();
