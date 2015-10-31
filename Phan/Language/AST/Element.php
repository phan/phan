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
            return $visitor->visitClassConstDec($this->node);
        case \ast\AST_CLOSURE:
            return $visitor->visitClosure($this->node);
        case \ast\AST_CLOSURE_USES:
            return $visitor->visitClosureUses($this->node);
        case \ast\AST_CLOSURE_VAR:
            return $visitor->visitClosureVar($this->node);
        case \ast\AST_CONST:
            return $visitor->visitConst($this->node);
        case \ast\AST_DIM:
            return $visitor->visitDim($this->node);
        case \ast\AST_DO_WHILE:
            return $visitor->visitDoWhile($this->node);
        case \ast\AST_ECHO:
            return $visitor->visitEcho($this->node);
        case \ast\AST_ENCAPS_LIST:
            return $visitor->visitEncapsList($this->node);
        case \ast\AST_EXPR_LIST:
            return $visitor->visitExprList($this->node);
        case \ast\AST_FOREACH:
            return $visitor->visitForeach($this->node);
        case \ast\AST_FUNC_DECL:
            return $visitor->visitFuncDecl($this->node);
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
        case \ast\AST_TYPE:
            return $visitor->visitType($this->node);
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
        switch ($$this->node->flag) {
        case \ast\flags\ASSIGN_ADD:
            return $visitor->visitAssign_add($this->node);
        case \ast\flags\ASSIGN_BITWISE_AND:
            return $visitor->visitAssign_bitwise_and($this->node);
        case \ast\flags\ASSIGN_BITWISE_OR:
            return $visitor->visitAssign_bitwise_or($this->node);
        case \ast\flags\ASSIGN_BITWISE_XOR:
            return $visitor->visitAssign_bitwise_xor($this->node);
        case \ast\flags\ASSIGN_CONCAT:
            return $visitor->visitAssign_concat($this->node);
        case \ast\flags\ASSIGN_DIV:
            return $visitor->visitAssign_div($this->node);
        case \ast\flags\ASSIGN_MOD:
            return $visitor->visitAssign_mod($this->node);
        case \ast\flags\ASSIGN_MUL:
            return $visitor->visitAssign_mul($this->node);
        case \ast\flags\ASSIGN_POW:
            return $visitor->visitAssign_pow($this->node);
        case \ast\flags\ASSIGN_SHIFT_LEFT:
            return $visitor->visitAssign_shift_left($this->node);
        case \ast\flags\ASSIGN_SHIFT_RIGHT:
            return $visitor->visitAssign_shift_right($this->node);
        case \ast\flags\ASSIGN_SUB:
            return $visitor->visitAssign_sub($this->node);
        case \ast\flags\BINARY_ADD:
            return $visitor->visitBinary_add($this->node);
        case \ast\flags\BINARY_BITWISE_AND:
            return $visitor->visitBinary_bitwise_and($this->node);
        case \ast\flags\BINARY_BITWISE_OR:
            return $visitor->visitBinary_bitwise_or($this->node);
        case \ast\flags\BINARY_BITWISE_XOR:
            return $visitor->visitBinary_bitwise_xor($this->node);
        case \ast\flags\BINARY_BOOL_XOR:
            return $visitor->visitBinary_bool_xor($this->node);
        case \ast\flags\BINARY_CONCAT:
            return $visitor->visitBinary_concat($this->node);
        case \ast\flags\BINARY_DIV:
            return $visitor->visitBinary_div($this->node);
        case \ast\flags\BINARY_IS_EQUAL:
            return $visitor->visitBinary_is_equal($this->node);
        case \ast\flags\BINARY_IS_IDENTICAL:
            return $visitor->visitBinary_is_identical($this->node);
        case \ast\flags\BINARY_IS_NOT_EQUAL:
            return $visitor->visitBinary_is_not_equal($this->node);
        case \ast\flags\BINARY_IS_NOT_IDENTICAL:
            return $visitor->visitBinary_is_not_identical($this->node);
        case \ast\flags\BINARY_IS_SMALLER:
            return $visitor->visitBinary_is_smaller($this->node);
        case \ast\flags\BINARY_IS_SMALLER_OR_EQUAL:
            return $visitor->visitBinary_is_smaller_or_equal($this->node);
        case \ast\flags\BINARY_MOD:
            return $visitor->visitBinary_mod($this->node);
        case \ast\flags\BINARY_MUL:
            return $visitor->visitBinary_mul($this->node);
        case \ast\flags\BINARY_POW:
            return $visitor->visitBinary_pow($this->node);
        case \ast\flags\BINARY_SHIFT_LEFT:
            return $visitor->visitBinary_shift_left($this->node);
        case \ast\flags\BINARY_SHIFT_RIGHT:
            return $visitor->visitBinary_shift_right($this->node);
        case \ast\flags\BINARY_SPACESHIP:
            return $visitor->visitBinary_spaceship($this->node);
        case \ast\flags\BINARY_SUB:
            return $visitor->visitBinary_sub($this->node);
        case \ast\flags\CLASS_ABSTRACT:
            return $visitor->visitClass_abstract($this->node);
        case \ast\flags\CLASS_FINAL:
            return $visitor->visitClass_final($this->node);
        case \ast\flags\CLASS_INTERFACE:
            return $visitor->visitClass_interface($this->node);
        case \ast\flags\CLASS_TRAIT:
            return $visitor->visitClass_trait($this->node);
        case \ast\flags\MODIFIER_ABSTRACT:
            return $visitor->visitModifier_abstract($this->node);
        case \ast\flags\MODIFIER_FINAL:
            return $visitor->visitModifier_final($this->node);
        case \ast\flags\MODIFIER_PRIVATE:
            return $visitor->visitModifier_private($this->node);
        case \ast\flags\MODIFIER_PROTECTED:
            return $visitor->visitModifier_protected($this->node);
        case \ast\flags\MODIFIER_PUBLIC:
            return $visitor->visitModifier_public($this->node);
        case \ast\flags\MODIFIER_STATIC:
            return $visitor->visitModifier_static($this->node);
        case \ast\flags\NAME_FQ:
            return $visitor->visitName_fq($this->node);
        case \ast\flags\NAME_NOT_FQ:
            return $visitor->visitName_not_fq($this->node);
        case \ast\flags\NAME_RELATIVE:
            return $visitor->visitName_relative($this->node);
        case \ast\flags\PARAM_REF:
            return $visitor->visitParam_ref($this->node);
        case \ast\flags\PARAM_VARIADIC:
            return $visitor->visitParam_variadic($this->node);
        case \ast\flags\RETURNS_REF:
            return $visitor->visitReturns_ref($this->node);
        case \ast\flags\TYPE_ARRAY:
            return $visitor->visitType_array($this->node);
        case \ast\flags\TYPE_BOOL:
            return $visitor->visitType_bool($this->node);
        case \ast\flags\TYPE_CALLABLE:
            return $visitor->visitType_callable($this->node);
        case \ast\flags\TYPE_DOUBLE:
            return $visitor->visitType_double($this->node);
        case \ast\flags\TYPE_LONG:
            return $visitor->visitType_long($this->node);
        case \ast\flags\TYPE_NULL:
            return $visitor->visitType_null($this->node);
        case \ast\flags\TYPE_OBJECT:
            return $visitor->visitType_object($this->node);
        case \ast\flags\TYPE_STRING:
            return $visitor->visitType_string($this->node);
        case \ast\flags\UNARY_BITWISE_NOT:
            return $visitor->visitUnary_bitwise_not($this->node);
        case \ast\flags\UNARY_BOOL_NOT:
            return $visitor->visitUnary_bool_not($this->node);
        default:
            Debug::printNode($this->node);
            assert(false, 'All flags must match');
            break;
        }
    }

}
