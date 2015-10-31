<?php declare(strict_types=1);
namespace Phan\Language\AST;

use \ast\Node;

/**
 * A visitor of AST nodes based on the node's kind value
 */
interface KindVisitor {

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     */
    public function visitArgList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     */
    public function visitArray(Node $node);

    /**
     * Visit a node with kind `ast\AST_ARRAY_ELEM`
     */
    public function visitArrayElem(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ASSIGN`
     */
    public function visitAssign(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_OP`
     */
    public function visitAssignOp(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_REF`
     */
    public function visitAssignRef(Node $node);

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     */
    public function visitBinaryOp(Node $node);

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     */
    public function visitBreak(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CALL`
     */
    public function visitCall(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CAST`
     */
    public function visitCast(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CATCH`
     */
    public function visitCatch(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CLASS`
     */
    public function visitClass(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     */
    public function visitClassConst(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST_DECL`
     */
    public function visitClassConstDecl(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CLOSURE`
     */
    public function visitClosure(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CLOSURE_USES`
     */
    public function visitClosureUses(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CLOSURE_VAR`
     */
    public function visitClosureVar(Node $node);

    /**
     * Visit a node with kind `\ast\AST_COALESCE`
     */
    public function visitCoalesce(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CONST`
     */
    public function visitConst(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CONST_DECL`
     */
    public function visitConstDecl(Node $node);

    /**
     * Visit a node with kind `\ast\AST_CONST_ELEM`
     */
    public function visitConstElem(Node $node);

    /**
     * Visit a nod ewith kind `\ast\AST_DECLARE`
     */
    public function visitDeclare(Node $node);

    /**
     * Visit a node with kind `\ast\AST_DIM`
     */
    public function visitDim(Node $node);

    /**
     * Visit a node with kind `\ast\AST_DO_WHILE`
     */
    public function visitDoWhile(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ECHO`
     */
    public function visitEcho(Node $node);
    /**
     *
     * Visit a node with kind `\ast\AST_EMPTY`
     */
    public function visitEmpty(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ENCAPS_LIST`
     */
    public function visitEncapsList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_EXIT`
     */
    public function visitExit(Node $node);

    /**
     * Visit a node with kind `\ast\AST_EXPR_LIST`
     */
    public function visitExprList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_FOREACH`
     */
    public function visitForeach(Node $node);

    /**
     * Visit a node with kind `\ast\AST_FUNC_DECL`
     */
    public function visitFuncDecl(Node $node);

    /**
     * Visit a node with kind `\ast\AST_ISSET`
     */
    public function visitIsset(Node $node);

    /**
     * Visit a node with kind `\ast\AST_GLOBAL`
     */
    public function visitGlobal(Node $node);

    /**
     * Visit a node with kind `\ast\AST_GREATER`
     */
    public function visitGreater(Node $node);

    /**
     * Visit a node with kind `\ast\AST_GREATER_EQUAL`
     */
    public function visitGreaterEqual(Node $node);

    /**
     * Visit a node with kind `\ast\AST_GROUP_USE`
     */
    public function visitGroupUse(Node $node);

    /**
     * Visit a node with kind `\ast\AST_IF`
     */
    public function visitIf(Node $node);

    /**
     * Visit a node with kind `\ast\AST_IF_ELEM`
     */
    public function visitIfElem(Node $node);

    /**
     * Visit a node with kind `\ast\AST_INSTANCEOF`
     */
    public function visitInstanceof(Node $node);

    /**
     * Visit a node with kind `\ast\AST_LIST`
     */
    public function visitList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_MAGIC_CONST`
     */
    public function visitMagicConst(Node $node);

    /**
     * Visit a node with kind `\ast\AST_METHOD`
     */
    public function visitMethod(Node $node);

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     */
    public function visitMethodCall(Node $node);

    /**
     * Visit a node with kind `\ast\AST_NAME`
     */
    public function visitName(Node $node);

    /**
     * Visit a node with kind `\ast\AST_NAMESPACE`
     */
    public function visitNamespace(Node $node);

    /**
     * Visit a node with kind `\ast\AST_NEW`
     */
    public function visitNew(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PARAM`
     */
    public function visitParam(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PARAM_LIST`
     */
    public function visitParamList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PRE_INC`
     */
    public function visitPreInc(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PRINT`
     */
    public function visitPrint(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PROP`
     */
    public function visitProp(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PROP_DECL`
     */
    public function visitPropDecl(Node $node);

    /**
     * Visit a node with kind `\ast\AST_PROP_ELEM`
     */
    public function visitPropElem(Node $node);

    /**
     * Visit a node with kind `\ast\AST_RETURN`
     */
    public function visitReturn(Node $node);

    /**
     * Visit a node with kind `\ast\AST_STATIC`
     */
    public function visitStatic(Node $node);

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     */
    public function visitStaticCall(Node $node);

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     */
    public function visitStaticProp(Node $node);

    /**
     * Visit a node with kind `\ast\AST_STMT_LIST`
     */
    public function visitStmtList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_SWITCH`
     */
    public function visitSwitch(Node $node);

    /**
     * Visit a node with kind `\ast\AST_SWITCH_CASE`
     */
    public function visitSwitchCase(Node $node);

    /**
     * Visit a node with kind `\ast\AST_SWITCH_LIST`
     */
    public function visitSwitchList(Node $node);

    /**
     * Visit a node with kind `\ast\AST_TYPE`
     */
    public function visitType(Node $node);

    /**
     * Visit a node with kind `\ast\AST_UNARY_MINUS`
     */
    public function visitUnaryMinus(Node $node);

    /**
     * Visit a node with kind `\ast\AST_UNARY_OP`
     */
    public function visitUnaryOp(Node $node);

    /**
     * Visit a node with kind `\ast\AST_USE`
     */
    public function visitUse(Node $node);

    /**
     * Visit a node with kind `\ast\AST_USE_ELEM`
     */
    public function visitUseElem(Node $node);

    /**
     * Visit a node with kind `\ast\AST_USE_TRAIT`
     */
    public function visitUseTrait(Node $node);

    /**
     * Visit a node with kind `\ast\AST_VAR`
     */
    public function visitVar(Node $node);

    /**
     * Visit a node with kind `\ast\AST_WHILE`
     */
    public function visitWhile(Node $node);

}
