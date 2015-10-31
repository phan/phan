<?php declare(strict_types=1);
namespace Phan\Language\AST;

use \Phan\Debug;
use \ast\Node;

class Element {

    private $node = null;

    /**
     * @param Node $node
     * Any AST node.
     */
    public function __construct(Node $node) {
        $this->node = $node;
    }

    /**
     * Accepts a visitor that differentiates on the kind value
     * of the AST node.
     *
     * @return null
     */
    public function acceptKindVisitor(KindVisitor $visitor) {
        switch ($this->node->kind) {
        case \ast\AST_ARG_LIST:
            return $visitor->visitArgList($this->node);
        case \ast\AST_ARRAY:
            return $visitor->visitArray($this->node);
        case \ast\AST_ARRAY_ELEM:
            return $visitor->visitArrayElem($this->node);
        case \ast\AST_ASSIGN:
            return $visitor->visitAssign($this->node);
        case \ast\AST_ASSIGN_OP:
            return $visitor->visitAssignOp($this->node);
        case \ast\AST_ASSIGN_REF:
            return $visitor->visitAssignRef($this->node);
        case \ast\AST_BINARY_OP:
            return $visitor->visitBinaryOp($this->node);
        case \ast\AST_BREAK:
            return $visitor->visitBreak($this->node);
        case \ast\AST_CALL:
            return $visitor->visitCall($this->node);
        case \ast\AST_CAST:
            return $visitor->visitCast($this->node);
        case \ast\AST_CATCH:
            return $visitor->visitCatch($this->node);
        case \ast\AST_CLASS:
            return $visitor->visitClass($this->node);
        case \ast\AST_CLASS_CONST:
            return $visitor->visitClassConst($this->node);
        case \ast\AST_CLASS_CONST_DECL:
            return $visitor->visitClassConstDecl($this->node);
        case \ast\AST_CLOSURE:
            return $visitor->visitClosure($this->node);
        case \ast\AST_CLOSURE_USES:
            return $visitor->visitClosureUses($this->node);
        case \ast\AST_CLOSURE_VAR:
            return $visitor->visitClosureVar($this->node);
        case \ast\AST_COALESCE:
            return $visitor->visitCoalesce($this->node);
        case \ast\AST_CONST:
            return $visitor->visitConst($this->node);
        case \ast\AST_CONST_DECL:
            return $visitor->visitConstDecl($this->node);
        case \ast\AST_CONST_ELEM:
            return $visitor->visitConstElem($this->node);
        case \ast\AST_DECLARE:
            return $visitor->visitDeclare($this->node);
        case \ast\AST_DIM:
            return $visitor->visitDim($this->node);
        case \ast\AST_DO_WHILE:
            return $visitor->visitDoWhile($this->node);
        case \ast\AST_ECHO:
            return $visitor->visitEcho($this->node);
        case \ast\AST_EMPTY:
            return $visitor->visitEmpty($this->node);
        case \ast\AST_ENCAPS_LIST:
            return $visitor->visitEncapsList($this->node);
        case \ast\AST_EXIT:
            return $visitor->visitExit($this->node);
        case \ast\AST_EXPR_LIST:
            return $visitor->visitExprList($this->node);
        case \ast\AST_FOREACH:
            return $visitor->visitForeach($this->node);
        case \ast\AST_FUNC_DECL:
            return $visitor->visitFuncDecl($this->node);
        case \ast\AST_ISSET:
            return $visitor->visitIsset($this->node);
        case \ast\AST_GLOBAL:
            return $visitor->visitGlobal($this->node);
        case \ast\AST_GREATER:
            return $visitor->visitGreater($this->node);
        case \ast\AST_GREATER_EQUAL:
            return $visitor->visitGreaterEqual($this->node);
        case \ast\AST_GROUP_USE:
            return $visitor->visitGroupUse($this->node);
        case \ast\AST_IF:
            return $visitor->visitIf($this->node);
        case \ast\AST_IF_ELEM:
            return $visitor->visitIfElem($this->node);
        case \ast\AST_INSTANCEOF:
            return $visitor->visitInstanceof($this->node);
        case \ast\AST_LIST:
            return $visitor->visitList($this->node);
        case \ast\AST_MAGIC_CONST:
            return $visitor->visitMagicConst($this->node);
        case \ast\AST_METHOD:
            return $visitor->visitMethod($this->node);
        case \ast\AST_METHOD_CALL:
            return $visitor->visitMethodCall($this->node);
        case \ast\AST_NAME:
            return $visitor->visitName($this->node);
        case \ast\AST_NAMESPACE:
            return $visitor->visitNamespace($this->node);
        case \ast\AST_NEW:
            return $visitor->visitNew($this->node);
        case \ast\AST_PARAM:
            return $visitor->visitParam($this->node);
        case \ast\AST_PARAM_LIST:
            return $visitor->visitParamList($this->node);
        case \ast\AST_PRE_INC:
            return $visitor->visitPreInc($this->node);
        case \ast\AST_PRINT:
            return $visitor->visitPrint($this->node);
        case \ast\AST_PROP:
            return $visitor->visitProp($this->node);
        case \ast\AST_PROP_DECL:
            return $visitor->visitPropDecl($this->node);
        case \ast\AST_PROP_ELEM:
            return $visitor->visitPropElem($this->node);
        case \ast\AST_RETURN:
            return $visitor->visitReturn($this->node);
        case \ast\AST_STATIC:
            return $visitor->visitStatic($this->node);
        case \ast\AST_STATIC_CALL:
            return $visitor->visitStaticCall($this->node);
        case \ast\AST_STATIC_PROP:
            return $visitor->visitStaticProp($this->node);
        case \ast\AST_STMT_LIST:
            return $visitor->visitStmtList($this->node);
        case \ast\AST_SWITCH:
            return $visitor->visitSwitch($this->node);
        case \ast\AST_SWITCH_CASE:
            return $visitor->visitSwitchCase($this->node);
        case \ast\AST_SWITCH_LIST:
            return $visitor->visitSwitchList($this->node);
        case \ast\AST_TYPE:
            return $visitor->visitType($this->node);
        case \ast\AST_UNARY_MINUS:
            return $visitor->visitUnaryMinus($this->node);
        case \ast\AST_UNARY_OP:
            return $visitor->visitUnaryOp($this->node);
        case \ast\AST_USE:
            return $visitor->visitUse($this->node);
        case \ast\AST_USE_ELEM:
            return $visitor->visitUseElem($this->node);
        case \ast\AST_USE_TRAIT:
            return $visitor->visitUseTrait($this->node);
        case \ast\AST_VAR:
            return $visitor->visitVar($this->node);
        case \ast\AST_WHILE:
            return $visitor->visitWhile($this->node);
        default:
            Debug::printNode($this->node);
            assert(false, 'All node kinds must match');
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @return null
     */
    public function acceptFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\ASSIGN_ADD:
            return $visitor->visitAssignAdd($this->node);
        case \ast\flags\ASSIGN_BITWISE_AND:
            return $visitor->visitAssignBitwiseAnd($this->node);
        case \ast\flags\ASSIGN_BITWISE_OR:
            return $visitor->visitAssignBitwiseOr($this->node);
        case \ast\flags\ASSIGN_BITWISE_XOR:
            return $visitor->visitAssign_bitwiseXor($this->node);
        case \ast\flags\ASSIGN_CONCAT:
            return $visitor->visitAssignConcat($this->node);
        case \ast\flags\ASSIGN_DIV:
            return $visitor->visitAssignDiv($this->node);
        case \ast\flags\ASSIGN_MOD:
            return $visitor->visitAssignMod($this->node);
        case \ast\flags\ASSIGN_MUL:
            return $visitor->visitAssignMul($this->node);
        case \ast\flags\ASSIGN_POW:
            return $visitor->visitAssignPow($this->node);
        case \ast\flags\ASSIGN_SHIFT_LEFT:
            return $visitor->visitAssignShiftLeft($this->node);
        case \ast\flags\ASSIGN_SHIFT_RIGHT:
            return $visitor->visitAssignShiftRight($this->node);
        case \ast\flags\ASSIGN_SUB:
            return $visitor->visitAssignSub($this->node);
        case \ast\flags\BINARY_ADD:
            return $visitor->visitBinaryAdd($this->node);
        case \ast\flags\BINARY_BITWISE_AND:
            return $visitor->visitBinaryBitwiseAnd($this->node);
        case \ast\flags\BINARY_BITWISE_OR:
            return $visitor->visitBinaryBitwiseOr($this->node);
        case \ast\flags\BINARY_BITWISE_XOR:
            return $visitor->visitBinaryBitwiseXor($this->node);
        case \ast\flags\BINARY_BOOL_XOR:
            return $visitor->visitBinaryBoolXor($this->node);
        case \ast\flags\BINARY_CONCAT:
            return $visitor->visitBinaryConcat($this->node);
        case \ast\flags\BINARY_DIV:
            return $visitor->visitBinaryDiv($this->node);
        case \ast\flags\BINARY_IS_EQUAL:
            return $visitor->visitBinaryIsEqual($this->node);
        case \ast\flags\BINARY_IS_IDENTICAL:
            return $visitor->visitBinaryIsIdentical($this->node);
        case \ast\flags\BINARY_IS_NOT_EQUAL:
            return $visitor->visitBinaryIsNotEqual($this->node);
        case \ast\flags\BINARY_IS_NOT_IDENTICAL:
            return $visitor->visitBinaryIsNotIdentical($this->node);
        case \ast\flags\BINARY_IS_SMALLER:
            return $visitor->visitBinaryIsSmaller($this->node);
        case \ast\flags\BINARY_IS_SMALLER_OR_EQUAL:
            return $visitor->visitBinaryIsSmallerOrEqual($this->node);
        case \ast\flags\BINARY_MOD:
            return $visitor->visitBinaryMod($this->node);
        case \ast\flags\BINARY_MUL:
            return $visitor->visitBinaryMul($this->node);
        case \ast\flags\BINARY_POW:
            return $visitor->visitBinaryPow($this->node);
        case \ast\flags\BINARY_SHIFT_LEFT:
            return $visitor->visitBinaryShiftLeft($this->node);
        case \ast\flags\BINARY_SHIFT_RIGHT:
            return $visitor->visitBinaryShiftRight($this->node);
        case \ast\flags\BINARY_SPACESHIP:
            return $visitor->visitBinarySpaceship($this->node);
        case \ast\flags\BINARY_SUB:
            return $visitor->visitBinarySub($this->node);
        case \ast\flags\CLASS_ABSTRACT:
            return $visitor->visitClassAbstract($this->node);
        case \ast\flags\CLASS_FINAL:
            return $visitor->visitClassFinal($this->node);
        case \ast\flags\CLASS_INTERFACE:
            return $visitor->visitClassInterface($this->node);
        case \ast\flags\CLASS_TRAIT:
            return $visitor->visitClassTrait($this->node);
        case \ast\flags\MODIFIER_ABSTRACT:
            return $visitor->visitModifierAbstract($this->node);
        case \ast\flags\MODIFIER_FINAL:
            return $visitor->visitModifierFinal($this->node);
        case \ast\flags\MODIFIER_PRIVATE:
            return $visitor->visitModifierPrivate($this->node);
        case \ast\flags\MODIFIER_PROTECTED:
            return $visitor->visitModifierProtected($this->node);
        case \ast\flags\MODIFIER_PUBLIC:
            return $visitor->visitModifierPublic($this->node);
        case \ast\flags\MODIFIER_STATIC:
            return $visitor->visitModifierStatic($this->node);
        case \ast\flags\NAME_FQ:
            return $visitor->visitNameFq($this->node);
        case \ast\flags\NAME_NOT_FQ:
            return $visitor->visitNameNotFq($this->node);
        case \ast\flags\NAME_RELATIVE:
            return $visitor->visitNameRelative($this->node);
        case \ast\flags\PARAM_REF:
            return $visitor->visitParamRef($this->node);
        case \ast\flags\PARAM_VARIADIC:
            return $visitor->visitParamVariadic($this->node);
        case \ast\flags\RETURNS_REF:
            return $visitor->visitReturnsRef($this->node);
        case \ast\flags\TYPE_ARRAY:
            return $visitor->visitTypeArray($this->node);
        case \ast\flags\TYPE_BOOL:
            return $visitor->visitTypeBool($this->node);
        case \ast\flags\TYPE_CALLABLE:
            return $visitor->visitTypeCallable($this->node);
        case \ast\flags\TYPE_DOUBLE:
            return $visitor->visitTypeDouble($this->node);
        case \ast\flags\TYPE_LONG:
            return $visitor->visitTypeLong($this->node);
        case \ast\flags\TYPE_NULL:
            return $visitor->visitTypeNull($this->node);
        case \ast\flags\TYPE_OBJECT:
            return $visitor->visitTypeObject($this->node);
        case \ast\flags\TYPE_STRING:
            return $visitor->visitTypeString($this->node);
        case \ast\flags\UNARY_BITWISE_NOT:
            return $visitor->visitUnaryBitwiseNot($this->node);
        case \ast\flags\UNARY_BOOL_NOT:
            return $visitor->visitUnaryBoolNot($this->node);
        default:
            Debug::printNode($this->node);
            assert(false, 'All flags must match');
            break;
        }
    }

}
