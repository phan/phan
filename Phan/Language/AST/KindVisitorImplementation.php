<?php declare(strict_types=1);
namespace Phan\Language\AST;

use \ast\Node;

/**
 * A visitor of AST nodes based on the node's kind value
 * which does nothing upon visiting a node of any kind
 */
abstract class KindVisitorImplementation implements KindVisitor {

    abstract public function visit(Node $node);

    public function visitArgList(Node $node) {
        return $this->visit($node);
    }

    public function visitArray(Node $node) {
        return $this->visit($node);
    }

    public function visitArrayElem(Node $node) {
        return $this->visit($node);
    }

    public function visitAssign(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignOp(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignRef(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryOp(Node $node) {
        return $this->visit($node);
    }

    public function visitBreak(Node $node) {
        return $this->visit($node);
    }

    public function visitCall(Node $node) {
        return $this->visit($node);
    }

    public function visitCast(Node $node) {
        return $this->visit($node);
    }

    public function visitCatch(Node $node) {
        return $this->visit($node);
    }

    public function visitClass(Node $node) {
        return $this->visit($node);
    }

    public function visitClassConst(Node $node) {
        return $this->visit($node);
    }

    public function visitClassConstDecl(Node $node) {
        return $this->visit($node);
    }

    public function visitClosure(Node $node) {
        return $this->visit($node);
    }

    public function visitClosureUses(Node $node) {
        return $this->visit($node);
    }

    public function visitClosureVar(Node $node) {
        return $this->visit($node);
    }

    public function visitCoalesce(Node $node) {
        return $this->visit($node);
    }

    public function visitConst(Node $node) {
        return $this->visit($node);
    }

    public function visitConstDecl(Node $node) {
        return $this->visit($node);
    }

    public function visitConstElem(Node $node) {
        return $this->visit($node);
    }

    public function visitDeclare(Node $node) {
        return $this->visit($node);
    }

    public function visitDim(Node $node) {
        return $this->visit($node);
    }

    public function visitDoWhile(Node $node) {
        return $this->visit($node);
    }

    public function visitEcho(Node $node) {
        return $this->visit($node);
    }

    public function visitEmpty(Node $node) {
        return $this->visit($node);
    }

    public function visitEncapsList(Node $node) {
        return $this->visit($node);
    }

    public function visitExit(Node $node) {
        return $this->visit($node);
    }

    public function visitExprList(Node $node) {
        return $this->visit($node);
    }

    public function visitForeach(Node $node) {
        return $this->visit($node);
    }

    public function visitFuncDecl(Node $node) {
        return $this->visit($node);
    }

    public function visitIsset(Node $node) {
        return $this->visit($node);
    }

    public function visitGlobal(Node $node) {
        return $this->visit($node);
    }

    public function visitGreater(Node $node) {
        return $this->visit($node);
    }

    public function visitGreaterEqual(Node $node) {
        return $this->visit($node);
    }

    public function visitGroupUse(Node $node) {
        return $this->visit($node);
    }

    public function visitIf(Node $node) {
        return $this->visit($node);
    }

    public function visitIfElem(Node $node) {
        return $this->visit($node);
    }

    public function visitInstanceof(Node $node) {
        return $this->visit($node);
    }

    public function visitList(Node $node) {
        return $this->visit($node);
    }

    public function visitMagicConst(Node $node) {
        return $this->visit($node);
    }

    public function visitMethod(Node $node) {
        return $this->visit($node);
    }

    public function visitMethodCall(Node $node) {
        return $this->visit($node);
    }

    public function visitName(Node $node) {
        return $this->visit($node);
    }

    public function visitNamespace(Node $node) {
        return $this->visit($node);
    }

    public function visitNew(Node $node) {
        return $this->visit($node);
    }

    public function visitParam(Node $node) {
        return $this->visit($node);
    }

    public function visitParamList(Node $node) {
        return $this->visit($node);
    }

    public function visitPreInc(Node $node) {
        return $this->visit($node);
    }

    public function visitPrint(Node $node) {
        return $this->visit($node);
    }

    public function visitProp(Node $node) {
        return $this->visit($node);
    }

    public function visitPropDecl(Node $node) {
        return $this->visit($node);
    }

    public function visitPropElem(Node $node) {
        return $this->visit($node);
    }

    public function visitReturn(Node $node) {
        return $this->visit($node);
    }

    public function visitStatic(Node $node) {
        return $this->visit($node);
    }

    public function visitStaticCall(Node $node) {
        return $this->visit($node);
    }

    public function visitStaticProp(Node $node) {
        return $this->visit($node);
    }

    public function visitStmtList(Node $node) {
        return $this->visit($node);
    }

    public function visitSwitch(Node $node) {
        return $this->visit($node);
    }

    public function visitSwitchCase(Node $node) {
        return $this->visit($node);
    }

    public function visitSwitchList(Node $node) {
        return $this->visit($node);
    }

    public function visitType(Node $node) {
        return $this->visit($node);
    }

    public function visitUnaryMinus(Node $node) {
        return $this->visit($node);
    }

    public function visitUnaryOp(Node $node) {
        return $this->visit($node);
    }

    public function visitUse(Node $node) {
        return $this->visit($node);
    }

    public function visitUseElem(Node $node) {
        return $this->visit($node);
    }

    public function visitUseTrait(Node $node) {
        return $this->visit($node);
    }

    public function visitVar(Node $node) {
        return $this->visit($node);
    }

    public function visitWhile(Node $node) {
        return $this->visit($node);
    }

}
