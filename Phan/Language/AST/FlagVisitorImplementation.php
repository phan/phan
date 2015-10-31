<?php declare(strict_types=1);
namespace Phan\Language\AST;

use \ast\Node;

/**
 * A visitor of AST nodes based on the node's flag value
 * which does nothing upon visiting a node
 */
abstract class FlagVisitorImplementation implements FlagVisitor {

    public function visit(Node $node) {}

    public function visitAssignAdd(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignBitwiseAnd(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignBitwiseOr(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignBitwiseXor(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignConcat(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignDiv(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignMod(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignMul(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignPow(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignShiftLeft(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignShiftRight(Node $node) {
        return $this->visit($node);
    }

    public function visitAssignSub(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryAdd(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryBitwiseAnd(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryBitwiseOr(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryBitwiseXor(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryBoolXor(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryConcat(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryDiv(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryIsEqual(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryIsIdentical(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryIsNotEqual(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryIsNotIdentical(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryIsSmaller(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryIsSmallerOrEqual(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryMod(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryMul(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryPow(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryShiftLeft(Node $node) {
        return $this->visit($node);
    }

    public function visitBinaryShiftRight(Node $node) {
        return $this->visit($node);
    }

    public function visitBinarySpaceship(Node $node) {
        return $this->visit($node);
    }

    public function visitBinarySub(Node $node) {
        return $this->visit($node);
    }

    public function visitClassAbstract(Node $node) {
        return $this->visit($node);
    }

    public function visitClassFinal(Node $node) {
        return $this->visit($node);
    }

    public function visitClassInterface(Node $node) {
        return $this->visit($node);
    }

    public function visitClassTrait(Node $node) {
        return $this->visit($node);
    }

    public function visitModifierAbstract(Node $node) {
        return $this->visit($node);
    }

    public function visitModifierFinal(Node $node) {
        return $this->visit($node);
    }

    public function visitModifierPrivate(Node $node) {
        return $this->visit($node);
    }

    public function visitModifierProtected(Node $node) {
        return $this->visit($node);
    }

    public function visitModifierPublic(Node $node) {
        return $this->visit($node);
    }

    public function visitModifierStatic(Node $node) {
        return $this->visit($node);
    }

    public function visitNameFq(Node $node) {
        return $this->visit($node);
    }

    public function visitNameNotFq(Node $node) {
        return $this->visit($node);
    }

    public function visitNameRelative(Node $node) {
        return $this->visit($node);
    }

    public function visitParamRef(Node $node) {
        return $this->visit($node);
    }

    public function visitParamVariadic(Node $node) {
        return $this->visit($node);
    }

    public function visitReturnsRef(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeArray(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeBool(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeCallable(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeDouble(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeLong(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeNull(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeObject(Node $node) {
        return $this->visit($node);
    }

    public function visitTypeString(Node $node) {
        return $this->visit($node);
    }

    public function visitUnaryBitwiseNot(Node $node) {
        return $this->visit($node);
    }

    public function visitUnaryBoolNot(Node $node) {
        return $this->visit($node);
    }

}
