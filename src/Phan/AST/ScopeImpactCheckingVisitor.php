<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Phan\CodeBase;
use Phan\Exception\NodeException;
use Phan\Language\Context;

/**
 * This checks if the expression/statement is likely to have an impact on inferences in the current scope.
 *
 * Based on InferPureVisitor.
 * - InferPureVisitor allows assignments, increments, and other operations that would affect the types in the scope, unlike this
 * - InferPureVisitor allows self-calls, unlike this
 * - InferPureVisitor allows moving to other scopes with break/continue, unlike this
 * - TODO: Allow function/method calls with side effects as long as they don't impact the current scope. e.g. continue to forbid extract().
 *
 * @phan-file-suppress PhanThrowTypeAbsent
 */
class ScopeImpactCheckingVisitor extends InferPureVisitor
{
    private const NOT_A_VALID_FQSEN_KEY = 'X';

    /**
     * Returns true if this expression has a possible impact on the inferences in this scope.
     * (e.g. calls that assign by reference, assignments, control flow, throwing, etc.)
     *
     * @param Node|string|int|float|null $node
     */
    public static function hasPossibleImpact(
        CodeBase $code_base,
        Context $context,
        $node
    ): bool {
        if (!($node instanceof Node)) {
            return false;
        }

        try {
            (new self($code_base, $context, self::NOT_A_VALID_FQSEN_KEY))($node);
            return false;
        } catch (NodeException $_) {
            return true;
        }
    }

    // echo/print don't impact the scope.
    public function visitEcho(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }

    public function visitPrint(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }


    public function visitVar(Node $node): void
    {
        if (!\is_scalar($node->children['name'])) {
            throw new NodeException($node);
        }
    }

    /** @override */
    public function visitContinue(Node $node): void
    {
        throw new NodeException($node);
    }

    public function visitBreak(Node $node): void
    {
        throw new NodeException($node);
    }

    public function visitPreInc(Node $node): void
    {
        throw new NodeException($node);
    }

    public function visitPreDec(Node $node): void
    {
        throw new NodeException($node);
    }

    public function visitPostInc(Node $node): void
    {
        throw new NodeException($node);
    }

    public function visitPostDec(Node $node): void
    {
        throw new NodeException($node);
    }

    protected function checkPureIncDec(Node $node): void
    {
        $var = $node->children['var'];
        if (!$var instanceof Node) {
            throw new NodeException($node);
        }
        if ($var->kind !== ast\AST_VAR) {
            throw new NodeException($var);
        }
        $this->visitVar($var);
    }

    /** @override */
    public function visitGoto(Node $node): void
    {
        throw new NodeException($node);
    }

    /** @override */
    public function visitAssignOp(Node $node): void
    {
        throw new NodeException($node);
    }

    /** @override */
    public function visitAssign(Node $node): void
    {
        throw new NodeException($node);
    }

    /** @override */
    public function visitReturn(Node $node): void
    {
        throw new NodeException($node);
    }

    /** @override */
    public function visitYield(Node $node): void
    {
        $this->maybeInvoke($node->children['key']);
        $this->maybeInvoke($node->children['value']);
    }

    /** @override */
    public function visitYieldFrom(Node $node): void
    {
        $this->maybeInvoke($node->children['expr']);
    }

    // TODO: Allow calls that accept scalars and regular data that wouldn't get modified?
}
