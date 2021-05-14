<?php

declare(strict_types=1);

namespace Phan\AST\Visitor;

use ast\Node;

/**
 * A visitor of AST nodes based on the node's flag value
 * which does nothing upon visiting a node
 * @phan-file-suppress PhanPluginUnknownMethodReturnType - TODO: Make this and FlagVisitorImplementation use Phan templates?
 */
abstract class FlagVisitorImplementation implements FlagVisitor
{

    /**
     * This is called to analyze nodes in FlagVisitorImplementation subclasses
     * that don't define more specific `visit*()` methods for the Node's kind.
     */
    abstract public function visit(Node $node);

    public function visitBinaryAdd(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryBitwiseAnd(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryBitwiseOr(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryBitwiseXor(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryBoolXor(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryConcat(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryDiv(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsEqual(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsIdentical(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsNotEqual(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsNotIdentical(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsSmaller(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsSmallerOrEqual(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryMod(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryMul(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryPow(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryShiftLeft(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryShiftRight(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinarySpaceship(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinarySub(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryBoolAnd(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryBoolOr(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryCoalesce(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsGreater(Node $node)
    {
        return $this->visit($node);
    }

    public function visitBinaryIsGreaterOrEqual(Node $node)
    {
        return $this->visit($node);
    }

    public function visitClassAbstract(Node $node)
    {
        return $this->visit($node);
    }

    public function visitClassEnum(Node $node)
    {
        return $this->visit($node);
    }

    public function visitClassFinal(Node $node)
    {
        return $this->visit($node);
    }

    public function visitClassInterface(Node $node)
    {
        return $this->visit($node);
    }

    public function visitClassTrait(Node $node)
    {
        return $this->visit($node);
    }

    public function visitModifierAbstract(Node $node)
    {
        return $this->visit($node);
    }

    public function visitModifierFinal(Node $node)
    {
        return $this->visit($node);
    }

    public function visitModifierPrivate(Node $node)
    {
        return $this->visit($node);
    }

    public function visitModifierProtected(Node $node)
    {
        return $this->visit($node);
    }

    public function visitModifierPublic(Node $node)
    {
        return $this->visit($node);
    }

    public function visitModifierStatic(Node $node)
    {
        return $this->visit($node);
    }

    public function visitNameFq(Node $node)
    {
        return $this->visit($node);
    }

    public function visitNameNotFq(Node $node)
    {
        return $this->visit($node);
    }

    public function visitNameRelative(Node $node)
    {
        return $this->visit($node);
    }

    public function visitParamRef(Node $node)
    {
        return $this->visit($node);
    }

    public function visitParamVariadic(Node $node)
    {
        return $this->visit($node);
    }

    public function visitReturnsRef(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeArray(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeBool(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeCallable(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeDouble(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeLong(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeNull(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeFalse(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeStatic(Node $node)
    {
        return $this->visit($node);
    }


    public function visitUnionTypeObject(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnionTypeString(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnaryBitwiseNot(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUnaryBoolNot(Node $node)
    {
        return $this->visit($node);
    }

    public function visitClassAnonymous(Node $node)
    {
        return $this->visit($node);
    }

    public function visitExecEval(Node $node)
    {
        return $this->visit($node);
    }

    public function visitExecInclude(Node $node)
    {
        return $this->visit($node);
    }

    public function visitExecIncludeOnce(Node $node)
    {
        return $this->visit($node);
    }

    public function visitExecRequire(Node $node)
    {
        return $this->visit($node);
    }

    public function visitExecRequireOnce(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicClass(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicDir(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicFile(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicFunction(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicLine(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicMethod(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicNamespace(Node $node)
    {
        return $this->visit($node);
    }

    public function visitMagicTrait(Node $node)
    {
        return $this->visit($node);
    }

    /**
     * Visit a node with kind `ast\AST_UNARY_OP` and flags `ast\flags\UNARY_MINUS`
     */
    public function visitUnaryMinus(Node $node)
    {
        return $this->visit($node);
    }

    /**
     * Visit a node with kind `ast\AST_UNARY_OP` and flags `ast\flags\UNARY_PLUS`
     */
    public function visitUnaryPlus(Node $node)
    {
        return $this->visit($node);
    }

    /**
     * Visit a node with kind `ast\AST_UNARY_OP` and flags `ast\flags\UNARY_SILENCE`
     */
    public function visitUnarySilence(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUseConst(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUseFunction(Node $node)
    {
        return $this->visit($node);
    }

    public function visitUseNormal(Node $node)
    {
        return $this->visit($node);
    }
}
