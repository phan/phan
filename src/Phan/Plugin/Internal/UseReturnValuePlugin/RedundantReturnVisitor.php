<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\UseReturnValuePlugin;

use ast;
use ast\Node;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\AST\ASTHasher;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Parse\ParseVisitor;

/**
* Checks for invocations of functions/methods where the return value should be used.
* Also, gathers statistics on how often those functions/methods are used.
* @phan-file-suppress PhanAccessPropertyInternal
*/
class RedundantReturnVisitor
{
    /** @var CodeBase */
    private $code_base;
    /** @var Context */
    private $context;
    /** @var Node */
    private $stmts;
    public function __construct(CodeBase $code_base, Context $context, Node $stmts)
    {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->stmts = $stmts;
    }

    /**
     * Check for any code paths where 2 or more return statements would return the same value
     */
    public function analyze() : void
    {
        try {
            $this->analyzeNode($this->stmts);
        } catch (IssueException $e) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $e->getIssueInstance()
            );
        }
    }

    /**
     * @return list<Node>
     * @throws IssueException for the first issue seen in this function-like, if any
     * TODO: Instead, iterate backwards through the AST_STMT_LIST to check if the last group of returns and second-last group of returns are redundant.
     */
    private function analyzeNode(Node $stmts) : array
    {
        $kind = $stmts->kind;
        switch ($kind) {
            // Nodes that create new scopes
            case ast\AST_FUNC_DECL:
            case ast\AST_CLASS:
            case ast\AST_CLOSURE:
            case ast\AST_METHOD:
            // Nodes that can't contain return statements.
            case ast\AST_CALL:
            case ast\AST_PROP:
            case ast\AST_STATIC_PROP:
            case ast\AST_STATIC_CALL:
            case ast\AST_METHOD_CALL:
            case ast\AST_UNARY_OP:
            case ast\AST_BINARY_OP:
            case ast\AST_ASSIGN:
            case ast\AST_ASSIGN_OP:
            case ast\AST_ECHO:
                return [];
            case ast\AST_RETURN:
                return [$stmts];
        }
        $possible_return_nodes = [];
        foreach ($stmts->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            foreach ($this->analyzeNode($child) as $return_instance) {
                $possible_return_nodes[] = $return_instance;
            }
        }
        if ($stmts->kind !== ast\AST_STMT_LIST) {
            return $possible_return_nodes;
        }
        if (count($possible_return_nodes) === 0) {
            return $possible_return_nodes;
        }

        if (count($possible_return_nodes) <= 1 && ($stmts !== $this->stmts || count($possible_return_nodes) === 0)) {
            return $possible_return_nodes;
        }
        $exit_status = (new BlockExitStatusChecker())->__invoke($stmts);
        if ($exit_status !== BlockExitStatusChecker::STATUS_RETURN) {
            if ($stmts !== $this->stmts || !($exit_status & BlockExitStatusChecker::STATUS_PROCEED)) {
                return $possible_return_nodes;
            }
            // This is the function body, and there's one code path where it will not return a value.
            $line = $this->stmts->lineno;
            $possible_return_nodes[] = new Node(ast\AST_RETURN, 0, [
                'expr' => new Node(ast\AST_CONST, 0, [
                    'name' => new Node(ast\AST_NAME, ast\flags\NAME_NOT_FQ, ['name' => 'null'], $line)
                ], $line)
            ], $line);
        }
        // There are 2 or more possible returned statements. Check if all returned expressions are the same.

        // @phan-suppress-next-line PhanPartialTypeMismatchArgument can't understand count() assertions
        $this->checkMultipleReturns($possible_return_nodes);
        return $possible_return_nodes;
    }

    /**
     * @param non-empty-list<Node> $possible_return_nodes
     * @throws IssueException for the first issue seen in this function-like, if any
     */
    private function checkMultipleReturns(array $possible_return_nodes) : void
    {
        if (count($possible_return_nodes) <= 1) {
            return;
        }
        $remaining_returns = $possible_return_nodes;
        $last_return = array_pop($remaining_returns);
        $last_expr = $last_return->children['expr'];
        if (!ParseVisitor::isConstExpr($last_expr)) {
            return;
        }
        $last_hash = ASTHasher::hash($last_expr);
        $resolved_last_value = false;
        $last_value = null;
        foreach ($remaining_returns as $return) {
            $expr = $return->children['expr'];
            if (!ParseVisitor::isConstExpr($expr)) {
                return;
            }
            if (ASTHasher::hash($expr) === $last_hash) {
                continue;
            }
            if (!$resolved_last_value) {
                $last_value = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $last_expr)->asSingleScalarValueOrNullOrSelf();
                if (is_object($last_value)) {
                    return;
                }
                $resolved_last_value = true;
            }
            $value = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $expr)->asSingleScalarValueOrNullOrSelf();
            if ($value !== $last_value) {
                return;
            }
            // This is the same value as the previous return, e.g. `return 1+1;` and `return 2;`
        }
        throw new IssueException(
            Issue::fromType(Issue::UnusedReturnBranchWithoutSideEffects)(
                $this->context->getFile(),
                $last_return->lineno,
                [ASTReverter::toShortString($last_expr), reset($remaining_returns)->lineno]
            )
        );
    }
}
