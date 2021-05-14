<?php

declare(strict_types=1);

namespace Phan\AST\Visitor;

use ast\Node;

/**
 * A visitor of AST nodes based on the node's flag value
 * @phan-file-suppress PhanPluginUnknownMethodReturnType - TODO: Make this and FlagVisitorImplementation use Phan templates?
 */
interface FlagVisitor
{
    /**
     * Visit a node with flag `\ast\flags\BINARY_ADD`
     */
    public function visitBinaryAdd(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_BITWISE_AND`
     */
    public function visitBinaryBitwiseAnd(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_BITWISE_OR`
     */
    public function visitBinaryBitwiseOr(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_BITWISE_XOR`
     */
    public function visitBinaryBitwiseXor(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_BOOL_XOR`
     */
    public function visitBinaryBoolXor(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_CONCAT`
     */
    public function visitBinaryConcat(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_DIV`
     */
    public function visitBinaryDiv(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_EQUAL`
     */
    public function visitBinaryIsEqual(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_IDENTICAL`
     */
    public function visitBinaryIsIdentical(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_NOT_EQUAL`
     */
    public function visitBinaryIsNotEqual(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_NOT_IDENTICAL`
     */
    public function visitBinaryIsNotIdentical(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_SMALLER`
     */
    public function visitBinaryIsSmaller(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_SMALLER_OR_EQUAL`
     */
    public function visitBinaryIsSmallerOrEqual(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_MOD`
     */
    public function visitBinaryMod(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_MUL`
     */
    public function visitBinaryMul(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_POW`
     */
    public function visitBinaryPow(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_SHIFT_LEFT`
     */
    public function visitBinaryShiftLeft(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_SHIFT_RIGHT`
     */
    public function visitBinaryShiftRight(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_SPACESHIP`
     */
    public function visitBinarySpaceship(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_SUB`
     */
    public function visitBinarySub(Node $node);

    /**
     * Visit a node with flag `\ast\flags\CLASS_ABSTRACT`
     */
    public function visitClassAbstract(Node $node);

    /**
     * Visit a node with flag `\ast\flags\CLASS_ENUM`
     */
    public function visitClassEnum(Node $node);

    /**
     * Visit a node with flag `\ast\flags\CLASS_FINAL`
     */
    public function visitClassFinal(Node $node);

    /**
     * Visit a node with flag `\ast\flags\CLASS_INTERFACE`
     */
    public function visitClassInterface(Node $node);

    /**
     * Visit a node with flag `\ast\flags\CLASS_TRAIT`
     */
    public function visitClassTrait(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MODIFIER_ABSTRACT`
     */
    public function visitModifierAbstract(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MODIFIER_FINAL`
     */
    public function visitModifierFinal(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MODIFIER_PRIVATE`
     */
    public function visitModifierPrivate(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MODIFIER_PROTECTED`
     */
    public function visitModifierProtected(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MODIFIER_PUBLIC`
     */
    public function visitModifierPublic(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MODIFIER_STATIC`
     */
    public function visitModifierStatic(Node $node);

    /**
     * Visit a node with flag `\ast\flags\NAME_FQ`
     */
    public function visitNameFq(Node $node);

    /**
     * Visit a node with flag `\ast\flags\NAME_NOT_FQ`
     */
    public function visitNameNotFq(Node $node);

    /**
     * Visit a node with flag `\ast\flags\NAME_RELATIVE`
     */
    public function visitNameRelative(Node $node);

    /**
     * Visit a node with flag `\ast\flags\PARAM_REF`
     */
    public function visitParamRef(Node $node);

    /**
     * Visit a node with flag `\ast\flags\PARAM_VARIADIC`
     */
    public function visitParamVariadic(Node $node);

    /**
     * Visit a node with flag `\ast\flags\FUNC_RETURNS_REF`
     */
    public function visitReturnsRef(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_ARRAY`
     */
    public function visitUnionTypeArray(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_BOOL`
     */
    public function visitUnionTypeBool(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_CALLABLE`
     */
    public function visitUnionTypeCallable(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_DOUBLE`
     */
    public function visitUnionTypeDouble(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_LONG`
     */
    public function visitUnionTypeLong(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_NULL`
     */
    public function visitUnionTypeNull(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_STATIC`
     */
    public function visitUnionTypeStatic(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_FALSE`
     */
    public function visitUnionTypeFalse(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_OBJECT`
     */
    public function visitUnionTypeObject(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_STRING`
     */
    public function visitUnionTypeString(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARY_BITWISE_NOT`
     */
    public function visitUnaryBitwiseNot(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARY_BOOL_NOT`
     */
    public function visitUnaryBoolNot(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_BOOL_AND`
     */
    public function visitBinaryBoolAnd(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_BOOL_OR`
     */
    public function visitBinaryBoolOr(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_COALESCE`
     */
    public function visitBinaryCoalesce(Node $node);
    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_GREATER`
     */
    public function visitBinaryIsGreater(Node $node);

    /**
     * Visit a node with flag `\ast\flags\BINARY_IS_GREATER_OR_EQUAL`
     */
    public function visitBinaryIsGreaterOrEqual(Node $node);

    /**
     * Visit a node with flag `\ast\flags\CLASS_ANONYMOUS`
     */
    public function visitClassAnonymous(Node $node);

    /**
     * Visit a node with flag `\ast\flags\EXEC_EVAL`
     */
    public function visitExecEval(Node $node);

    /**
     * Visit a node with flag `\ast\flags\EXEC_INCLUDE`
     */
    public function visitExecInclude(Node $node);

    /**
     * Visit a node with flag `\ast\flags\EXEC_INCLUDE_ONCE`
     */
    public function visitExecIncludeOnce(Node $node);

    /**
     * Visit a node with flag `\ast\flags\EXEC_REQUIRE`
     */
    public function visitExecRequire(Node $node);

    /**
     * Visit a node with flag `\ast\flags\EXEC_REQUIRE_ONCE`
     */
    public function visitExecRequireOnce(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_CLASS`
     */
    public function visitMagicClass(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_DIR`
     */
    public function visitMagicDir(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_FILE`
     */
    public function visitMagicFile(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_FUNCTION`
     */
    public function visitMagicFunction(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_LINE`
     */
    public function visitMagicLine(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_METHOD`
     */
    public function visitMagicMethod(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_NAMESPACE`
     */
    public function visitMagicNamespace(Node $node);

    /**
     * Visit a node with flag `\ast\flags\MAGIC_TRAIT`
     */
    public function visitMagicTrait(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARY_MINUS`
     */
    public function visitUnaryMinus(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARY_PLUS`
     */
    public function visitUnaryPlus(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARY_SILENCE`
     */
    public function visitUnarySilence(Node $node);

    /**
     * Visit a node with flag `\ast\flags\USE_CONST`
     */
    public function visitUseConst(Node $node);

    /**
     * Visit a node with flag `\ast\flags\USE_FUNCTION`
     */
    public function visitUseFunction(Node $node);

    /**
     * Visit a node with flag `\ast\flags\USE_NORMAL`
     */
    public function visitUseNormal(Node $node);
}
