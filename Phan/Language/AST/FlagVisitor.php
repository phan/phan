<?php declare(strict_types=1);
namespace Phan\Language\AST;

use \ast\Node;

/**
 * A visitor of AST nodes based on the node's flag value
 */
interface FlagVisitor {

    /**
     * Visit a node with flag \ast\flags\BINARY_ADD
     */
    public function visitBinaryAdd(Node $node);


    /**
     * Visit a node with flag `\ast\flags\ASSIGN_ADD`
     */
    public function visitAssignAdd(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_BITWISE_AND`
     */
    public function visitAssignBitwiseAnd(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_BITWISE_OR`
     */
    public function visitAssignBitwiseOr(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_BITWISE_XOR`
     */
    public function visitAssignBitwiseXor(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_CONCAT`
     */
    public function visitAssignConcat(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_DIV`
     */
    public function visitAssignDiv(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_MOD`
     */
    public function visitAssignMod(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_MUL`
     */
    public function visitAssignMul(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_POW`
     */
    public function visitAssignPow(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_SHIFT_LEFT`
     */
    public function visitAssignShiftLeft(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_SHIFT_RIGHT`
     */
    public function visitAssignShiftRight(Node $node);

    /**
     * Visit a node with flag `\ast\flags\ASSIGN_SUB`
     */
    public function visitAssignSub(Node $node);

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
     * Visit a node with flag `\ast\flags\RETURNS_REF`
     */
    public function visitReturnsRef(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_ARRAY`
     */
    public function visitTypeArray(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_BOOL`
     */
    public function visitTypeBool(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_CALLABLE`
     */
    public function visitTypeCallable(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_DOUBLE`
     */
    public function visitTypeDouble(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_LONG`
     */
    public function visitTypeLong(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_NULL`
     */
    public function visitTypeNull(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_OBJECT`
     */
    public function visitTypeObject(Node $node);

    /**
     * Visit a node with flag `\ast\flags\TYPE_STRING`
     */
    public function visitTypeString(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARY_BITWISE_NOT`
     */
    public function visitUnaryBitwiseNot(Node $node);

    /**
     * Visit a node with flag `\ast\flags\UNARYbOOL_NOT`
     */
    public function visitUnaryBoolNot(Node $node);

}
