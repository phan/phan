<?php declare(strict_types=1);
namespace Phan\Language\AST;

use \Phan\Debug;
use \ast\Node;

class Element {

    /**
     * @var Node
     */
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
        case \ast\AST_AND:
            return $visitor->visitAnd($this->node);
        case \ast\AST_CATCH_LIST:
            return $visitor->visitCatchList($this->node);
        case \ast\AST_CLONE:
            return $visitor->visitClone($this->node);
        case \ast\AST_CONDITIONAL:
            return $visitor->visitConditional($this->node);
        case \ast\AST_CONTINUE:
            return $visitor->visitContinue($this->node);
        case \ast\AST_FOR:
            return $visitor->visitFor($this->node);
        case \ast\AST_GOTO:
            return $visitor->visitGoto($this->node);
        case \ast\AST_HALT_COMPILER:
            return $visitor->visitHaltCompiler($this->node);
        case \ast\AST_INCLUDE_OR_EVAL:
            return $visitor->visitIncludeOrEval($this->node);
        case \ast\AST_LABEL:
            return $visitor->visitLabel($this->node);
        case \ast\AST_METHOD_REFERENCE:
            return $visitor->visitMethodReference($this->node);
        case \ast\AST_NAME_LIST:
            return $visitor->visitNameList($this->node);
        case \ast\AST_OR:
            return $visitor->visitOr($this->node);
        case \ast\AST_POST_DEC:
            return $visitor->visitPostDec($this->node);
        case \ast\AST_POST_INC:
            return $visitor->visitPostInc($this->node);
        case \ast\AST_PRE_DEC:
            return $visitor->visitPreDec($this->node);
        case \ast\AST_REF:
            return $visitor->visitRef($this->node);
        case \ast\AST_SHELL_EXEC:
            return $visitor->visitShellExec($this->node);
        case \ast\AST_SILENCE:
            return $visitor->visitSilence($this->node);
        case \ast\AST_THROW:
            return $visitor->visitThrow($this->node);
        case \ast\AST_TRAIT_ADAPTATIONS:
            return $visitor->visitTraitAdaptations($this->node);
        case \ast\AST_TRAIT_ALIAS:
            return $visitor->visitTraitAlias($this->node);
        case \ast\AST_TRAIT_PRECEDENCE:
            return $visitor->visitTraitPrecedence($this->node);
        case \ast\AST_TRY:
            return $visitor->visitTry($this->node);
        case \ast\AST_UNARY_PLUS:
            return $visitor->visitUnaryPlus($this->node);
        case \ast\AST_UNPACK:
            return $visitor->visitUnpack($this->node);
        case \ast\AST_UNSET:
            return $visitor->visitUnset($this->node);
        case \ast\AST_YIELD:
            return $visitor->visitYield($this->node);
        case \ast\AST_YIELD_FROM:
            return $visitor->visitYieldFrom($this->node);
        default:
            Debug::printNode($this->node);
            assert(false, 'All node kinds must match');
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptAssignFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\ASSIGN_ADD:
            return $visitor->visitAssignAdd($this->node);
        case \ast\flags\ASSIGN_BITWISE_AND:
            return $visitor->visitAssignBitwiseAnd($this->node);
        case \ast\flags\ASSIGN_BITWISE_OR:
            return $visitor->visitAssignBitwiseOr($this->node);
        case \ast\flags\ASSIGN_BITWISE_XOR:
            return $visitor->visitAssignBitwiseXor($this->node);
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
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptBinaryFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags ?? 0) {
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
        case \ast\flags\BINARY_BOOL_AND:
            return $visitor->visitBinaryBoolAnd($this->node);
        case \ast\flags\BINARY_BOOL_OR:
            return $visitor->visitBinaryBoolOr($this->node);
        case \ast\flags\BINARY_IS_GREATER:
            return $visitor->visitBinaryIsGreater($this->node);
        case \ast\flags\BINARY_IS_GREATER_OR_EQUAL:
            return $visitor->visitBinaryIsGreaterOrEqual($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptClassFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\CLASS_ABSTRACT:
            return $visitor->visitClassAbstract($this->node);
        case \ast\flags\CLASS_FINAL:
            return $visitor->visitClassFinal($this->node);
        case \ast\flags\CLASS_INTERFACE:
            return $visitor->visitClassInterface($this->node);
        case \ast\flags\CLASS_TRAIT:
            return $visitor->visitClassTrait($this->node);
        case \ast\flags\CLASS_ANONYMOUS:
            return $visitor->visitClassAnonymous($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptModifierFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
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
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptNameFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\NAME_FQ:
            return $visitor->visitNameFq($this->node);
        case \ast\flags\NAME_NOT_FQ:
            return $visitor->visitNameNotFq($this->node);
        case \ast\flags\NAME_RELATIVE:
            return $visitor->visitNameRelative($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptParamFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\PARAM_REF:
            return $visitor->visitParamRef($this->node);
        case \ast\flags\PARAM_VARIADIC:
            return $visitor->visitParamVariadic($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptTypeFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\TYPE_ARRAY:
            return $visitor->visitUnionTypeArray($this->node);
        case \ast\flags\TYPE_BOOL:
            return $visitor->visitUnionTypeBool($this->node);
        case \ast\flags\TYPE_CALLABLE:
            return $visitor->visitUnionTypeCallable($this->node);
        case \ast\flags\TYPE_DOUBLE:
            return $visitor->visitUnionTypeDouble($this->node);
        case \ast\flags\TYPE_LONG:
            return $visitor->visitUnionTypeLong($this->node);
        case \ast\flags\TYPE_NULL:
            return $visitor->visitUnionTypeNull($this->node);
        case \ast\flags\TYPE_OBJECT:
            return $visitor->visitUnionTypeObject($this->node);
        case \ast\flags\TYPE_STRING:
            return $visitor->visitUnionTypeString($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptUnaryFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\UNARY_BITWISE_NOT:
            return $visitor->visitUnaryBitwiseNot($this->node);
        case \ast\flags\UNARY_BOOL_NOT:
            return $visitor->visitUnaryBoolNot($this->node);
        case \ast\flags\UNARY_MINUS:
            return $visitor->visitUnaryMinus($this->node);
        case \ast\flags\UNARY_PLUS:
            return $visitor->visitUnaryPlus($this->node);
        case \ast\flags\UNARY_SILENCE:
            return $visitor->visitUnarySilence($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptExecFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\EXEC_EVAL:
            return $visitor->visitExecEval($this->node);
        case \ast\flags\EXEC_INCLUDE:
            return $visitor->visitExecInclude($this->node);
        case \ast\flags\EXEC_INCLUDE_ONCE:
            return $visitor->visitExecIncludeOnce($this->node);
        case \ast\flags\EXEC_REQUIRE:
            return $visitor->visitExecRequire($this->node);
        case \ast\flags\EXEC_REQUIRE_ONCE:
            return $visitor->visitExecRequireOnce($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptMagicFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\MAGIC_CLASS:
            return $visitor->visitMagicClass($this->node);
        case \ast\flags\MAGIC_DIR:
            return $visitor->visitMagicDir($this->node);
        case \ast\flags\MAGIC_FILE:
            return $visitor->visitMagicFile($this->node);
        case \ast\flags\MAGIC_FUNCTION:
            return $visitor->visitMagicFunction($this->node);
        case \ast\flags\MAGIC_LINE:
            return $visitor->visitMagicLine($this->node);
        case \ast\flags\MAGIC_METHOD:
            return $visitor->visitMagicMethod($this->node);
        case \ast\flags\MAGIC_NAMESPACE:
            return $visitor->visitMagicNamespace($this->node);
        case \ast\flags\MAGIC_TRAIT:
            return $visitor->visitMagicTrait($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptUseFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\USE_CONST:
            return $visitor->visitUseConst($this->node);
        case \ast\flags\USE_FUNCTION:
            return $visitor->visitUseFunction($this->node);
        case \ast\flags\USE_NORMAL:
            return $visitor->visitUseNormal($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptAnyFlagVisitor(FlagVisitor $visitor) {
        switch ($this->node->flags) {
        case \ast\flags\ASSIGN_ADD:
            return $visitor->visitAssignAdd($this->node);
        case \ast\flags\ASSIGN_BITWISE_AND:
            return $visitor->visitAssignBitwiseAnd($this->node);
        case \ast\flags\ASSIGN_BITWISE_OR:
            return $visitor->visitAssignBitwiseOr($this->node);
        case \ast\flags\ASSIGN_BITWISE_XOR:
            return $visitor->visitAssignBitwiseXor($this->node);
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
            return $visitor->visitUnionTypeArray($this->node);
        case \ast\flags\TYPE_BOOL:
            return $visitor->visitUnionTypeBool($this->node);
        case \ast\flags\TYPE_CALLABLE:
            return $visitor->visitUnionTypeCallable($this->node);
        case \ast\flags\TYPE_DOUBLE:
            return $visitor->visitUnionTypeDouble($this->node);
        case \ast\flags\TYPE_LONG:
            return $visitor->visitUnionTypeLong($this->node);
        case \ast\flags\TYPE_NULL:
            return $visitor->visitUnionTypeNull($this->node);
        case \ast\flags\TYPE_OBJECT:
            return $visitor->visitUnionTypeObject($this->node);
        case \ast\flags\TYPE_STRING:
            return $visitor->visitUnionTypeString($this->node);
        case \ast\flags\UNARY_BITWISE_NOT:
            return $visitor->visitUnaryBitwiseNot($this->node);
        case \ast\flags\UNARY_BOOL_NOT:
            return $visitor->visitUnaryBoolNot($this->node);
        case \ast\flags\BINARY_BOOL_AND:
            return $visitor->visitBinaryBoolAnd($this->node);
        case \ast\flags\BINARY_BOOL_OR:
            return $visitor->visitBinaryBoolOr($this->node);
        case \ast\flags\BINARY_IS_GREATER:
            return $visitor->visitBinaryIsGreater($this->node);
        case \ast\flags\BINARY_IS_GREATER_OR_EQUAL:
            return $visitor->visitBinaryIsGreaterOrEqual($this->node);
        case \ast\flags\CLASS_ANONYMOUS:
            return $visitor->visitClassAnonymous($this->node);
        case \ast\flags\EXEC_EVAL:
            return $visitor->visitExecEval($this->node);
        case \ast\flags\EXEC_INCLUDE:
            return $visitor->visitExecInclude($this->node);
        case \ast\flags\EXEC_INCLUDE_ONCE:
            return $visitor->visitExecIncludeOnce($this->node);
        case \ast\flags\EXEC_REQUIRE:
            return $visitor->visitExecRequire($this->node);
        case \ast\flags\EXEC_REQUIRE_ONCE:
            return $visitor->visitExecRequireOnce($this->node);
        case \ast\flags\MAGIC_CLASS:
            return $visitor->visitMagicClass($this->node);
        case \ast\flags\MAGIC_DIR:
            return $visitor->visitMagicDir($this->node);
        case \ast\flags\MAGIC_FILE:
            return $visitor->visitMagicFile($this->node);
        case \ast\flags\MAGIC_FUNCTION:
            return $visitor->visitMagicFunction($this->node);
        case \ast\flags\MAGIC_LINE:
            return $visitor->visitMagicLine($this->node);
        case \ast\flags\MAGIC_METHOD:
            return $visitor->visitMagicMethod($this->node);
        case \ast\flags\MAGIC_NAMESPACE:
            return $visitor->visitMagicNamespace($this->node);
        case \ast\flags\MAGIC_TRAIT:
            return $visitor->visitMagicTrait($this->node);
        case \ast\flags\UNARY_MINUS:
            return $visitor->visitUnaryMinus($this->node);
        case \ast\flags\UNARY_PLUS:
            return $visitor->visitUnaryPlus($this->node);
        case \ast\flags\UNARY_SILENCE:
            return $visitor->visitUnarySilence($this->node);
        case \ast\flags\USE_CONST:
            return $visitor->visitUseConst($this->node);
        case \ast\flags\USE_FUNCTION:
            return $visitor->visitUseFunction($this->node);
        case \ast\flags\USE_NORMAL:
            return $visitor->visitUseNormal($this->node);
        default:
            assert(false,
                "All flags must match. Found "
                . Debug::astFlagDescription($this->node->flags ?? 0));
            break;
        }
    }

}
