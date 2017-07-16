<?php declare(strict_types=1);
namespace Phan\Analysis;

use ast\Node;
use Phan\AST\Visitor\KindVisitorImplementation;

/**
 * This simplifies a PHP AST into a form which is easier to analyze.
 * Precondition: The original \ast\Node objects are not modified.
 *
 * This caches the status for AST nodes, so references to this object
 * should be removed once the source transformation of a file/function is complete.
 *
 * This uses the \ast\Node's themselves in order to cache the status.
 * It reuses $node->flags whenever possible in order to avoid keeping around \ast\Node
 * instances for longer than those would be used.
 * This assumes that Nodes aren't manipulated, or manipulations to Nodes will preserve the semantics (including computed exit status) or clear $node->flags.
 *
 * - Creating an additional object property would increase overall memory usage, which is why properties are used.
 * - AST_IF, AST_IF_ELEM, AST_DO_WHILE, AST_FOR, AST_WHILE, AST_STMT_LIST,
 *   etc (e.g. switch and switch case, try/finally).
 *   are node types which are known to not have flags in AST version 40.
 * - In the future, add a new property such as $node->children['__exitStatus'] if used for a node type with flags, or use the higher bits.
 *
 * TODO: Change to AnalysisVisitor if this ever emits issues.
 * TODO: Analyze switch (if there is a default) in another PR (And handle fallthrough)
 * TODO: Refactor this class to be able to express return values such as "This will return or break, but it won't throw".
 */
final class BlockExitStatusChecker extends KindVisitorImplementation {
    // These should be at most 1 << 31, in order to work in 32-bit php.
    const STATUS_PROCEED        = (1 << 20);       // At least one branch continues to completion.
    const STATUS_MAYBE_CONTINUE = (1 << 21);       // We are certain at least one branch does not continue to completion. At least one of those is a "continue;"
    const STATUS_MAYBE_BREAK    = (1 << 22);       // We are certain at least one branch is a "break;", none are "continue;"
    const STATUS_MAYBE_THROW    = (1 << 23);       // At least one branch is a "throw", none are break/continue
    const STATUS_MAYBE_RETURN   = (1 << 24);       // At least one branch is a "return"/"exit", none are throw/break/continue

    const STATUS_CONTINUE       = (1 << 25);       // All branches lead to a continue statement (Or possibly a break, throw, or return)
    const STATUS_BREAK          = (1 << 26);       // All branches lead to a break statement (Or possibly a throw or return)
    const STATUS_THROW          = (1 << 27);       // All branches lead to a throw statement (Or possibly a return)
    const STATUS_RETURN         = (1 << 28);       // All branches lead to a return/exit statement

    const STATUS_THROW_OR_RETURN_BITMASK = self::STATUS_THROW | self::STATUS_RETURN;

    const STATUS_INTERESTING_SWITCH_BITMASK =
        self::STATUS_MAYBE_THROW |
        self::STATUS_MAYBE_RETURN |
        self::STATUS_THROW |
        self::STATUS_RETURN;

    const STATUS_INTERESTING_TRY_BITMASK =
        self::STATUS_MAYBE_CONTINUE |
        self::STATUS_MAYBE_BREAK |
        self::STATUS_CONTINUE |
        self::STATUS_BREAK;

    // Bitshift left by this to convert a possible status to a certain status;
    const BITSHIFT_FOR_MAYBE = 4;

    const STATUS_MAYBE_BITMASK =
        self::STATUS_MAYBE_CONTINUE |
        self::STATUS_MAYBE_BREAK |
        self::STATUS_MAYBE_THROW |
        self::STATUS_MAYBE_RETURN;

    const STATUS_CERTAIN_BITMASK =
        self::STATUS_CONTINUE |
        self::STATUS_BREAK |
        self::STATUS_THROW |
        self::STATUS_RETURN;

    const STATUS_BITMASK =
        self::STATUS_PROCEED |
        self::STATUS_MAYBE_BITMASK |
        self::STATUS_CERTAIN_BITMASK;

    public function __construct() { }

    public function check(Node $node = null) : int
    {
        if (!$node) {
            return self::STATUS_PROCEED;
        }
        $result = $this($node);
        \assert(\is_int($result), 'Expected int');
        return $result;
    }

    /**
     * If we don't know how to analyze a node type (or left it out), assume it always proceeds
     */
    public function visit(Node $node)
    {
        return self::STATUS_PROCEED;
    }

    private static function isTruthyLiteral($cond) : bool
    {
        if ($cond instanceof Node) {
            // TODO: Could look up values for remaining constants and inline expressions, but doing that has low value.
            if ($cond->kind === \ast\AST_CONST) {
                $condName = $cond->children['name'];
                if ($condName->kind === \ast\AST_NAME) {
                    return \strtolower($condName->children['name']) === 'true';
                }
            }
            return false;
        }
        // Cast string, int, etc. literal to a bool
        return (bool)$cond;
    }

    // A break statement unconditionally breaks out of a loop/switch
    public function visitBreak(Node $node)
    {
        return self::STATUS_BREAK;
    }

    // A continue statement unconditionally continues out of a loop.
    public function visitContinue(Node $node)
    {
        return self::STATUS_CONTINUE;
    }

    // A throw statement unconditionally throws
    public function visitThrow(Node $node)
    {
        return self::STATUS_THROW;
    }

    public function visitTry(Node $node)
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfTry($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfTry(Node $node) : int
    {
        $main_status = $this->check($node->children['try']);
        // Finding good heuristics is difficult.
        // e.g. "return someFunctionThatMayThrow()" in try{} block would be inferred as STATUS_RETURN, but may actually be STATUS_THROW
        $main_status = min($main_status, self::STATUS_THROW);

        $finally_node = $node->children['finally'];
        if ($finally_node) {
            $finally_status = $this->check($finally_node);
            if ($finally_status >= self::STATUS_THROW) {
                return $finally_status;
            }
        } else {
            $finally_status = self::STATUS_PROCEED;
        }
        $catch_node_list = $node->children['catches']->children;
        if (\count($catch_node_list) === 0) {
            return self::mergeFinallyStatus($main_status, $finally_status);
        }
        // TODO: Check if each catch statement unconditionally returns?
        if (($main_status & self::STATUS_INTERESTING_TRY_BITMASK) !== 0) {
            // Not 100% certain of any status. If anything threw, it could be caught by the 1 or more catch statements..
            if (($main_status & self::STATUS_CERTAIN_BITMASK) !== 0) {
                return $main_status >> self::BITSHIFT_FOR_MAYBE;
            }
            return $main_status;
        }
        // No idea.
        return self::STATUS_PROCEED;
    }

    private static function mergeFinallyStatus(int $try_status, int $finally_status) : int
    {
        if (($try_status & self::STATUS_CERTAIN_BITMASK) !== 0) {
            return \max($try_status, $finally_status);
        }
        if (($finally_status & self::STATUS_CERTAIN_BITMASK) !== 0) {
            return $finally_status;
        }
        return min($try_status, $finally_status);
    }

    public function visitSwitch(Node $node)
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfSwitch($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfSwitch(Node $node) : int
    {
        $has_default = false;
        $status = null;
        $normal_break_is_possible = false;
        $switch_stmt_case_nodes = $node->children['stmts']->children;
        foreach ($switch_stmt_case_nodes as $index => $case_node) {
            if ($case_node->children['cond'] === null) {
                $has_default = true;
            }
            $case_status = self::getStatusOfSwitchCase($case_node, $index, $switch_stmt_case_nodes);
            if ($case_status & self::STATUS_INTERESTING_SWITCH_BITMASK) {
                if (is_null($status) || $case_status < $status) {
                    $status = $case_status;
                }
            } else {
                // One of the case statements will break, or fall through to the end.
                $normal_break_is_possible = true;
            }
        }
        if ($status === null) {
            return self::STATUS_PROCEED;
        }
        if (($status & self::STATUS_INTERESTING_SWITCH_BITMASK) === 0) {
            return self::STATUS_PROCEED;
        }
        if ($normal_break_is_possible || !$has_default) {
            if (($status & self::STATUS_CERTAIN_BITMASK) !== 0) {
                // E.g. some of the case statements throw unconditionally, others break normally.
                // So, the final result is that an interesting outcome such as throw/return is possible but not certain.
                return $status >> self::BITSHIFT_FOR_MAYBE;
            } else {
                return $status;
            }
        }
        // Ignore statuses such as break/continue. They take effect inside, not outside.
        return $status;
    }

    /**
     * @param Node[] $siblings
     */
    private function getStatusOfSwitchCase(Node $case_node, int $index, array $siblings) : int
    {
        $status = $case_node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfSwitchCase($case_node, $index, $siblings);
        $case_node->flags = $status;
        return $status;
    }

    private function computeStatusOfSwitchCase(Node $case_node, int $index, array $siblings) : int
    {
        $status = $this->visitStmtList($case_node->children['stmts']);
        if ($status & self::STATUS_CERTAIN_BITMASK) {
            return $status;
        }
        $next_sibling = $siblings[$index + 1] ?? null;
        if (!$next_sibling) {
            return $status;
        }
        $next_status = self::getStatusOfSwitchCase($next_sibling, $index + 1, $siblings);
        if ($status & self::STATUS_MAYBE_BITMASK) {
            if ($next_status & self::STATUS_MAYBE_BITMASK) {
                return min($status, $next_status);
            } else if ($next_status & self::STATUS_CERTAIN_BITMASK) {
                return min($status << self::BITSHIFT_FOR_MAYBE, $next_status);
            }
            // next_status === STATUS_PROCEED
            return $status;
        }
        // STATUS_PROCEED | self::STATUS_CERTAIN_BITMASK
        return $next_status;
    }

    public function visitWhile(Node $node)
    {
        return $this->analyzeLoop($node);
    }

    public function visitFor(Node $node)
    {
        return $this->analyzeLoop($node);
    }

    private function analyzeLoop(Node $node) : int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfLoopWithTrueCond($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfLoopWithTrueCond(Node $node) : int
    {
        // only know how to analyze "while (1) {exprs}" or "for (; true; ) {exprs}"
        // TODO: identify infinite loops, mark those as STATUS_NO_PROCEED or STATUS_RETURN.
        if (!self::isTruthyLiteral($node->children['cond'])) {
            return self::STATUS_PROCEED;
        }
        $status = $this->check($node->children['stmts']);
        if ($status === self::STATUS_RETURN || $status === self::STATUS_THROW) {
            return $status;
        }
        return self::STATUS_PROCEED;
    }

    // A return statement unconditionally returns (Assume expression doesn't throw)
    public function visitReturn(Node $node)
    {
        return self::STATUS_RETURN;
    }

    // A exit statement unconditionally exits (Assume expression doesn't throw)
    public function visitExit(Node $node)
    {
        return self::STATUS_RETURN;
    }

    /**
     * A statement list has the weakest return status out of all of the (non-PROCEEDing) statements.
     * FIXME: This is buggy, doesn't account for one statement having STATUS_CONTINUE some of the time but not all of it.
     *       (We don't check for STATUS_CONTINUE yet, so this doesn't matter yet.)
     */
    public function visitStmtList(Node $node)
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfBlock($node->children ?? []);
        $node->flags = $status;
        return $status;
    }

    // TODO: Check if for/while/foreach block will execute at least once.
    // (e.g. for ($i = 0; $i < 10; $i++) is guaranteed to work)
    // For now, assume it's possible they may execute 0 times.

    /**
     * Analyzes any type of node with a statement list
     * @return int - the exit status code
     */
    private function analyzeBranched(Node $node)
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfBranched($node);
        $node->flags = $status;
        return $status;
    }

    public function computeStatusOfBranched(Node $node) : int {
        // A do-while statement and an if branch are executed at least once (or exactly once)
        // TODO: deduplicate
        $stmts = $node->children['stmts'];
        if (\is_null($stmts)) {
            return self::STATUS_PROCEED;
        }
        // We can have a single statement in the 'stmts' field when no braces exist?
        // TODO: no longer the case in ast version 40?
        if (!($stmts instanceof Node)) {
            return self::STATUS_PROCEED;
        }
        // This may be a statement list (or in rare cases, a statement?)
        $status = $this->check($stmts);
        if ($node->kind === \ast\AST_DO_WHILE) {
            // ignore break/continue within a do{}while ($cond);
            return in_array($status, [self::STATUS_THROW, self::STATUS_RETURN]) ? $status : self::STATUS_PROCEED;
        }
        return $status;
    }

    /**
     * Analyzes a node with kind \ast\AST_IF
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     * @override
     */
    public function visitIf(Node $node)
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfIf($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfIf(Node $node) : int {
        $has_if_elems_for_all_cases = false;
        $min_status = self::STATUS_RETURN;
        foreach ($node->children as $child_node) {
            $status = $this->check($child_node->children['stmts']);
            if ($status < $min_status) {
                $min_status = $status;
            }
            if ($min_status === self::STATUS_PROCEED) {
                break;
            }

            $cond_node = $child_node->children['cond'];
            // check for "else" or "elseif (true)"
            if ($cond_node === null || self::isTruthyLiteral($cond_node)) {
                $has_if_elems_for_all_cases = true;
                break;
            }
        }
        if (!$has_if_elems_for_all_cases) {
            return self::STATUS_PROCEED;
        }
        return $min_status;
    }


    /**
     * Analyzes a node with kind \ast\AST_DO_WHILE
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     */
    public function visitDoWhile(Node $node)
    {
        // TODO: Also account for conditionals, e.g. do{ }while(true); with no break statement.
        return $this->analyzeBranched($node);
    }

    /**
     * Analyzes a node with kind \ast\AST_IF_ELEM
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     */
    public function visitIfElem(Node $node)
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->visitStmtList($node->children['stmts']);
        $node->flags = $status;
        return $status;
    }

    /**
     * @param \ast\Node[] $block
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     */
    private function computeStatusOfBlock(array $block) : int
    {
        $maybe_status = 0;
        foreach ($block as $child) {
            if ($child === null) {
                continue;
            }
            // e.g. can be non-Node for statement lists such as `if ($a) { return; }echo "X";2;` (under unknown conditions)
            if (!($child instanceof Node)) {
                continue;
            }
            $status = $this->check($child);
            if ($status !== self::STATUS_PROCEED) {
                if ($status & self::STATUS_MAYBE_BITMASK) {
                    if (!$maybe_status || $status < $maybe_status) {
                        $maybe_status = $status;
                    }
                } else {
                    if ($maybe_status) {
                        // E.g. if this statement is guaranteed to throw, but an earlier statement may break,
                        // then the statement list is guarenteed to break/throw.
                        $equivalent_status = $maybe_status << self::BITSHIFT_FOR_MAYBE;
                        return min($status, $equivalent_status);
                    }
                    // The statement after this one is unreachable, due to unconditional continue/break/throw/return.
                    return $status;
                }
            }
        }
        return self::STATUS_PROCEED;
    }

    public static function willUnconditionallySkipRemainingStatements(Node $node) : bool
    {
        return ((new self())($node) & self::STATUS_CERTAIN_BITMASK) !== 0;
    }

    public static function willUnconditionallyThrowOrReturn(Node $node) : bool
    {
        return ((new self())($node) & self::STATUS_THROW_OR_RETURN_BITMASK) !== 0;
    }
}
