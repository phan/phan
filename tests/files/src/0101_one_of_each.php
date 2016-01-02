<?php

// Issue::PhanAccessPropertyPrivate
class C1 { private static $p = 42; }
print C1::$p;

// Issue::PhanAccessPropertyProtected
class C2 { protected static $p = 42; }
print C2::$p;

// Issue::PhanCompatibleExpressionPHP7

// Issue::PhanCompatiblePHP7
$c->$m[0]();

// Issue::PhanContextNotObject
new parent;

// Issue::PhanDeprecatedFunction
/** @deprecated  */
function f1() {}
f1();

// Issue::PhanNoopArray
[1,2,3];

// Issue::PhanNoopClosure
function() {};

// Issue::PhanNoopConstant
class C3 { const C = 42; }
C3::C;

// Issue::PhanNoopProperty
class C4 {
    public $p;
    function f() { $this->p; }
}

// Issue::PhanNoopVariable
$a = 42;
$a;

// Issue::PhanNoopZeroReferences
class C5 {}

// Issue::PhanParamReqAfterOpt
function f2($p1 = null, $p2) {}

// Issue::PhanParamSpecial1


// Issue::PhanParamSpecial2


// Issue::PhanParamSpecial3


// Issue::PhanParamSpecial4


// Issue::PhanParamTooFew


// Issue::PhanParamTooFewInternal


// Issue::PhanParamTooMany


// Issue::PhanParamTooManyInternal


// Issue::PhanParamTypeMismatch


// Issue::PhanRedefineClass


// Issue::PhanRedefineClassInternal


// Issue::PhanRedefineFunction


// Issue::PhanRedefineFunctionInternal


// Issue::PhanStaticCallToNonStatic


// Issue::PhanNonClassMethodCall


// Issue::PhanTypeArrayOperator


// Issue::PhanTypeArraySuspicious


// Issue::PhanTypeComparisonFromArray


// Issue::PhanTypeComparisonToArray


// Issue::PhanTypeConversionFromArray


// Issue::PhanTypeInstantiateAbstract


// Issue::PhanTypeInstantiateInterface


// Issue::PhanTypeInvalidLeftOperand


// Issue::PhanTypeInvalidRightOperand


// Issue::PhanTypeMismatchArgument


// Issue::PhanTypeMismatchArgumentInternal


// Issue::PhanTypeMismatchDefault


// Issue::PhanTypeMismatchForeach


// Issue::PhanTypeMismatchProperty


// Issue::PhanTypeMismatchReturn


// Issue::PhanTypeMissingReturn


// Issue::PhanTypeNonVarPassByRef


// Issue::PhanTypeParentConstructorCalled


// Issue::PhanUndeclaredTypeParameter


// Issue::PhanUndeclaredTypeProperty


// Issue::PhanEmptyFile


// Issue::PhanParentlessClass


// Issue::PhanTraitParentReference


// Issue::PhanUnanalyzable


// Issue::PhanUndeclaredClass


// Issue::PhanUndeclaredClassCatch


// Issue::PhanUndeclaredClassConstant


// Issue::PhanUndeclaredClassInstanceof


// Issue::PhanUndeclaredClassMethod


// Issue::PhanUndeclaredClassReference


// Issue::PhanUndeclaredConstant


// Issue::PhanUndeclaredExtendedClass


// Issue::PhanUndeclaredFunction


// Issue::PhanUndeclaredInterface


// Issue::PhanUndeclaredMethod


// Issue::PhanUndeclaredProperty


// Issue::PhanUndeclaredStaticMethod


// Issue::PhanUndeclaredStaticProperty


// Issue::PhanUndeclaredTrait


// Issue::PhanUndeclaredVariable


// Issue::PhanVariableUseClause

