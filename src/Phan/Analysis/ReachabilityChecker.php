<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use Phan\AST\Visitor\KindVisitorImplementation;

/**
 * This checks if $inner is unconditionally reachable from the passed in node.
 *
 * This returns false if the node is not even a descendent node.
 *
 * @see BlockExitStatusChecker
 * @internal - This is specialized for AST_ARG_LIST right now.
 */
final class ReachabilityChecker extends KindVisitorImplementation
{
    /** @var Node the node we're checking for reachability. */
    private $inner;

    public function __construct(Node $inner)
    {
        $this->inner = $inner;
    }

    /**
     * @return ?bool
     */
    public function visitArgList(Node $node)
    {
        if ($node === $this->inner) {
            return true;
        }
        return $this->visit($node);
    }

    /**
     * If we don't know how to analyze a node type (or left it out), assume it always proceeds
     * @return ?bool - The status bitmask corresponding to always proceeding
     */
    public function visit(Node $node)
    {
        foreach ($node->children as $child) {
            if (!($child instanceof Node)) {
                continue;
            }
            $result = $this->__invoke($child);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * @return ?bool this gives up on analyzing catch lists
     */
    public function visitCatchList(Node $_)
    {
        return null;
    }

    /**
     * @return ?bool this gives up on analyzing switches, except for the condition
     */
    public function visitSwitch(Node $node)
    {
        $cond = $node->children['cond'];
        if ($cond instanceof Node) {
            return $this->__invoke($cond);
        }
        return null;
    }

    /**
     * @return ?bool this gives up on analyzing for loops, except for the initializer and condition
     */
    public function visitFor(Node $node)
    {
        $init = $node->children['init'];
        if ($init instanceof Node) {
            $result = $this->__invoke($init);
            if ($result !== null) {
                return $result;
            }
        }
        $cond = $node->children['cond'];
        if ($cond instanceof Node) {
            return $this->__invoke($cond);
        }
        return null;
    }

    /**
     * @return ?bool this gives up on analyzing loops, except for the condition
     */
    public function visitWhile(Node $node)
    {
        $cond = $node->children['cond'];
        if ($cond instanceof Node) {
            return $this->__invoke($cond);
        }
        return null;
    }

    /**
     * @return ?bool this gives up on analyzing loops, except for the condition
     */
    public function visitForeach(Node $node)
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            return $this->__invoke($expr);
        }
        return null;
    }

    /**
     * @return ?bool
     */
    public function visitBreak(Node $_)
    {
        return false;
    }

    /**
     * @return ?bool
     */
    public function visitContinue(Node $_)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function visitReturn(Node $node)
    {
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return false;
        }
        return $this->__invoke($expr) ?? false;
    }

    /**
     * @return ?bool
     * @override
     */
    public function visitClosure(Node $_)
    {
        return null;
    }

    /**
     * @return ?bool
     * @override
     */
    public function visitFuncDecl(Node $_)
    {
        return null;
    }

    /**
     * @return ?bool
     * @override
     */
    public function visitClass(Node $node)
    {
        $args = $node->children['args'] ?? null;
        if (!$args instanceof Node) {
            return null;
        }
        return $this->__invoke($args);
    }

    /**
     * @return bool
     */
    public function visitThrow(Node $node)
    {
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return false;
        }
        return $this->__invoke($expr) ?? false;
    }

    /**
     * @return bool
     */
    public function visitExit(Node $node)
    {
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return false;
        }
        return $this->__invoke($expr) ?? false;
    }

    /**
     * @return ?bool the first result seen for any statement, or null.
     */
    public function visitStmtList(Node $node)
    {
        foreach ($node->children as $child) {
            if (!($child instanceof Node)) {
                continue;
            }
            $result = $this->__invoke($child);
            if ($result !== null) {
                return $result;
            }
            $status = (new BlockExitStatusChecker())->__invoke($child);
            if ($status !== BlockExitStatusChecker::STATUS_PROCEED) {
                if ($status & BlockExitStatusChecker::STATUS_THROW_OR_RETURN_BITMASK) {
                    return false;
                }
                continue;
            }
        }
        return null;
    }

    /**
     * Analyzes a node with kind \ast\AST_IF
     * @return ?bool the result seen for an if statement (if $node contains $this->inner or causes this to give up), or null
     * @override
     */
    public function visitIf(Node $node)
    {
        foreach ($node->children as $i => $child) {
            // TODO could check first if element (not important)
            // @phan-suppress-next-line PhanPartialTypeMismatchArgument
            $result = $this->visitIfElem($child);
            if ($result !== null) {
                return $result && $i === 0;
            }
        }
        return null;
    }

    /**
     * Analyzes a node with kind \ast\AST_IF_ELEM
     * @return ?bool the result seen for an if statement element (if $node contains $this->inner or causes this to give up), or null
     */
    public function visitIfElem(Node $node)
    {
        $cond = $node->children['cond'] ?? null;
        if ($cond instanceof Node) {
            $result = $this->__invoke($cond);
            if ($result !== null) {
                return $result;
            }
        }
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
        $result = $this->__invoke($node->children['stmts']);
        if ($result !== null) {
            // This is a conditional; it's not guaranteed to work
            return false;
        }
        return null;
    }

    /**
     * Analyzes a node with kind \ast\AST_CONDITIONAL
     * @return ?bool the result seen for a conditional
     */
    public function visitConditional(Node $node)
    {
        $cond = $node->children['cond'];
        if ($cond instanceof Node) {
            $result = $this->__invoke($cond);
            if ($result !== null) {
                return $result;
            }
        }
        foreach (['true', 'false'] as $sub_node_name) {
            $value = $node->children[$sub_node_name];
            if (!($value instanceof Node)) {
                continue;
            }
            $result = $this->__invoke($value);
            if ($result !== null) {
                // This is a conditional; it's not guaranteed to work
                return false;
            }
        }
        return null;
    }

    /**
     * Returns true if there are no break/return/throw/etc statements
     * within the method that would prevent $inner (a descendant node of $node)
     * to be reached from the start of evaluating the statements in $node.
     *
     * This does not attempt to check if any statements in $node might indirectly throw.
     */
    public static function willUnconditionallyBeReached(Node $node, Node $inner) : bool
    {
        return (new self($inner))->__invoke($node) ?? false;
    }
}
