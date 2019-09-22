<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\UnionType;

/**
 * Used to avoid false positives analyzing loop conditions for redundant conditions.
 */
class LoopConditionVisitor extends ConditionVisitor
{
    /** @var Node|string|int|false the node for the condition of the loop */
    protected $loop_condition_node;

    /** @var bool whether to allow conditions that are always false */
    protected $allow_false;

    /** @var bool whether the loop body unconditionally proceeds - If it does, then enable checks for infinite loops such as while (true){} */
    protected $loop_body_unconditionally_proceeds;

    /** @param Node|string|int|false $loop_condition_node the node for the condition of the loop */
    public function __construct(CodeBase $code_base, Context $context, $loop_condition_node, bool $allow_false, bool $loop_body_unconditionally_proceeds)
    {
        parent::__construct($code_base, $context);
        $this->loop_condition_node = $loop_condition_node;
        $this->allow_false = $allow_false;
        $this->loop_body_unconditionally_proceeds = $loop_body_unconditionally_proceeds;
    }

    public function checkRedundantOrImpossibleTruthyCondition($node, Context $context, ?UnionType $type, bool $is_negated) : void
    {
        if (!$this->loop_body_unconditionally_proceeds && $node === $this->loop_condition_node) {
            // Don't warn about `while (1)` or `while (true)`
            if ($node instanceof Node) {
                if ($node->kind === ast\AST_CONST) {
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                    $node_name = \strtolower($node->children['name']->children['name'] ?? '');
                    if ($node_name === 'true' || ($this->allow_false && $node_name === 'false')) {
                        return;
                    }
                }
            } elseif (\is_int($node)) {
                if ($node || $this->allow_false) {
                    return;
                }
            }
        }
        parent::checkRedundantOrImpossibleTruthyCondition($node, $context, $type, $is_negated);
    }

    /**
     * @override
     * @param Node|mixed $node
     */
    protected function chooseIssueForUnconditionallyTrue(bool $is_negated, $node) : string
    {
        if (!$is_negated && $node === $this->loop_condition_node) {
            return Issue::InfiniteLoop;
        }
        return parent::chooseIssueForUnconditionallyTrue($is_negated, $node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitExprList(Node $node) : Context
    {
        $children = $node->children;
        $count = \count($children);
        if ($count > 1) {
            foreach ($children as $sub_node) {
                --$count;
                if ($count > 0 && $sub_node instanceof Node) {
                    $this->checkVariablesDefined($sub_node);
                }
            }
        }
        // Only analyze the last expression in the expression list for conditions.
        $last_expression = \end($children);
        if ($node === $this->loop_condition_node) {
            // @phan-suppress-next-line PhanPartialTypeMismatchProperty
            $this->loop_condition_node = $last_expression;
        }
        if ($last_expression instanceof Node) {
            return $this->__invoke($last_expression);
        } elseif (Config::getValue('redundant_condition_detection')) {
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument, PhanTypeMismatchArgumentNullable
            $this->checkRedundantOrImpossibleTruthyCondition($last_expression, $this->context, null, false);
        }
        return $this->context;
    }
}
