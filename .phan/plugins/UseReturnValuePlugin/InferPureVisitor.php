<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\Exception\NodeException;
use ast\Node;

/**
 * Used to check if a method is pure.
 * Throws NodeException if it sees a node that isn't likely to be in a method that is free of side effects.
 *
 * This ignores many edge cases, including:
 * - Magic properties
 * - The possibility of emitting notices or throwing
 * - Whether or not referenced elements exist (Phan checks that elsewhere)
 *
 * @phan-file-suppress PhanThrowTypeAbsent
 */
class InferPureVisitor extends AnalysisVisitor
{
    // visitAssignRef
    // visitThrow
    // visitEcho
    // visitPrint
    // visitIncludeOrExec
    public function visit(Node $node) : void {
        throw new NodeException($node);
    }

    public function visitVar(Node $node) : void {
        if (!is_scalar($node->children['name'])) {
            throw new NodeException($node);
        }
    }

    /** @override */
    public function visitClassName(Node $_) : void {
    }

    /** @override */
    public function visitMagicConst(Node $_) : void {
    }

    /** @override */
    public function visitConst(Node $_) : void {
    }

    /** @override */
    public function visitEmpty(Node $node) : void {
        $this->maybeInvoke($node->children['expr']);
    }

    /** @override */
    public function visitIsset(Node $node) : void {
        $this->maybeInvoke($node->children['var']);
    }

    /** @override */
    public function visitContinue(Node $_) : void {
    }

    /** @override */
    public function visitBreak(Node $_) : void {
    }

    /** @override */
    public function visitClassConst(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitStatic(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitArray(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitArrayElem(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitEncapsList(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitInstanceof(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    public function visitPreInc(Node $node) : void {
        $this->checkPureIncDec($node);
    }

    public function visitPreDec(Node $node) : void {
        $this->checkPureIncDec($node);
    }

    public function visitPostInc(Node $node) : void {
        $this->checkPureIncDec($node);
    }

    public function visitPostDec(Node $node) : void {
        $this->checkPureIncDec($node);
    }

    private function checkPureIncDec(Node $node) : void {
        $var = $node->children['var'];
        if (!$var instanceof Node) {
            throw new NodeException($node);
        }
        if ($var->kind !== ast\AST_VAR) {
            throw new NodeException($var);
        }
        $this->visitVar($var);
    }

    /**
     * @param Node|string|int|float|null $node
     */
    private function maybeInvoke($node) : void {
        if ($node instanceof Node) {
            $this->__invoke($node);
        }
    }

    public function visitBinaryOp(Node $node) : void {
        $this->maybeInvoke($node->children['left']);
        $this->maybeInvoke($node->children['right']);
    }

    public function visitUnaryOp(Node $node) : void {
        $this->maybeInvoke($node->children['expr']);
    }

    public function visitDim(Node $node) : void {
        $this->maybeInvoke($node->children['expr']);
        $this->maybeInvoke($node->children['dim']);
    }

    public function visitProp(Node $node) : void {
        ['expr' => $expr, 'prop' => $prop] = $node->children;
        if (!$expr instanceof Node) {
            throw new NodeException($node);
        }
        $this->__invoke($expr);
        if ($prop instanceof Node) {
            throw new NodeException($prop);
        }
    }

    /** @override */
    public function visitStmtList(Node $node) : void {
        foreach ($node->children as $stmt) {
            if ($stmt instanceof Node) {
                $this->__invoke($stmt);
            }
        }
    }

    /** @override */
    public function visitStaticProp(Node $node) : void {
        ['class' => $class, 'prop' => $prop] = $node->children;
        if (!$class instanceof Node) {
            throw new NodeException($node);
        }
        $this->__invoke($class);
        if ($prop instanceof Node) {
            throw new NodeException($prop);
        }
    }

    private function maybeInvokeAllChildNodes(Node $node) : void
    {
        foreach ($node->children as $c) {
            if ($c instanceof Node) {
                $this->__invoke($c);
            }
        }
    }

    /** @override */
    public function visitCast(Node $node) : void {
        $this->maybeInvoke($node->children['expr']);
    }

    /** @override */
    public function visitConditional(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitWhile(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitDoWhile(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitFor(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitForeach(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitIf(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitIfElem(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitSwitch(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitSwitchList(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitSwitchCase(Node $node) : void {
        $this->maybeInvokeAllChildNodes($node);
    }

    /** @override */
    public function visitGoto(Node $_) : void {
    }

    /** @override */
    public function visitLabel(Node $_) : void {
    }

    /** @override */
    public function visitAssignOp(Node $node) : void {
        $this->visitAssign($node);
    }

    /** @override */
    public function visitAssign(Node $node) : void {
        ['var' => $var, 'expr' => $expr] = $node->children;
        if (!$var instanceof Node) {
            throw new NodeException($node);
        }
        if ($var->kind !== ast\AST_VAR) {
            throw new NodeException($var);
        }
        $this->visitVar($var);
        if ($expr instanceof Node) {
            $this->__invoke($expr);
        }
    }

    /** @override */
    public function visitReturn(Node $node) : void {
        $expr_node = $node->children['expr'];
        if ($expr_node instanceof Node) {
            $this->__invoke($expr_node);
        }
    }

    /** @override */
    public function visitYield(Node $node) : void {
        $this->maybeInvoke($node->children['key']);
        $this->maybeInvoke($node->children['value']);
    }

    /** @override */
    public function visitYieldFrom(Node $node) : void {
        $this->maybeInvoke($node->children['expr']);
    }

    /** @override */
    public function visitName(Node $_) : void {
        // do nothing
    }

    /** @override */
    public function visitCall(Node $node) : void {
        $expr = $node->children['expr'];
        if (!is_string($expr)) {
            throw new NodeException($node);
        }
        $key = strtolower($expr);
        if (($key[0] ?? '') === '\\') {
            $key = substr($key, 1);
        }
        if ((UseReturnValuePlugin::HARDCODED_FQSENS[$key] ?? false) !== true) {
            throw new NodeException($node);
        }
        $this->visitArgList($node->children['args']);
    }

    // TODO: visitStaticCall
    public function visitArgList(Node $node) : void {
        foreach ($node->children as $x) {
            if ($x instanceof Node) {
                $this->__invoke($x);
            }
        }
    }
}
