<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\FQSENException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\NeverType;
use Phan\Parse\ParseVisitor;

use function count;

/**
 * This checks what exit statuses are possible for AST nodes: `break;`, `continue;`, `throw`, `return`, proceeding, etc.
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
 *
 * @phan-file-suppress PhanUnusedPublicFinalMethodParameter
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 */
final class BlockExitStatusChecker extends KindVisitorImplementation
{
    // These should be at most 1 << 30, in order to work in 32-bit php. (Bits 20 to 30 are all in use)
    // NOTE: Any exit status must be a combination of at least one of these bits
    // E.g. if STATUS_PROCEED is mixed with STATUS_RETURN, it would mean it is possible both to go to completion or return.
    public const STATUS_PROCEED        = (1 << 20);       // At least one branch continues to completion.
    public const STATUS_GOTO           = (1 << 21);       // At least one branch leads to a goto statement
    public const STATUS_CONTINUE       = (1 << 22);       // At least one branch leads to a continue statement
    public const STATUS_BREAK          = (1 << 23);       // At least one branch leads to a break statement
    public const STATUS_THROW          = (1 << 24);       // At least one branch leads to a throw statement
    public const STATUS_RETURN         = (1 << 25);       // At least one branch leads to a return statement
    public const STATUS_NORETURN       = (1 << 26);       // At least one branch leads to a exit() statement (or an infinite loop)

    // `break N`/`continue N` with N of 2 or 3. A loop or switch turns these into the status for the next level when they leave it.
    // (With N > 3, they are treated as `break`/`continue` without a depth.)
    // (Only kept in the flags of statements: expressions use these bits for the flags of PhanAnnotationAdder)
    public const STATUS_BREAK_2        = (1 << 27);       // At least one branch leads to `break 2`: it leaves the loop/switch around the innermost one
    public const STATUS_CONTINUE_2     = (1 << 28);       // At least one branch leads to `continue 2`
    public const STATUS_BREAK_3        = (1 << 29);       // At least one branch leads to `break 3`
    public const STATUS_CONTINUE_3     = (1 << 30);       // At least one branch leads to `continue 3`

    /** The statuses of `break N`/`continue N` with N > 1 */
    public const STATUS_OUTER_EXIT_BITMASK =
        self::STATUS_BREAK_2 |
        self::STATUS_CONTINUE_2 |
        self::STATUS_BREAK_3 |
        self::STATUS_CONTINUE_3;

    public const STATUS_THROW_OR_RETURN_BITMASK =
        self::STATUS_THROW |
        self::STATUS_RETURN |
        self::STATUS_NORETURN;

    public const STATUS_NOT_RETURN_BITMASK =
        self::STATUS_THROW |
        self::STATUS_NORETURN;

    // Any status which doesn't lead to proceeding.
    public const STATUS_NOT_PROCEED_BITMASK =
        self::STATUS_GOTO |
        self::STATUS_CONTINUE |
        self::STATUS_BREAK |
        self::STATUS_THROW |
        self::STATUS_RETURN |
        self::STATUS_NORETURN;

    public const STATUS_BITMASK =
        self::STATUS_PROCEED |
        self::STATUS_NOT_PROCEED_BITMASK;

    /**
     * The kinds of nodes that a status with STATUS_OUTER_EXIT_BITMASK is cached for (with STATUS_STATEMENT_BITMASK).
     * PhanAnnotationAdder must not add its flags to these kinds. (Checked by BlockExitStatusCheckerTest)
     */
    public const KINDS_WITH_STATEMENT_STATUS = [
        ast\AST_STMT_LIST,
        ast\AST_IF,
        ast\AST_IF_ELEM,
        ast\AST_TRY,
        ast\AST_CATCH_LIST,
        ast\AST_SWITCH_LIST,
        ast\AST_SWITCH_CASE,
    ];

    /** The bits of the status that is cached in the flags of a statement. (STATUS_BITMASK is used for expressions) */
    private const STATUS_STATEMENT_BITMASK =
        self::STATUS_BITMASK |
        self::STATUS_OUTER_EXIT_BITMASK;

    public const STATUS_MAYBE_PROCEED =
        self::STATUS_PROCEED |
        self::STATUS_GOTO;

    /** @var ?CodeBase */
    private $code_base;

    /** @var ?Context */
    private $context;

    /**
     * @var list<Node|string|int|float|null> the statement list currently being analyzed by computeStatusOfBlock,
     * used to resolve `$var = new ClassName(); $var->methodReturningNever();`
     */
    private $current_block = [];

    /** @var int the index within $current_block of the statement currently being analyzed */
    private $current_block_index = -1;

    /** @var int the statuses of the code after a label for which statusFromLabels() gives up */
    private $status_not_followed_after_label = self::STATUS_BREAK | self::STATUS_CONTINUE | self::STATUS_OUTER_EXIT_BITMASK;

    public function __construct(
        ?CodeBase $code_base = null,
        ?Context $context = null
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * Computes the bitmask representing the possible ways this block of code might exit.
     *
     * This currently does not handle goto or `break N` comprehensively.
     */
    public function check(?Node $node = null): int
    {
        if (!$node) {
            return self::STATUS_PROCEED;
        }
        $result = $this->__invoke($node);
        if (!\is_int($result) || $result <= 0) {
            throw new AssertionError('Expected positive int');
        }
        return $result;
    }

    /**
     * If we don't know how to analyze a node type (or left it out), assume it always proceeds
     * @return int - The status bitmask corresponding to always proceeding
     */
    public function visit(Node $node): int
    {
        return self::STATUS_PROCEED;
    }

    private static function isTruthyLiteral(Node|float|int|string $cond): bool
    {
        if ($cond instanceof Node) {
            // TODO: Could look up values for remaining constants and inline expressions, but doing that has low value.
            if ($cond->kind === ast\AST_CONST) {
                $cond_name_string = $cond->children['name']->children['name'] ?? null;
                return \is_string($cond_name_string) && \strcasecmp($cond_name_string, 'true') === 0;
            }
            return false;
        }
        // Cast string, int, etc. literal to a bool
        return (bool)$cond;
    }

    /**
     * A break statement unconditionally breaks out of a loop/switch
     * @return int the corresponding status code
     */
    public function visitBreak(Node $node): int
    {
        // (The depth is a Node for the invalid `break 1 + 1;`)
        return match ($node->children['depth']) {
            2 => self::STATUS_BREAK_2,
            3 => self::STATUS_BREAK_3,
            default => self::STATUS_BREAK,
        };
    }

    /**
     * A continue statement unconditionally continues out of a loop/switch.
     * @return int the corresponding status code
     */
    public function visitContinue(Node $node): int
    {
        // (The depth is a Node for the invalid `break 1 + 1;`)
        return match ($node->children['depth']) {
            2 => self::STATUS_CONTINUE_2,
            3 => self::STATUS_CONTINUE_3,
            default => self::STATUS_CONTINUE,
        };
    }

    /**
     * Converts the status of a loop or switch to what the statements around it see:
     * `break 2` in the loop is `break` for the loop around it.
     *
     * @param int $status the status of the loop/switch, in which `break`/`continue` for that loop/switch were already handled
     */
    private static function leaveLoopOrSwitch(int $status): int
    {
        $outer_status = $status & self::STATUS_OUTER_EXIT_BITMASK;
        if ($outer_status === 0) {
            return $status;
        }
        $status &= ~self::STATUS_OUTER_EXIT_BITMASK;
        if (($outer_status & self::STATUS_BREAK_2) !== 0) {
            $status |= self::STATUS_BREAK;
        }
        if (($outer_status & self::STATUS_CONTINUE_2) !== 0) {
            $status |= self::STATUS_CONTINUE;
        }
        if (($outer_status & self::STATUS_BREAK_3) !== 0) {
            $status |= self::STATUS_BREAK_2;
        }
        if (($outer_status & self::STATUS_CONTINUE_3) !== 0) {
            $status |= self::STATUS_CONTINUE_2;
        }
        return $status;
    }

    /**
     * A throw statement unconditionally throws
     * @return int the corresponding status code
     */
    public function visitThrow(Node $node): int
    {
        return self::STATUS_THROW;
    }

    /**
     * @return int the corresponding status code for the try/catch/finally block
     */
    public function visitTry(Node $node): int
    {
        $status = $node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfTry($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfTry(Node $node): int
    {
        $main_status = $this->check($node->children['try']);
        // Finding good heuristics is difficult.
        // e.g. "return someFunctionThatMayThrow()" in try{} block would be inferred as STATUS_RETURN, but may actually be STATUS_THROW

        $finally_node = $node->children['finally'];
        if ($finally_node) {
            $finally_status = $this->check($finally_node);
            // TODO: Could emit an issue as a side effect
            // Having any sort of status in a finally statement is
            // likely to have unintuitive behavior.
            if (($finally_status & (~self::STATUS_THROW_OR_RETURN_BITMASK)) === 0) {
                return $finally_status;
            }
        } else {
            $finally_status = self::STATUS_PROCEED;
        }
        $catches_node = $node->children['catches'];
        if (\count($catches_node->children) === 0) {
            return self::mergeFinallyStatus($main_status, $finally_status);
        }
        // TODO: Could enhance slightly by checking for catch nodes with the exact same types (or subclasses) as names of exception thrown.
        $combined_status = self::mergeFinallyStatus($main_status, $finally_status) | $this->visitCatchList($catches_node);
        if (($finally_status & self::STATUS_PROCEED) === 0) {
            $combined_status &= ~self::STATUS_PROCEED;
        }
        // No idea.
        return $combined_status;
    }

    /**
     * @return int the corresponding status code
     */
    public function visitCatchList(Node $node): int
    {
        $status = $node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfCatchList($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfCatchList(Node $node): int
    {
        $catch_list = $node->children;
        if (count($catch_list) === 0) {
            return self::STATUS_PROCEED;  // status probably won't matter
        }
        // TODO: Could enhance slightly by checking for catch nodes with the exact same types (or subclasses) as names of exception thrown.
        $combined_status = 0;
        // Try to cover all possible cases, such as try { return throwsException(); } catch(Exception $e) { break; }
        foreach ($node->children as $catch_node) {
            if (!$catch_node instanceof Node) {
                throw new AssertionError('Expected catch statement to be a Node');
            }
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null for catch nodes
            $catch_node_status = $this->visitStmtList($catch_node->children['stmts']);
            $combined_status |= $catch_node_status;
        }
        // No idea.
        return $combined_status;
    }

    private static function mergeFinallyStatus(int $try_status, int $finally_status): int
    {
        // If at least one of try or finally are guaranteed not to proceed to completion,
        // then combine those possibilities.
        if (($try_status & $finally_status & self::STATUS_PROCEED) === 0) {
            return ($try_status | $finally_status) & ~self::STATUS_PROCEED;
        }
        return $try_status | $finally_status;
    }

    /**
     * @return int the corresponding status code
     * @suppress PhanTypeMismatchArgumentNullable
     */
    public function visitSwitch(Node $node): int
    {
        $cond = $node->children['cond'];
        if ($cond instanceof Node) {
            $cond_status = $this->check($cond);
            if (($cond_status & self::STATUS_PROCEED) === 0) {
                // handle throw expressions, switch(exit()), etc.
                return $cond_status;
            }
        } else {
            $cond_status = self::STATUS_PROCEED;
        }
        return $this->visitSwitchList($node->children['stmts']) | ($cond_status & ~self::STATUS_PROCEED);
    }

    /**
     * @return int the corresponding status code
     */
    public function visitSwitchList(Node $node): int
    {
        $status = $node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfSwitchList($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfSwitchList(Node $node): int
    {
        $switch_stmt_case_nodes = $node->children ?? [];
        if (\count($switch_stmt_case_nodes) === 0) {
            return self::STATUS_PROCEED;
        }
        $has_default = false;
        $combined_statuses = 0;
        foreach ($switch_stmt_case_nodes as $index => $case_node) {
            if (!$case_node instanceof Node) {
                throw new AssertionError('Expected switch case to be a Node');
            }
            if ($case_node->children['cond'] === null) {
                $has_default = true;
            }
            $case_status = self::getStatusOfSwitchCase($case_node, $index, $switch_stmt_case_nodes);
            if (($case_status & self::STATUS_CONTINUE_OR_BREAK) !== 0) {
                // Ignore statuses such as break/continue. They take effect inside, but are a proceed status outside
                $case_status = ($case_status & ~self::STATUS_CONTINUE_OR_BREAK) | self::STATUS_PROCEED;
            }
            $combined_statuses |= $case_status;
        }
        if (!$has_default) {
            $combined_statuses |= self::STATUS_PROCEED;
        }
        return self::leaveLoopOrSwitch($combined_statuses);
    }

    /**
     * @param list<Node> $siblings
     */
    private function getStatusOfSwitchCase(Node $case_node, int $index, array $siblings): int
    {
        $status = $case_node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfSwitchCase($case_node, $index, $siblings);
        $case_node->flags = $status;
        return $status;
    }

    /**
     * @param array<mixed,Node|int|string|float> $siblings
     */
    private function computeStatusOfSwitchCase(Node $case_node, int $index, array $siblings): int
    {
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
        $status = $this->visitStmtList($case_node->children['stmts']);
        if (($status & self::STATUS_PROCEED) === 0) {
            // Check if the current switch case will not fall through.
            return $status;
        }
        $next_sibling = $siblings[$index + 1] ?? null;
        if (!$next_sibling instanceof Node) {
            return $status;
        }
        $next_status = self::getStatusOfSwitchCase($next_sibling, $index + 1, $siblings);
        // Combine the possibilities.
        // e.g. `case 1: if (cond()) { return; } case 2: throw;`, case 1 will either break or throw,
        // but won't proceed normally to the outside of the switch statement.
        return ($status & ~self::STATUS_PROCEED) | $next_status;
    }

    /**
     * @return int the corresponding status code
     * @suppress PhanTypeMismatchArgumentNullable
     */
    public function visitMatch(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $cond = $node->children['cond'];
        // The subject may be a literal, e.g. `match (1) { ... }`
        $cond_status = $cond instanceof Node ? $this->check($cond) : self::STATUS_PROCEED;
        if (($cond_status & self::STATUS_PROCEED) === 0) {
            return $cond_status;
        }
        return $this->visitMatchArmList($node->children['stmts']) | ($cond_status & ~self::STATUS_PROCEED);
    }

    /**
     * @return int the corresponding status code for the match arm list
     */
    public function visitMatchArmList(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfMatchArmList($node);
        $node->flags = $status;
        return $status;
    }

    /**
     * @return int the corresponding status code
     */
    public function visitMatchArm(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeMatchArmStatus($node);
        $node->flags |= $status;
        return $status;
    }

    private function computeMatchArmStatus(Node $node): int
    {
        ['cond' => $cond, 'expr' => $expr] = $node->children;
        $cond_status = 0;
        foreach ($cond->children ?? [] as $cond_expr) {
            if (!$cond_expr instanceof Node) {
                $cond_status |= self::STATUS_PROCEED;
                continue;
            }
            $cond_status |= $this->check($cond_expr);
            if (($cond_status & self::STATUS_PROCEED) === 0) {
                return $cond_status;
            }
        }
        return ($cond_status & ~self::STATUS_PROCEED) | ($expr instanceof Node ? $this->check($expr) : self::STATUS_PROCEED);
    }

    private function computeStatusOfMatchArmList(Node $node): int
    {
        $default_status = self::STATUS_THROW;  // UnhandledMatchError if no default node exists
        $combined_status = 0;
        foreach ($node->children as $arm_node) {
            // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
            $arm_cond = $arm_node->children['cond'];
            if ($arm_cond === null) {
                $default_status = $this->visitMatchArm($arm_node);
                continue;
            }
            $combined_status |= $this->visitMatchArm($arm_node);
        }
        return $default_status | $combined_status;
    }

    public const UNEXITABLE_LOOP_INNER_STATUS = self::STATUS_PROCEED | self::STATUS_CONTINUE;

    public const STATUS_CONTINUE_OR_BREAK = self::STATUS_CONTINUE | self::STATUS_BREAK;

    public function visitForeach(Node $node): int
    {
        // We assume foreach loops are over a finite sequence, and that it's possible for that sequence to have at least one element.
        $inner_status = $this->check($node->children['stmts']);

        // 1. break/continue apply to the inside of a loop, not outside. (`break 2;` is passed on to the loop around this one)
        // 2. We assume that it's possible that any given loop can have 0 iterations.
        //    A TODO exists above to check for special cases.
        return self::leaveLoopOrSwitch(($inner_status & ~self::STATUS_CONTINUE_OR_BREAK) | self::STATUS_PROCEED);
    }

    public function visitWhile(Node $node): int
    {
        $inner_status = $this->check($node->children['stmts']);
        // TODO: Check for unconditionally false conditions.
        if (self::isTruthyLiteral($node->children['cond'])) {
            // Use a special case to analyze "while (1) {exprs}" or "for (; true; ) {exprs}"
            // TODO: identify infinite loops, mark those as STATUS_NO_PROCEED or STATUS_RETURN.
            return self::leaveLoopOrSwitch(self::computeDerivedStatusOfInfiniteLoop($inner_status));
        }
        // This is (to our awareness) **not** an infinite loop


        // 1. break/continue apply to the inside of a loop, not outside. (`break 2;` is passed on to the loop around this one)
        // 2. We assume that it's possible that any given loop can have 0 iterations.
        //    A TODO exists above to check for special cases.
        return self::leaveLoopOrSwitch(($inner_status & ~self::STATUS_CONTINUE_OR_BREAK) | self::STATUS_PROCEED);
    }

    /**
     * @return int the corresponding status code
     */
    public function visitFor(Node $node): int
    {
        $inner_status = $this->check($node->children['stmts']);
        // for loops have an expression list as a condition.
        $cond_nodes = $node->children['cond']->children ?? [];  // NOTE: $node->children['cond'] is null for the expression `for (;;)`
        // TODO: Check for unconditionally false conditions.
        if (count($cond_nodes) === 0 || self::isTruthyLiteral(\end($cond_nodes))) {
            // Use a special case to analyze "while (1) {exprs}" or "for (; true; ) {exprs}"
            // TODO: identify infinite loops, mark those as STATUS_NO_PROCEED or STATUS_RETURN.
            return self::leaveLoopOrSwitch(self::computeDerivedStatusOfInfiniteLoop($inner_status));
        }
        // This is (to our awareness) **not** an infinite loop


        // 1. break/continue apply to the inside of a loop, not outside. (`break 2;` is passed on to the loop around this one)
        // 2. We assume that it's possible that any given loop can have 0 iterations.
        //    A TODO exists above to check for special cases.
        return self::leaveLoopOrSwitch(($inner_status & ~self::STATUS_CONTINUE_OR_BREAK) | self::STATUS_PROCEED);
        // TODO: Improve this by checking for loops which almost definitely have at least one iteration,
        // such as "foreach ([$val] as $v)" or "for ($i = 0; $i < 10; $i++)"

        // if (($inner_status & ~self::STATUS_THROW_OR_RETURN_BITMASK) === 0) {
        //     // The inside of the loop will unconditionally throw or return.
        //     return $inner_status
        // }
    }

    // Logic to determine status of "while (1) {exprs}" or "for (; true; ) {exprs}"
    // TODO: identify infinite loops, mark those as STATUS_NO_PROCEED or STATUS_RETURN.
    private static function computeDerivedStatusOfInfiniteLoop(int $inner_status): int
    {
        $status = $inner_status & ~self::UNEXITABLE_LOOP_INNER_STATUS;
        if ($status === 0) {
            return self::STATUS_NORETURN;  // this is an infinite loop, it didn't contain break/throw/return statements?
        }
        if (($status & self::STATUS_BREAK) !== 0) {
            // if the inside of "while (true) {} contains a break statement,
            // then execution can proceed past the end of the loop.
            return ($status & ~self::STATUS_BREAK) | self::STATUS_PROCEED;
        }
        return $status;
    }

    /**
     * A return statement unconditionally returns (Assume expression passed in doesn't throw)
     * @return int the corresponding status code
     */
    public function visitReturn(Node $node): int
    {
        return self::STATUS_RETURN;
    }

    /**
     * An exit statement unconditionally exits (Assume expression passed in doesn't throw)
     * @return int the corresponding status code
     */
    public function visitExit(Node $node): int
    {
        return self::STATUS_NORETURN;
    }

    /**
     * @return int the corresponding status code
     */
    public function visitUnaryOp(Node $node): int
    {
        // Don't modify $node->flags, use unmodified flags here
        if ($node->flags !== ast\flags\UNARY_SILENCE) {
            return self::STATUS_PROCEED;
        }
        // Analyze exit status of `@expr` like `expr` (e.g. @trigger_error())
        $expr = $node->children['expr'];
        if (!($expr instanceof Node)) {
            return self::STATUS_PROCEED;
        }
        return $this->__invoke($expr);
    }

    /**
     * Determines the exit status of a function call, such as trigger_error()
     *
     * NOTE: A trigger_error() statement may or may not exit, depending on the constant and user configuration.
     * @return int the corresponding status code
     */
    public function visitCall(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfCall($node);
        $node->flags = $status;
        return $status;
    }

    /**
     * Determines the exit status of a static method call.
     *
     * @return int the corresponding status code
     */
    public function visitStaticCall(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfStaticCall($node);
        $node->flags = $status;
        return $status;
    }

    /**
     * Determines the exit status of an instance method call.
     *
     * @return int the corresponding status code
     * @override
     * @see UseReturnValueVisitor::checkIfUsingFunctionThatNeverReturns()
     */
    public function visitMethodCall(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfMethodCall($node);
        $node->flags = $status;
        return $status;
    }

    /**
     * Determines the exit status of an instance method call.
     *
     * @return int the corresponding status code
     * @override
     */
    public function visitNullsafeMethodCall(Node $node): int
    {
        // TODO: The expression or arguments might unconditionally throw, though that is rare in practice.
        return ($node->flags & self::STATUS_BITMASK) ?: self::STATUS_PROCEED;
    }

    /**
     * @suppress PhanImpossibleTypeComparison
     */
    private function computeStatusOfCall(Node $node): int
    {
        $expression = $node->children['expr'];
        if ($expression instanceof Node) {
            if ($expression->kind !== ast\AST_NAME) {
                return self::STATUS_PROCEED;  // best guess
            }
            $function_name = $expression->children['name'];
            if (!\is_string($function_name)) {
                return self::STATUS_PROCEED;
            }
        } else {
            if (!\is_string($expression)) {
                return self::STATUS_THROW;  // Probably impossible.
            }
            $function_name = $expression;
        }
        if ($function_name === '') {
            // TODO: Check for all invalid fqsens, allowing 'NS\ClassName::methodName'
            return self::STATUS_THROW;  // nonsense such as ''();
        }
        if ($node->children['args']->kind === ast\AST_CALLABLE_CONVERT) {
            // This is creating a closure, not calling it.
            return self::STATUS_PROCEED;
        }
        if ($function_name[0] === '\\') {
            $function_name = \substr($function_name, 1);
        }
        // In AST version 120+, exit/die are represented as AST_CALL instead of AST_EXIT
        // This happens regardless of the PHP version running Phan
        if (\Phan\Config::AST_VERSION >= 120) {
            if ($function_name === 'exit' || $function_name === 'die') {
                return self::STATUS_NORETURN;
            }
        }
        if (\strcasecmp($function_name, 'trigger_error') === 0) {
            return self::computeTriggerErrorStatusCodeForConstant($node->children['args']->children[1] ?? null);
        }
        if ($function_name !== false && $this->code_base !== null && $this->context !== null) {
            try {
                $function_fqsen = FullyQualifiedFunctionName::fromStringInContext(
                    $function_name,
                    $this->context
                );
            } catch (FQSENException $e) {
                // @phan-suppress-previous-line PhanUnusedVariableCaughtException
                // Cannot find, cannot infer
                return self::STATUS_PROCEED;
            }
            // An unqualified name in a namespace is the function of the namespace if there is one, and the global function otherwise.
            // (fromStringInContext() gives the global function for a name that is not imported)
            if ($expression instanceof Node && $expression->flags === ast\flags\NAME_NOT_FQ
                && !\str_contains($function_name, '\\')
                && $this->context->getNamespace() !== '\\'
                && !$this->context->hasNamespaceMapFor(ast\flags\USE_FUNCTION, $function_name)
            ) {
                try {
                    $namespaced_function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString(
                        \rtrim($this->context->getNamespace(), '\\') . '\\' . $function_name
                    );
                    if ($this->code_base->hasFunctionWithFQSEN($namespaced_function_fqsen)) {
                        $function_fqsen = $namespaced_function_fqsen;
                    }
                } catch (FQSENException) {
                    // Keep the global function
                }
            }
            if ($this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
                $function = $this->code_base->getFunctionByFQSEN($function_fqsen);
                $returnUnion = $function->getUnionType();
                $unionTypes = $returnUnion->getTypeSet();
                $isNeverReturn = count($unionTypes) === 1 && $unionTypes[0] instanceof NeverType;
                if ($isNeverReturn || $this->callNeverReturnsByConditionalReturnType($function, $node, true)) {
                    return self::STATUS_NOT_RETURN_BITMASK;
                }
            }
        }
        // TODO: Could allow .phan/config.php or plugins to define additional behaviors, e.g. for methods.
        // E.g. if (!$var) {HttpFramework::generate_302_and_die(); }
        return self::STATUS_PROCEED;
    }

    /**
     * Does this call never return according to the conditional return type of the function?
     * (`(at)return ($args is array{exit: false} ? void : never)`)
     *
     * This is only decided when it does not depend on anything that is unknown here:
     *
     * - The arguments that the conditions look at are constant expressions (or are left out):
     *   the status of a call is cached and does not depend on the types that variables have where the call is made.
     * - An argument is not converted to the declared type of the parameter (`f('1')` for `int $x`).
     * - For a call of a method on an object and for `static::method()`, the arguments that the conditions look at are passed,
     *   and by position: a method that overrides this one can have other defaults and other parameter names.
     *   (Unless the method cannot be overridden)
     * - The conditional return type does not use template types.
     *
     * @param Node $node the node of the call
     * @param bool $is_exact_dispatch false if a method of a subclass may be called instead
     */
    private function callNeverReturnsByConditionalReturnType(FunctionInterface $function, Node $node, bool $is_exact_dispatch): bool
    {
        $conditional = $function->getConditionalReturnType();
        if ($conditional === null || $this->code_base === null || $this->context === null || $conditional->hasTemplateTypeRecursive() || $conditional->hasApproximatedCondition()) {
            return false;
        }
        $args = [];
        if (!$is_exact_dispatch && $function instanceof Method) {
            if ($function->isFinal() || $function->isPrivate() || $function->getClass($this->code_base)->isFinal()) {
                $is_exact_dispatch = true;
            } elseif ($function->isOverriddenByAnother()) {
                // A method that overrides this one may be called, and nothing makes it keep the conditional return type.
                return false;
            }
        }
        foreach ($node->children['args']->children as $arg) {
            if ($arg instanceof Node && ($arg->kind === ast\AST_UNPACK || (!$is_exact_dispatch && $arg->kind === ast\AST_NAMED_ARG))) {
                // (A method that overrides this one can give its parameters other names)
                return false;
            }
            $args[] = $arg;
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument the arguments of a call are a list
        $args = ArgumentType::normalizeNamedArgs($args, $function);
        // The call is analyzed elsewhere: nothing is reported for its arguments here.
        $old_is_discarding_issues = Issue::$is_discarding_issues;
        Issue::$is_discarding_issues = true;
        try {
            foreach ($function->getParameterList() as $i => $parameter) {
                if (!$conditional->usesParameter($parameter->getName())) {
                    continue;
                }
                if (!\array_key_exists($i, $args)) {
                    if (!$is_exact_dispatch || !$parameter->hasDefaultValue()) {
                        return false;
                    }
                    $default_value = $parameter->getDefaultValue();
                    if ($default_value instanceof Node && self::dependsOnCallingClass($default_value)) {
                        // e.g. `$name = __CLASS__` or `$mode = self::MODE` in a method of a trait
                        return false;
                    }
                    $arg_type = $parameter->getDefaultValueLiteralType();
                } else {
                    $arg = $args[$i];
                    if ($arg instanceof Node && (!ParseVisitor::isConstExpr($arg, ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION) || self::dependsOnCallingClass($arg))) {
                        return false;
                    }
                    $arg_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $arg);
                }
                // (Also for a default: `float $x = 1` is 1.0 in the function)
                $real_parameter_type = $parameter->getNonVariadicUnionType()->getRealUnionType();
                if (!$real_parameter_type->isEmpty() && !$arg_type->isStrictSubtypeOf($this->code_base, $real_parameter_type)) {
                    return false;
                }
            }
            $type = $function->resolveConditionalReturnType($this->code_base, $this->context, $args);
        } finally {
            Issue::$is_discarding_issues = $old_is_discarding_issues;
        }
        return $type !== null && $type->isNeverType();
    }

    /**
     * @return bool whether the value of this constant expression depends on the class that the code runs in:
     * `__CLASS__` in a trait, `static::NAME`, `static::class`, and `self::`/`parent::` (which depend on the class that uses a trait).
     */
    private static function dependsOnCallingClass(Node $node): bool
    {
        switch ($node->kind) {
            case ast\AST_MAGIC_CONST:
                return true;
            case ast\AST_CLASS_CONST:
            case ast\AST_CLASS_NAME:
                $class = $node->children['class'];
                // (`self` and `parent` are other classes for each class that uses a trait)
                if ($class instanceof Node && $class->kind === ast\AST_NAME && \in_array(\strtolower((string)$class->children['name']), ['static', 'self', 'parent'], true)) {
                    return true;
                }
                break;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && self::dependsOnCallingClass($child)) {
                return true;
            }
        }
        return false;
    }

    private function computeStatusOfStaticCall(Node $node): int
    {
        if ($this->code_base === null || $this->context === null) {
            return self::STATUS_PROCEED;
        }
        if ($node->children['args']->kind === ast\AST_CALLABLE_CONVERT) {
            // This is creating a closure, not calling it.
            return self::STATUS_PROCEED;
        }

        $class = $node->children['class'];
        if ($class instanceof Node) {
            if ($class->kind !== ast\AST_NAME) {
                return self::STATUS_PROCEED;  // best guess
            }
            $class_name = $class->children['name'];
            if (!\is_string($class_name)) {
                return self::STATUS_PROCEED;
            }
        } else {
            if (!\is_string($class)) {
                return self::STATUS_THROW;  // Probably impossible.
            }
            $class_name = $class;
        }

        $method = $node->children['method'];
        if ($method instanceof Node) {
            if ($method->kind !== ast\AST_NAME) {
                return self::STATUS_PROCEED;  // best guess
            }
            $method_name = $method->children['name'];
            if (!\is_string($method_name)) {
                return self::STATUS_PROCEED;
            }
        } else {
            if (!\is_string($method)) {
                return self::STATUS_THROW;  // Probably impossible.
            }
            $method_name = $method;
        }
        // Look for the class and method
        $normalized_class_name = \strtolower($class_name);
        if ($normalized_class_name === 'self' || $normalized_class_name === 'static') {
            $class_fqsen = $this->context->getClassFQSENOrNull();
        } elseif ($normalized_class_name === 'parent') {
            if (!$this->context->isInClassScope()) {
                // `parent::f()` outside of a class: reported elsewhere.
                return self::STATUS_PROCEED;
            }
            try {
                $class = $this->context->getClassInScope($this->code_base);
            } catch (CodeBaseException $e) {
                // @phan-suppress-previous-line PhanUnusedVariableCaughtException
                return self::STATUS_PROCEED;
            }
            if (!$class->hasParentType()) {
                return self::STATUS_PROCEED;
            }
            $class_fqsen = $class->getParentClassFQSEN();
        } else {
            try {
                $class_fqsen = FullyQualifiedClassName::fromStringInContext(
                    $class_name,
                    $this->context
                );
            } catch (FQSENException $e) {
                // @phan-suppress-previous-line PhanUnusedVariableCaughtException
                // Cannot find, cannot infer
                return self::STATUS_PROCEED;
            }
        }
        if (!isset($class_fqsen)) {
            return self::STATUS_PROCEED;
        }
        $method_fqsen = FullyQualifiedMethodName::make(
            $class_fqsen,
            $method_name
        );

        if ($this->code_base->hasMethodWithFQSEN($method_fqsen)) {
            $method = $this->code_base->getMethodByFQSEN($method_fqsen);
            $returnUnion = $method->getUnionType();
            $unionTypes = $returnUnion->getTypeSet();
            $isNeverReturn = count($unionTypes) === 1 && $unionTypes[0] instanceof NeverType;
            if ($isNeverReturn || $this->callNeverReturnsByConditionalReturnType($method, $node, $normalized_class_name !== 'static')) {
                return self::STATUS_NOT_RETURN_BITMASK;
            }
        }
        // TODO: Could allow .phan/config.php or plugins to define additional behaviors
        return self::STATUS_PROCEED;
    }

    private function computeStatusOfMethodCall(Node $node): int
    {
        if ($this->code_base === null || $this->context === null) {
            return self::STATUS_PROCEED;
        }
        if ($node->children['args']->kind === ast\AST_CALLABLE_CONVERT) {
            // This is creating a closure, not calling it.
            return self::STATUS_PROCEED;
        }

        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            if ($expr->kind !== ast\AST_VAR) {
                return self::STATUS_PROCEED;  // best guess
            }
            $var_name = $expr->children['name'];
            if (!\is_string($var_name)) {
                return self::STATUS_PROCEED;
            }
        } else {
            if (!\is_string($expr)) {
                return self::STATUS_THROW;  // Probably impossible.
            }
            $var_name = $expr;
        }

        $method = $node->children['method'];
        if ($method instanceof Node) {
            if ($method->kind !== ast\AST_NAME) {
                return self::STATUS_PROCEED;  // best guess
            }
            $method_name = $method->children['name'];
            if (!\is_string($method_name)) {
                return self::STATUS_PROCEED;
            }
        } else {
            if (!\is_string($method)) {
                return self::STATUS_THROW;  // Probably impossible.
            }
            $method_name = $method;
        }
        if ($var_name === 'this') {
            $class_fqsen = $this->context->getClassFQSENOrNull();
            if ($class_fqsen === null) {
                return self::STATUS_PROCEED;
            }
        } else {
            // Handle `$foo = new Foo(); $foo->neverReturns();` by scanning
            // earlier statements of the statement list being analyzed (issue #5553).
            // A `never` return type can only be overridden by `never` in subclasses,
            // so resolving through the statically known class is sound.
            $class_fqsen = $this->findClassOfPrecedingNewAssignment($var_name);
            if ($class_fqsen === null) {
                return self::STATUS_PROCEED;
            }
        }
        $method_fqsen = FullyQualifiedMethodName::make(
            $class_fqsen,
            $method_name
        );

        if ($this->code_base->hasMethodWithFQSEN($method_fqsen)) {
            $method = $this->code_base->getMethodByFQSEN($method_fqsen);
            $returnUnion = $method->getUnionType();
            $unionTypes = $returnUnion->getTypeSet();
            $isNeverReturn = count($unionTypes) === 1 && $unionTypes[0] instanceof NeverType;
            if ($isNeverReturn || $this->callNeverReturnsByConditionalReturnType($method, $node, false)) {
                return self::STATUS_NOT_RETURN_BITMASK;
            }
        }
        // TODO: Could allow .phan/config.php or plugins to define additional behaviors
        return self::STATUS_PROCEED;
    }

    /**
     * Searches the preceding statements of the statement list being analyzed for
     * `$var_name = new ClassName(...)` and returns ClassName's FQSEN if the class name
     * can be statically resolved and no intervening statement may reassign $var_name.
     */
    private function findClassOfPrecedingNewAssignment(string $var_name): ?FullyQualifiedClassName
    {
        if ($this->context === null) {
            return null;
        }
        for ($i = $this->current_block_index - 1; $i >= 0; $i--) {
            $stmt = $this->current_block[$i] ?? null;
            if (!($stmt instanceof Node)) {
                continue;
            }
            if (self::containsLabel($stmt)) {
                // A `goto` to this label may skip the assignment.
                return null;
            }
            if ($stmt->kind === ast\AST_ASSIGN) {
                $target = $stmt->children['var'];
                if ($target instanceof Node && $target->kind === ast\AST_VAR && $target->children['name'] === $var_name) {
                    // Found the most recent assignment to $var_name in this statement list.
                    $value = $stmt->children['expr'];
                    if (!($value instanceof Node) || $value->kind !== ast\AST_NEW) {
                        return null;
                    }
                    $class_node = $value->children['class'];
                    if (!($class_node instanceof Node) || $class_node->kind !== ast\AST_NAME) {
                        return null;
                    }
                    $class_name = $class_node->children['name'];
                    if (!\is_string($class_name)) {
                        return null;
                    }
                    $normalized_class_name = \strtolower($class_name);
                    if ($normalized_class_name === 'self' || $normalized_class_name === 'static') {
                        return $this->context->getClassFQSENOrNull();
                    }
                    if ($normalized_class_name === 'parent') {
                        return null;
                    }
                    // Bail if an earlier statement of this list may have created a reference
                    // to $var_name (e.g. `$alias =& $var;`) - a later write through the alias
                    // would rebind the variable without a direct assignment.
                    for ($j = $i - 1; $j >= 0; $j--) {
                        $earlier_stmt = $this->current_block[$j] ?? null;
                        if ($earlier_stmt instanceof Node && self::statementMayCreateReferenceToVariable($earlier_stmt, $var_name)) {
                            return null;
                        }
                    }
                    try {
                        return FullyQualifiedClassName::fromStringInContext($class_name, $this->context);
                    } catch (FQSENException $e) {
                        // @phan-suppress-previous-line PhanUnusedVariableCaughtException
                        return null;
                    }
                }
            }
            if (self::statementMayModifyVariable($stmt, $var_name)) {
                // e.g. a nested assignment inside an if block, a by-reference closure use, unset(), etc.
                return null;
            }
        }
        return null;
    }

    /**
     * Conservatively checks whether executing $node may reassign the variable $var_name.
     * (does not attempt to account for functions modifying variables by reference)
     */
    private static function statementMayModifyVariable(Node $node, string $var_name): bool
    {
        switch ($node->kind) {
            case ast\AST_ASSIGN_REF:
                // `$alias =& $var` makes subsequent writes through $alias rebind $var.
                if (self::assignmentTargetMayBeVariable($node->children['expr'], $var_name)) {
                    return true;
                }
                if (self::assignmentTargetMayBeVariable($node->children['var'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_ASSIGN:
            case ast\AST_ASSIGN_OP:
                if (self::assignmentTargetMayBeVariable($node->children['var'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_FOREACH:
                // foreach rebinds its value/key variables.
                if (self::assignmentTargetMayBeVariable($node->children['value'], $var_name)
                    || self::assignmentTargetMayBeVariable($node->children['key'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_CATCH:
                // catch binds the caught exception to its variable.
                if (self::assignmentTargetMayBeVariable($node->children['var'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_PRE_INC:
            case ast\AST_PRE_DEC:
            case ast\AST_POST_INC:
            case ast\AST_POST_DEC:
                if (self::assignmentTargetMayBeVariable($node->children['var'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_UNSET:
                if (self::assignmentTargetMayBeVariable($node->children['var'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_GLOBAL:
            case ast\AST_STATIC:
                $var = $node->children['var'];
                if ($var instanceof Node && self::assignmentTargetMayBeVariable($var, $var_name)) {
                    return true;
                }
                break;
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
                // Assignments inside a closure body don't affect the outer scope
                // unless the variable is imported by reference.
                foreach ($node->children['uses']->children ?? [] as $use) {
                    if ($use instanceof Node
                        && ($use->flags & ast\flags\CLOSURE_USE_REF)
                        && ($use->children['name'] === $var_name || !\is_string($use->children['name']))) {
                        return true;
                    }
                }
                // Arrow functions capture by value.
                return false;
            case ast\AST_FUNC_DECL:
            case ast\AST_CLASS:
                return false;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && self::statementMayModifyVariable($child, $var_name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Conservatively checks whether executing $node may create a reference to the variable $var_name
     * (making it possible for a later statement to rebind the variable without directly assigning to it).
     * (does not attempt to account for functions taking arguments by reference)
     */
    private static function statementMayCreateReferenceToVariable(Node $node, string $var_name): bool
    {
        switch ($node->kind) {
            case ast\AST_ASSIGN_REF:
                if (self::assignmentTargetMayBeVariable($node->children['var'], $var_name)
                    || self::assignmentTargetMayBeVariable($node->children['expr'], $var_name)) {
                    return true;
                }
                break;
            case ast\AST_GLOBAL:
            case ast\AST_STATIC:
                $var = $node->children['var'];
                if ($var instanceof Node && self::assignmentTargetMayBeVariable($var, $var_name)) {
                    return true;
                }
                break;
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
                foreach ($node->children['uses']->children ?? [] as $use) {
                    if ($use instanceof Node
                        && ($use->flags & ast\flags\CLOSURE_USE_REF)
                        && ($use->children['name'] === $var_name || !\is_string($use->children['name']))) {
                        return true;
                    }
                }
                // Arrow functions capture by value; assignments inside closure bodies don't escape.
                return false;
            case ast\AST_FUNC_DECL:
            case ast\AST_CLASS:
                return false;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && self::statementMayCreateReferenceToVariable($child, $var_name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether an assignment/unset target $target may refer to the variable $var_name.
     * @param Node|string|int|float|null $target
     */
    private static function assignmentTargetMayBeVariable(Node|string|int|float|null $target, string $var_name): bool
    {
        if (!($target instanceof Node)) {
            return false;
        }
        switch ($target->kind) {
            case ast\AST_VAR:
                $name = $target->children['name'];
                // A dynamic variable name (e.g. $$x) may refer to any variable.
                return !\is_string($name) || $name === $var_name;
            case ast\AST_ARRAY:
                // list()/array destructuring
                foreach ($target->children as $elem) {
                    if ($elem instanceof Node && self::assignmentTargetMayBeVariable($elem->children['value'] ?? null, $var_name)) {
                        return true;
                    }
                }
                return false;
            case ast\AST_REF:
                return self::assignmentTargetMayBeVariable($target->children['var'], $var_name);
            default:
                // Property/dim assignments ($x->prop = ..., $x[0] = ...) don't rebind the variable itself.
                return false;
        }
    }

    /**
     * @param \ast\Node|float|int|null|string $constant_ast
     */
    private static function computeTriggerErrorStatusCodeForConstant(Node|float|int|null|string $constant_ast): int
    {
        // return PROCEED if this can't be determined.
        // TODO: Could check for integer literals
        if (!($constant_ast instanceof Node)) {
            return self::STATUS_PROCEED;
        }
        if ($constant_ast->kind !== ast\AST_CONST) {
            return self::STATUS_PROCEED;
        }
        $name = $constant_ast->children['name']->children['name'] ?? null;
        if (!\is_string($name)) {
            return self::STATUS_PROCEED;
        }
        // The returned code for exit() is 'return', e.g. E_USER_ERROR makes trigger_error emit an error then abort execution.
        // NOTE: Native errors either emit a notice about being invalid error types to pass to this function (e.g. E_ERROR).
        // must be one of E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE, or E_USER_DEPRECATED
        if ($name === 'E_USER_ERROR') {
            // Fatal error
            return self::STATUS_NORETURN;
        }
        if (!\in_array($name, ['E_USER_WARNING', 'E_USER_NOTICE', 'E_USER_DEPRECATED'], true)) {
            // Newer php versions throw a ValueError for invalid types.
            return self::STATUS_THROW;
        }

        return self::STATUS_PROCEED;  // Assume this is a warning or notice?
    }

    /**
     * A statement list has the weakest return status out of all of the (non-PROCEEDing) statements.
     * FIXME: This is buggy, doesn't account for one statement having STATUS_CONTINUE some of the time but not all of it.
     *       (We don't check for STATUS_CONTINUE yet, so this doesn't matter yet.)
     * @return int the corresponding status code
     */
    public function visitStmtList(Node $node): int
    {
        $status = $node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfBlock($node->children);
        $node->flags = $status;
        return $status;
    }

    /**
     * An expression list has the weakest return status out of all of the (non-PROCEEDing) statements.
     * @return int the corresponding status code
     * @override
     */
    public function visitExprList(Node $node): int
    {
        $status = $node->flags & self::STATUS_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfBlock($node->children);
        $node->flags = $status;
        return $status;
    }

    /**
     * Analyzes a node with kind \ast\AST_IF
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     * @override
     */
    public function visitIf(Node $node): int
    {
        $status = $node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        $status = $this->computeStatusOfIf($node);
        $node->flags = $status;
        return $status;
    }

    private function computeStatusOfIf(Node $node): int
    {
        $has_if_elems_for_all_cases = false;
        $combined_statuses = 0;
        foreach ($node->children as $child_node) {
            '@phan-var Node $child_node';
            // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
            $status = $this->visitStmtList($child_node->children['stmts']);
            $combined_statuses |= $status;

            $cond_node = $child_node->children['cond'];
            // check for "else" or "elseif (true)"
            if ($cond_node === null || self::isTruthyLiteral($cond_node)) {
                $has_if_elems_for_all_cases = true;
                break;
            }
        }
        if (!$has_if_elems_for_all_cases) {
            $combined_statuses |= self::STATUS_PROCEED;
        }
        return $combined_statuses;
    }


    /**
     * Analyzes a node with kind \ast\AST_DO_WHILE
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     */
    public function visitDoWhile(Node $node): int
    {
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
        $inner_status = $this->visitStmtList($node->children['stmts']);
        if (($inner_status & ~(self::STATUS_THROW_OR_RETURN_BITMASK | self::STATUS_OUTER_EXIT_BITMASK)) === 0) {
            // The inner block throws, returns or leaves a loop around this one before the end can be reached.
            return self::leaveLoopOrSwitch($inner_status);
        }
        // TODO: Check for unconditionally false conditions.
        if (self::isTruthyLiteral($node->children['cond'])) {
            // Use a special case to analyze "while (1) {exprs}" or "for (; true; ) {exprs}"
            // TODO: identify infinite loops, mark those as STATUS_NO_PROCEED or STATUS_RETURN.
            return self::leaveLoopOrSwitch(self::computeDerivedStatusOfInfiniteLoop($inner_status));
        }
        // This is (to our awareness) **not** an infinite loop


        // 1. break/continue apply to the inside of a loop, not outside. (`break 2;` is passed on to the loop around this one)
        // 2. We assume that it's possible that any given loop can have 0 iterations.
        //    A TODO exists above to check for special cases.
        return self::leaveLoopOrSwitch(($inner_status & ~self::STATUS_CONTINUE_OR_BREAK) | self::STATUS_PROCEED);
    }

    /**
     * Analyzes a node with kind \ast\AST_IF_ELEM
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     */
    public function visitIfElem(Node $node): int
    {
        $status = $node->flags & self::STATUS_STATEMENT_BITMASK;
        if ($status) {
            return $status;
        }
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable this is never null
        $status = $this->visitStmtList($node->children['stmts']);
        $node->flags = $status;
        return $status;
    }

    /**
     * @return int the corresponding status code
     */
    public function visitGoto(Node $node): int
    {
        return self::STATUS_GOTO;
    }

    /**
     * @param list<Node> $block
     * @return int the exit status of a block (whether or not it would unconditionally exit, return, throw, etc.
     */
    private function computeStatusOfBlock(array $block): int
    {
        $outer_block = $this->current_block;
        $outer_block_index = $this->current_block_index;
        $this->current_block = $block;
        try {
            $maybe_status = 0;
            foreach ($block as $i => $child) {
                if ($child === null) {
                    continue;
                }
                // e.g. can be non-Node for statement lists such as `if ($a) { return; }echo "X";2;` (under unknown conditions)
                if (!($child instanceof Node)) {
                    continue;
                }
                $this->current_block_index = $i;
                $status = $this->check($child);
                if (($status & self::STATUS_PROCEED) === 0) {
                    // If it's guaranteed we won't proceed after this statement,
                    // then skip the subsequent statements.
                    return $status | ($maybe_status & ~self::STATUS_PROCEED);
                }
                $maybe_status |= $status;
            }
            return self::STATUS_PROCEED | $maybe_status;
        } finally {
            $this->current_block = $outer_block;
            $this->current_block_index = $outer_block_index;
        }
    }

    /**
     * Will the node $node unconditionally never fall through to the following statement?
     */
    public static function willUnconditionallySkipRemainingStatements(Node $node, ?CodeBase $code_base = null, ?Context $context = null): bool
    {
        return ((new self($code_base, $context))->__invoke($node) & self::STATUS_MAYBE_PROCEED) === 0;
    }

    /**
     * Will the node $node unconditionally throw or return (or exit),
     */
    public static function willUnconditionallyThrowOrReturn(Node $node, ?CodeBase $code_base = null, ?Context $context = null): bool
    {
        return ((new self($code_base, $context))->__invoke($node) & ~self::STATUS_THROW_OR_RETURN_BITMASK) === 0;
    }

    /**
     * Will the body of a function unconditionally throw or return (or exit)?
     *
     * Unlike willUnconditionallyThrowOrReturn(), this is true for a body that contains `goto` if no path reaches the end of the body:
     * a `goto` stays in the function, so it only matters where the code after each label can end up.
     *
     * @param Node $stmts the statement list of a function, method or closure
     */
    public static function willFunctionBodyUnconditionallyThrowOrReturn(Node $stmts, ?CodeBase $code_base = null, ?Context $context = null): bool
    {
        return self::willFunctionBodyOnlyExitWith($stmts, self::STATUS_THROW_OR_RETURN_BITMASK, $code_base, $context);
    }

    /**
     * Will the body of a function unconditionally throw or exit (or infinitely loop)?
     *
     * Unlike willUnconditionallyNeverReturn(), this is true for a body that contains `goto`
     * if no path reaches a `return` or the end of the body.
     *
     * @param Node $stmts the statement list of a function, method or closure
     */
    public static function willFunctionBodyUnconditionallyNeverReturn(Node $stmts, ?CodeBase $code_base = null, ?Context $context = null): bool
    {
        return self::willFunctionBodyOnlyExitWith($stmts, self::STATUS_NOT_RETURN_BITMASK, $code_base, $context);
    }

    /**
     * @param int $allowed_status the ways in which the body may be left (STATUS_THROW_OR_RETURN_BITMASK or STATUS_NOT_RETURN_BITMASK)
     */
    private static function willFunctionBodyOnlyExitWith(Node $stmts, int $allowed_status, ?CodeBase $code_base, ?Context $context): bool
    {
        $checker = new self($code_base, $context);
        $status = $checker->__invoke($stmts);
        if (($status & ~$allowed_status) === 0) {
            return true;
        }
        if (($status & ~($allowed_status | self::STATUS_GOTO)) !== 0 || $stmts->kind !== ast\AST_STMT_LIST) {
            return false;
        }
        // Without the jumps, every path is left in an allowed way. Check the code that the jumps continue at.
        $checker->status_not_followed_after_label |= self::STATUS_THROW_OR_RETURN_BITMASK & ~$allowed_status;
        return $checker->statusFromLabels($stmts->children) === 0;
    }

    /**
     * Where the code after a label may end up, for a label in $block or in a block nested in it:
     *
     * - 0: it always throws, returns or jumps again (or there is no label)
     * - STATUS_PROCEED: it may reach the end of $block
     * - STATUS_BREAK: it may leave an enclosing loop or switch with `break` or `continue`, or be in a `try` that catches what it throws.
     *   Nothing is known about where that continues.
     *   (Also if it may `return`, when checking that a function never returns)
     *
     * @param list<mixed> $block the statements of a block
     */
    private function statusFromLabels(array $block): int
    {
        $result = 0;
        foreach ($block as $i => $child) {
            if (!($child instanceof Node)) {
                continue;
            }
            if ($child->kind !== ast\AST_LABEL) {
                $nested_status = $this->statusFromLabelsInNode($child);
                if ($nested_status === 0) {
                    continue;
                }
                if ($nested_status !== self::STATUS_PROCEED) {
                    return $nested_status;
                }
                // The code after a label in a nested block may reach the end of that block.
                // Assume that the statement containing the block is then left normally.
            }
            $rest = new Node(ast\AST_STMT_LIST, 0, \array_slice($block, $i + 1), $child->lineno);
            $status = $this->__invoke($rest);
            if (($status & $this->status_not_followed_after_label) !== 0) {
                return self::STATUS_BREAK;
            }
            if (($status & self::STATUS_PROCEED) !== 0) {
                // Keep looking: the code after a later label may `break`.
                $result = self::STATUS_PROCEED;
            }
        }
        return $result;
    }

    /**
     * @return int statusFromLabels() for the statement lists in $node.
     * (Not looking into functions and classes declared in $node: a `goto` cannot jump into or out of them)
     */
    private function statusFromLabelsInNode(Node $node): int
    {
        switch ($node->kind) {
            case ast\AST_FUNC_DECL:
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
            case ast\AST_CLASS:
            case ast\AST_METHOD:
                return 0;
            case ast\AST_STMT_LIST:
                return $this->statusFromLabels($node->children);
            case ast\AST_TRY:
                // What the code after a label in `try` throws may be caught, and `finally` may return: not followed.
                return self::containsLabel($node) ? self::STATUS_BREAK : 0;
        }
        $result = 0;
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                $status = $this->statusFromLabelsInNode($child);
                if ($status === self::STATUS_BREAK) {
                    return $status;
                }
                $result |= $status;
            }
        }
        return $result;
    }

    /**
     * @return bool whether $node contains a label, outside of the functions and classes declared in it
     */
    private static function containsLabel(Node $node): bool
    {
        return self::containsKind($node, ast\AST_LABEL);
    }

    /**
     * @return bool whether $node contains a `goto`, outside of the functions and classes declared in it
     */
    public static function containsGoto(Node $node): bool
    {
        return self::containsKind($node, ast\AST_GOTO);
    }

    private static function containsKind(Node $node, int $kind): bool
    {
        switch ($node->kind) {
            case $kind:
                return true;
            case ast\AST_FUNC_DECL:
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
            case ast\AST_CLASS:
            case ast\AST_METHOD:
                return false;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && self::containsKind($child, $kind)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Will the node $node unconditionally throw or exit (or infinitely loop)
     */
    public static function willUnconditionallyNeverReturn(Node $node, ?CodeBase $code_base = null, ?Context $context = null): bool
    {
        return ((new self($code_base, $context))->__invoke($node) & ~self::STATUS_NOT_RETURN_BITMASK) === 0;
    }

    /**
     * Will the node $node unconditionally proceed (no break/continue, throw, or goto)
     */
    public static function willUnconditionallyProceed(Node $node): bool
    {
        return (new self())->__invoke($node) === self::STATUS_PROCEED;
    }
}
