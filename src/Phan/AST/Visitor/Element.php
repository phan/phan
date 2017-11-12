<?php declare(strict_types=1);
namespace Phan\AST\Visitor;

use Phan\Debug;
use ast\Node;

class Element
{
    use \Phan\Profile;

    /**
     * @var Node
     */
    private $node;

    /**
     * @param Node $node
     * Any AST node.
     */
    public function __construct(Node $node)
    {
        $this->node = $node;
    }

    // TODO: Revert this change back to the switch statement
    // when php 7.2 is released and phan supports php 7.2.
    // See https://github.com/php/php-src/pull/2427/files
    // This decreased the duration of running phan by about 4%
    const VISIT_LOOKUP_TABLE = [
        \ast\AST_ARG_LIST           => 'visitArgList',
        \ast\AST_ARRAY              => 'visitArray',
        \ast\AST_ARRAY_ELEM         => 'visitArrayElem',
        \ast\AST_ASSIGN             => 'visitAssign',
        \ast\AST_ASSIGN_OP          => 'visitAssignOp',
        \ast\AST_ASSIGN_REF         => 'visitAssignRef',
        \ast\AST_BINARY_OP          => 'visitBinaryOp',
        \ast\AST_BREAK              => 'visitBreak',
        \ast\AST_CALL               => 'visitCall',
        \ast\AST_CAST               => 'visitCast',
        \ast\AST_CATCH              => 'visitCatch',
        \ast\AST_CLASS              => 'visitClass',
        \ast\AST_CLASS_CONST        => 'visitClassConst',
        \ast\AST_CLASS_CONST_DECL   => 'visitClassConstDecl',
        \ast\AST_CLOSURE            => 'visitClosure',
        \ast\AST_CLOSURE_USES       => 'visitClosureUses',
        \ast\AST_CLOSURE_VAR        => 'visitClosureVar',
        \ast\AST_COALESCE           => 'visitCoalesce',
        \ast\AST_CONST              => 'visitConst',
        \ast\AST_CONST_DECL         => 'visitConstDecl',
        \ast\AST_CONST_ELEM         => 'visitConstElem',
        \ast\AST_DECLARE            => 'visitDeclare',
        \ast\AST_DIM                => 'visitDim',
        \ast\AST_DO_WHILE           => 'visitDoWhile',
        \ast\AST_ECHO               => 'visitEcho',
        \ast\AST_EMPTY              => 'visitEmpty',
        \ast\AST_ENCAPS_LIST        => 'visitEncapsList',
        \ast\AST_EXIT               => 'visitExit',
        \ast\AST_EXPR_LIST          => 'visitExprList',
        \ast\AST_FOREACH            => 'visitForeach',
        \ast\AST_FUNC_DECL          => 'visitFuncDecl',
        \ast\AST_ISSET              => 'visitIsset',
        \ast\AST_GLOBAL             => 'visitGlobal',
        \ast\AST_GREATER            => 'visitGreater',
        \ast\AST_GREATER_EQUAL      => 'visitGreaterEqual',
        \ast\AST_GROUP_USE          => 'visitGroupUse',
        \ast\AST_IF                 => 'visitIf',
        \ast\AST_IF_ELEM            => 'visitIfElem',
        \ast\AST_INSTANCEOF         => 'visitInstanceof',
        \ast\AST_MAGIC_CONST        => 'visitMagicConst',
        \ast\AST_METHOD             => 'visitMethod',
        \ast\AST_METHOD_CALL        => 'visitMethodCall',
        \ast\AST_NAME               => 'visitName',
        \ast\AST_NAMESPACE          => 'visitNamespace',
        \ast\AST_NEW                => 'visitNew',
        \ast\AST_PARAM              => 'visitParam',
        \ast\AST_PARAM_LIST         => 'visitParamList',
        \ast\AST_PRE_INC            => 'visitPreInc',
        \ast\AST_PRINT              => 'visitPrint',
        \ast\AST_PROP               => 'visitProp',
        \ast\AST_PROP_DECL          => 'visitPropDecl',
        \ast\AST_PROP_ELEM          => 'visitPropElem',
        \ast\AST_RETURN             => 'visitReturn',
        \ast\AST_STATIC             => 'visitStatic',
        \ast\AST_STATIC_CALL        => 'visitStaticCall',
        \ast\AST_STATIC_PROP        => 'visitStaticProp',
        \ast\AST_STMT_LIST          => 'visitStmtList',
        \ast\AST_SWITCH             => 'visitSwitch',
        \ast\AST_SWITCH_CASE        => 'visitSwitchCase',
        \ast\AST_SWITCH_LIST        => 'visitSwitchList',
        \ast\AST_TYPE               => 'visitType',
        \ast\AST_NULLABLE_TYPE      => 'visitNullableType',
        \ast\AST_UNARY_MINUS        => 'visitUnaryMinus',
        \ast\AST_UNARY_OP           => 'visitUnaryOp',
        \ast\AST_USE                => 'visitUse',
        \ast\AST_USE_ELEM           => 'visitUseElem',
        \ast\AST_USE_TRAIT          => 'visitUseTrait',
        \ast\AST_VAR                => 'visitVar',
        \ast\AST_WHILE              => 'visitWhile',
        \ast\AST_AND                => 'visitAnd',
        \ast\AST_CATCH_LIST         => 'visitCatchList',
        \ast\AST_CLONE              => 'visitClone',
        \ast\AST_CONDITIONAL        => 'visitConditional',
        \ast\AST_CONTINUE           => 'visitContinue',
        \ast\AST_FOR                => 'visitFor',
        \ast\AST_GOTO               => 'visitGoto',
        \ast\AST_HALT_COMPILER      => 'visitHaltCompiler',
        \ast\AST_INCLUDE_OR_EVAL    => 'visitIncludeOrEval',
        \ast\AST_LABEL              => 'visitLabel',
        \ast\AST_METHOD_REFERENCE   => 'visitMethodReference',
        \ast\AST_NAME_LIST          => 'visitNameList',
        \ast\AST_OR                 => 'visitOr',
        \ast\AST_POST_DEC           => 'visitPostDec',
        \ast\AST_POST_INC           => 'visitPostInc',
        \ast\AST_PRE_DEC            => 'visitPreDec',
        \ast\AST_REF                => 'visitRef',
        \ast\AST_SHELL_EXEC         => 'visitShellExec',
        \ast\AST_SILENCE            => 'visitSilence',
        \ast\AST_THROW              => 'visitThrow',
        \ast\AST_TRAIT_ADAPTATIONS  => 'visitTraitAdaptations',
        \ast\AST_TRAIT_ALIAS        => 'visitTraitAlias',
        \ast\AST_TRAIT_PRECEDENCE   => 'visitTraitPrecedence',
        \ast\AST_TRY                => 'visitTry',
        \ast\AST_UNARY_PLUS         => 'visitUnaryPlus',
        \ast\AST_UNPACK             => 'visitUnpack',
        \ast\AST_UNSET              => 'visitUnset',
        \ast\AST_YIELD              => 'visitYield',
        \ast\AST_YIELD_FROM         => 'visitYieldFrom',
    ];

    /**
     * Accepts a visitor that differentiates on the kind value
     * of the AST node.
     *
     * NOTE: This was turned into a static method for performance
     * because it was called extremely frequently.
     */
    public static function acceptNodeAndKindVisitor(Node $node, KindVisitor $visitor)
    {
        $fn_name = self::VISIT_LOOKUP_TABLE[$node->kind] ?? null;
        if (\is_string($fn_name)) {
            return $visitor->{$fn_name}($node);
        } else {
            Debug::printNode($node);
            \assert(false, 'All node kinds must match');
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     */
    public function acceptBinaryFlagVisitor(FlagVisitor $visitor)
    {
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
            case \ast\flags\BINARY_COALESCE:
                return $visitor->visitBinaryCoalesce($this->node);
            case \ast\flags\BINARY_IS_GREATER:
                return $visitor->visitBinaryIsGreater($this->node);
            case \ast\flags\BINARY_IS_GREATER_OR_EQUAL:
                return $visitor->visitBinaryIsGreaterOrEqual($this->node);
            default:
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptClassFlagVisitor(FlagVisitor $visitor)
    {
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
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptNameFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case \ast\flags\NAME_FQ:
                return $visitor->visitNameFq($this->node);
            case \ast\flags\NAME_NOT_FQ:
                return $visitor->visitNameNotFq($this->node);
            case \ast\flags\NAME_RELATIVE:
                return $visitor->visitNameRelative($this->node);
            default:
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptParamFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case \ast\flags\PARAM_REF:
                return $visitor->visitParamRef($this->node);
            case \ast\flags\PARAM_VARIADIC:
                return $visitor->visitParamVariadic($this->node);
            default:
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptTypeFlagVisitor(FlagVisitor $visitor)
    {
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
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptUnaryFlagVisitor(FlagVisitor $visitor)
    {
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
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptExecFlagVisitor(FlagVisitor $visitor)
    {
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
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptMagicFlagVisitor(FlagVisitor $visitor)
    {
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
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptUseFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case \ast\flags\USE_CONST:
                return $visitor->visitUseConst($this->node);
            case \ast\flags\USE_FUNCTION:
                return $visitor->visitUseFunction($this->node);
            case \ast\flags\USE_NORMAL:
                return $visitor->visitUseNormal($this->node);
            default:
                \assert(
                    false,
                    "All flags must match. Found "
                    . self::flagDescription($this->node)
                );
                break;
        }
    }

    private static function flagDescription(Node $node) : string
    {
        return Debug::astFlagDescription($node->flags ?? 0, $node->kind);
    }
}
