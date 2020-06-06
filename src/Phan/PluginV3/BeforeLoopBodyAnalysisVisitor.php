<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use ast\Node;
use BadMethodCallException;

/**
 * For plugins that want to analyze loop conditions before the body.
 *
 * Public APIs for use by plugins:
 *
 * - visitForeach(...), visitFor(...), visitWhile(...), visitDoWhile(...) (Override these methods)
 * - emitPluginIssue(CodeBase $code_base, Config $config, ...) (Call these methods)
 * - emit(...)
 * - Public methods from Phan\AST\AnalysisVisitor
 *
 * TODO Parent interface is too broad
 */
abstract class BeforeLoopBodyAnalysisVisitor extends PluginAwareBaseAnalysisVisitor
{
    // Subclasses should declare protected $parent_node_list as an instance property if they need to know the list.

    // @var list<Node> - Set after the constructor is called if an instance property with this name is declared
    // protected $parent_node_list;

    // Implementations should omit the constructor or call parent::__construct(CodeBase $code_base, Context $context)

    final public function visitArgList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitArray(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitArrayElem(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitArrowFunc(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitAssign(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitAssignOp(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitAssignRef(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitBinaryOp(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitBreak(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitCall(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitCast(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitCatch(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClass(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClassConst(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClassConstDecl(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClassName(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClosure(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClosureUses(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClosureVar(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitConst(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitConstDecl(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitConstElem(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitDeclare(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitDim(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitEcho(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitEmpty(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitEncapsList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitExit(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitExprList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitFuncDecl(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitIsset(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitGlobal(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitGroupUse(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitIf(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitIfElem(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitInstanceof(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitMagicConst(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitMethod(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitMethodCall(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitName(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitNamespace(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitNew(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitParam(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitParamList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPreInc(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPrint(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitProp(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPropGroup(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPropDecl(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPropElem(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitReturn(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitStatic(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitStaticCall(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitStaticProp(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitStmtList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitSwitch(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitSwitchCase(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitSwitchList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitType(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitTypeUnion(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitNullableType(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitUnaryOp(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitUse(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitUseElem(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitUseTrait(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitVar(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitCatchList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitClone(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitConditional(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitContinue(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitGoto(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitHaltCompiler(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitIncludeOrEval(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitLabel(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitMethodReference(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitNameList(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPostDec(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPostInc(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitPreDec(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitRef(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitShellExec(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitThrow(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitTraitAdaptations(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitTraitAlias(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitTraitPrecedence(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitTry(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitUnpack(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitUnset(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitYield(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }

    final public function visitYieldFrom(Node $node)
    {
        throw new BadMethodCallException(static::class . ' not expected to use this method');
    }
}
