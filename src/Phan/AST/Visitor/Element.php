<?php

declare(strict_types=1);

namespace Phan\AST\Visitor;

use AssertionError;
use ast;
use ast\flags;
use ast\Node;
use Phan\AST\TolerantASTConverter\Shim;
use Phan\Debug;

Shim::load();

/**
 * This contains functionality needed by various visitor implementations
 * (visitors on Node->kind, Node->flags for a specific kind, etc)
 *
 * For performance, many callers manually inline the implementation of these methods
 */
class Element
{
    use \Phan\Profile;

    /**
     * @var Node The node which this Visitor will have $this->visit*() called on.
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
    // when php 7.2 is released and Phan supports php 7.2.
    // TODO: Also look into initializing mappings of ast\Node->kind to ReflectionMethod->getClosure for those methods,
    // it may be more efficient.
    // See https://github.com/php/php-src/pull/2427/files
    // This decreased the duration of running phan by about 4%
    public const VISIT_LOOKUP_TABLE = [
        ast\AST_ARG_LIST           => 'visitArgList',
        ast\AST_ARRAY              => 'visitArray',
        ast\AST_ARRAY_ELEM         => 'visitArrayElem',
        ast\AST_ARROW_FUNC         => 'visitArrowFunc',
        ast\AST_ASSIGN             => 'visitAssign',
        ast\AST_ASSIGN_OP          => 'visitAssignOp',
        ast\AST_ASSIGN_REF         => 'visitAssignRef',
        ast\AST_BINARY_OP          => 'visitBinaryOp',
        ast\AST_BREAK              => 'visitBreak',
        ast\AST_CALL               => 'visitCall',
        ast\AST_CAST               => 'visitCast',
        ast\AST_CATCH              => 'visitCatch',
        ast\AST_CLASS              => 'visitClass',
        ast\AST_CLASS_CONST        => 'visitClassConst',
        ast\AST_CLASS_CONST_DECL   => 'visitClassConstDecl',
        ast\AST_CLASS_NAME         => 'visitClassName',
        ast\AST_CLOSURE            => 'visitClosure',
        ast\AST_CLOSURE_USES       => 'visitClosureUses',
        ast\AST_CLOSURE_VAR        => 'visitClosureVar',
        ast\AST_CONST              => 'visitConst',
        ast\AST_CONST_DECL         => 'visitConstDecl',
        ast\AST_CONST_ELEM         => 'visitConstElem',
        ast\AST_DECLARE            => 'visitDeclare',
        ast\AST_DIM                => 'visitDim',
        ast\AST_DO_WHILE           => 'visitDoWhile',
        ast\AST_ECHO               => 'visitEcho',
        ast\AST_EMPTY              => 'visitEmpty',
        ast\AST_ENCAPS_LIST        => 'visitEncapsList',
        ast\AST_EXIT               => 'visitExit',
        ast\AST_EXPR_LIST          => 'visitExprList',
        ast\AST_FOREACH            => 'visitForeach',
        ast\AST_FUNC_DECL          => 'visitFuncDecl',
        ast\AST_ISSET              => 'visitIsset',
        ast\AST_GLOBAL             => 'visitGlobal',
        ast\AST_GROUP_USE          => 'visitGroupUse',
        ast\AST_IF                 => 'visitIf',
        ast\AST_IF_ELEM            => 'visitIfElem',
        ast\AST_INSTANCEOF         => 'visitInstanceof',
        ast\AST_MAGIC_CONST        => 'visitMagicConst',
        ast\AST_METHOD             => 'visitMethod',
        ast\AST_METHOD_CALL        => 'visitMethodCall',
        ast\AST_NAME               => 'visitName',
        ast\AST_NAMESPACE          => 'visitNamespace',
        ast\AST_NEW                => 'visitNew',
        ast\AST_PARAM              => 'visitParam',
        ast\AST_PARAM_LIST         => 'visitParamList',
        ast\AST_PRE_INC            => 'visitPreInc',
        ast\AST_PRINT              => 'visitPrint',
        ast\AST_PROP               => 'visitProp',
        ast\AST_PROP_DECL          => 'visitPropDecl',
        ast\AST_PROP_ELEM          => 'visitPropElem',
        ast\AST_PROP_GROUP         => 'visitPropGroup',
        ast\AST_RETURN             => 'visitReturn',
        ast\AST_STATIC             => 'visitStatic',
        ast\AST_STATIC_CALL        => 'visitStaticCall',
        ast\AST_STATIC_PROP        => 'visitStaticProp',
        ast\AST_STMT_LIST          => 'visitStmtList',
        ast\AST_SWITCH             => 'visitSwitch',
        ast\AST_SWITCH_CASE        => 'visitSwitchCase',
        ast\AST_SWITCH_LIST        => 'visitSwitchList',
        ast\AST_TYPE               => 'visitType',
        ast\AST_TYPE_UNION         => 'visitTypeUnion',
        ast\AST_NULLABLE_TYPE      => 'visitNullableType',
        ast\AST_UNARY_OP           => 'visitUnaryOp',
        ast\AST_USE                => 'visitUse',
        ast\AST_USE_ELEM           => 'visitUseElem',
        ast\AST_USE_TRAIT          => 'visitUseTrait',
        ast\AST_VAR                => 'visitVar',
        ast\AST_WHILE              => 'visitWhile',
        ast\AST_CATCH_LIST         => 'visitCatchList',
        ast\AST_CLONE              => 'visitClone',
        ast\AST_CONDITIONAL        => 'visitConditional',
        ast\AST_CONTINUE           => 'visitContinue',
        ast\AST_FOR                => 'visitFor',
        ast\AST_GOTO               => 'visitGoto',
        ast\AST_HALT_COMPILER      => 'visitHaltCompiler',
        ast\AST_INCLUDE_OR_EVAL    => 'visitIncludeOrEval',
        ast\AST_LABEL              => 'visitLabel',
        ast\AST_METHOD_REFERENCE   => 'visitMethodReference',
        ast\AST_NAME_LIST          => 'visitNameList',
        ast\AST_POST_DEC           => 'visitPostDec',
        ast\AST_POST_INC           => 'visitPostInc',
        ast\AST_PRE_DEC            => 'visitPreDec',
        ast\AST_REF                => 'visitRef',
        ast\AST_SHELL_EXEC         => 'visitShellExec',
        ast\AST_THROW              => 'visitThrow',
        ast\AST_TRAIT_ADAPTATIONS  => 'visitTraitAdaptations',
        ast\AST_TRAIT_ALIAS        => 'visitTraitAlias',
        ast\AST_TRAIT_PRECEDENCE   => 'visitTraitPrecedence',
        ast\AST_TRY                => 'visitTry',
        ast\AST_UNPACK             => 'visitUnpack',
        ast\AST_UNSET              => 'visitUnset',
        ast\AST_YIELD              => 'visitYield',
        ast\AST_YIELD_FROM         => 'visitYieldFrom',
    ];

    /**
     * Accepts a visitor that differentiates on the kind value
     * of the AST node.
     *
     * NOTE: This was turned into a static method for performance
     * because it was called extremely frequently.
     *
     * @return mixed - The type depends on the subclass of KindVisitor being used.
     * @suppress PhanUnreferencedPublicMethod Phan's code inlines this, but may be useful for some plugins
     */
    public static function acceptNodeAndKindVisitor(Node $node, KindVisitor $visitor)
    {
        $fn_name = self::VISIT_LOOKUP_TABLE[$node->kind] ?? null;
        if (\is_string($fn_name)) {
            return $visitor->{$fn_name}($node);
        } else {
            Debug::printNode($node);
            throw new AssertionError('All node kinds must match');
        }
    }

    public const VISIT_BINARY_LOOKUP_TABLE = [
        252 => 'visitBinaryConcat',  // ZEND_PARENTHESIZED_CONCAT is returned instead of ZEND_CONCAT in earlier php-ast versions in PHP 7.4. This is fixed in php-ast 1.0.2
        flags\BINARY_ADD => 'visitBinaryAdd',
        flags\BINARY_BITWISE_AND => 'visitBinaryBitwiseAnd',
        flags\BINARY_BITWISE_OR => 'visitBinaryBitwiseOr',
        flags\BINARY_BITWISE_XOR => 'visitBinaryBitwiseXor',
        flags\BINARY_BOOL_XOR => 'visitBinaryBoolXor',
        flags\BINARY_CONCAT => 'visitBinaryConcat',
        flags\BINARY_DIV => 'visitBinaryDiv',
        flags\BINARY_IS_EQUAL => 'visitBinaryIsEqual',
        flags\BINARY_IS_IDENTICAL => 'visitBinaryIsIdentical',
        flags\BINARY_IS_NOT_EQUAL => 'visitBinaryIsNotEqual',
        flags\BINARY_IS_NOT_IDENTICAL => 'visitBinaryIsNotIdentical',
        flags\BINARY_IS_SMALLER => 'visitBinaryIsSmaller',
        flags\BINARY_IS_SMALLER_OR_EQUAL => 'visitBinaryIsSmallerOrEqual',
        flags\BINARY_MOD => 'visitBinaryMod',
        flags\BINARY_MUL => 'visitBinaryMul',
        flags\BINARY_POW => 'visitBinaryPow',
        flags\BINARY_SHIFT_LEFT => 'visitBinaryShiftLeft',
        flags\BINARY_SHIFT_RIGHT => 'visitBinaryShiftRight',
        flags\BINARY_SPACESHIP => 'visitBinarySpaceship',
        flags\BINARY_SUB => 'visitBinarySub',
        flags\BINARY_BOOL_AND => 'visitBinaryBoolAnd',
        flags\BINARY_BOOL_OR => 'visitBinaryBoolOr',
        flags\BINARY_COALESCE => 'visitBinaryCoalesce',
        flags\BINARY_IS_GREATER => 'visitBinaryIsGreater',
        flags\BINARY_IS_GREATER_OR_EQUAL => 'visitBinaryIsGreaterOrEqual',
    ];

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_BINARY_OP.
     * @return mixed - The type depends on the subclass of FlagVisitor
     */
    public static function acceptBinaryFlagVisitor(Node $node, FlagVisitor $visitor)
    {
        $fn_name = self::VISIT_BINARY_LOOKUP_TABLE[$node->flags] ?? null;
        if (\is_string($fn_name)) {
            return $visitor->{$fn_name}($node);
        } else {
            Debug::printNode($node);
            throw new AssertionError("All flags must match. Found " . self::flagDescription($node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_CLASS.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptClassFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\CLASS_ABSTRACT:
                return $visitor->visitClassAbstract($this->node);
            case flags\CLASS_FINAL:
                return $visitor->visitClassFinal($this->node);
            case flags\CLASS_INTERFACE:
                return $visitor->visitClassInterface($this->node);
            case flags\CLASS_TRAIT:
                return $visitor->visitClassTrait($this->node);
            case flags\CLASS_ANONYMOUS:
                return $visitor->visitClassAnonymous($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_NAME.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptNameFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\NAME_FQ:
                return $visitor->visitNameFq($this->node);
            case flags\NAME_NOT_FQ:
                return $visitor->visitNameNotFq($this->node);
            case flags\NAME_RELATIVE:
                return $visitor->visitNameRelative($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_TYPE.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptTypeFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\TYPE_ARRAY:
                return $visitor->visitUnionTypeArray($this->node);
            case flags\TYPE_BOOL:
                return $visitor->visitUnionTypeBool($this->node);
            case flags\TYPE_CALLABLE:
                return $visitor->visitUnionTypeCallable($this->node);
            case flags\TYPE_DOUBLE:
                return $visitor->visitUnionTypeDouble($this->node);
            case flags\TYPE_LONG:
                return $visitor->visitUnionTypeLong($this->node);
            case flags\TYPE_NULL:
                return $visitor->visitUnionTypeNull($this->node);
            case flags\TYPE_OBJECT:
                return $visitor->visitUnionTypeObject($this->node);
            case flags\TYPE_STRING:
                return $visitor->visitUnionTypeString($this->node);
            case flags\TYPE_FALSE:
                return $visitor->visitUnionTypeFalse($this->node);
            case flags\TYPE_STATIC:
                return $visitor->visitUnionTypeStatic($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of type ast\AST_UNARY_OP.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptUnaryFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\UNARY_BITWISE_NOT:
                return $visitor->visitUnaryBitwiseNot($this->node);
            case flags\UNARY_BOOL_NOT:
                return $visitor->visitUnaryBoolNot($this->node);
            case flags\UNARY_SILENCE:
                return $visitor->visitUnarySilence($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_INCLUDE_OR_EVAL.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptExecFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\EXEC_EVAL:
                return $visitor->visitExecEval($this->node);
            case flags\EXEC_INCLUDE:
                return $visitor->visitExecInclude($this->node);
            case flags\EXEC_INCLUDE_ONCE:
                return $visitor->visitExecIncludeOnce($this->node);
            case flags\EXEC_REQUIRE:
                return $visitor->visitExecRequire($this->node);
            case flags\EXEC_REQUIRE_ONCE:
                return $visitor->visitExecRequireOnce($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_MAGIC_CONST.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptMagicFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\MAGIC_CLASS:
                return $visitor->visitMagicClass($this->node);
            case flags\MAGIC_DIR:
                return $visitor->visitMagicDir($this->node);
            case flags\MAGIC_FILE:
                return $visitor->visitMagicFile($this->node);
            case flags\MAGIC_FUNCTION:
                return $visitor->visitMagicFunction($this->node);
            case flags\MAGIC_LINE:
                return $visitor->visitMagicLine($this->node);
            case flags\MAGIC_METHOD:
                return $visitor->visitMagicMethod($this->node);
            case flags\MAGIC_NAMESPACE:
                return $visitor->visitMagicNamespace($this->node);
            case flags\MAGIC_TRAIT:
                return $visitor->visitMagicTrait($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Accepts a visitor that differentiates on the flag value
     * of the AST node of kind ast\AST_USE.
     *
     * @return mixed - The type depends on the subclass of FlagVisitor
     * @suppress PhanUnreferencedPublicMethod
     */
    public function acceptUseFlagVisitor(FlagVisitor $visitor)
    {
        switch ($this->node->flags) {
            case flags\USE_CONST:
                return $visitor->visitUseConst($this->node);
            case flags\USE_FUNCTION:
                return $visitor->visitUseFunction($this->node);
            case flags\USE_NORMAL:
                return $visitor->visitUseNormal($this->node);
            default:
                throw new AssertionError("All flags must match. Found " . self::flagDescription($this->node));
        }
    }

    /**
     * Helper method to get a tag describing the flags for a given Node kind.
     */
    public static function flagDescription(Node $node): string
    {
        return Debug::astFlagDescription($node->flags ?? 0, $node->kind);
    }
}
