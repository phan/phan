<?php

// Issue::PhanAccessPropertyPrivate
class C1 { private static $p = 42; }
print C1::$p;

// Issue::PhanAccessPropertyProtected
class C2 { protected static $p = 42; }
print C2::$p;

// Issue::PhanCompatibleExpressionPHP7

// Issue::PhanCompatiblePHP7
$v1->$v2[0]();

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
$v3 = 42;
$v3;

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
$v4 = false; if($v4[1]) {}


// Issue::PhanTypeComparisonFromArray


// Issue::PhanTypeComparisonToArray


// Issue::PhanTypeConversionFromArray


// Issue::PhanTypeInstantiateAbstract
abstract class C6 {} (new C6);

// Issue::PhanTypeInstantiateInterface
interface C7 {} (new C7);

// Issue::PhanTypeInvalidLeftOperand


// Issue::PhanTypeInvalidRightOperand


// Issue::PhanTypeMismatchArgument


// Issue::PhanTypeMismatchArgumentInternal


// Issue::PhanTypeMismatchDefault


// Issue::PhanTypeMismatchForeach


// Issue::PhanTypeMismatchProperty
function f3(int $p = false) {}

// Issue::PhanTypeMismatchReturn
class C8 { function f() : int { return 'string'; } }

// Issue::PhanTypeMissingReturn
class C9 { function f() : int {} }

// Issue::PhanTypeNonVarPassByRef
class C10 { static function f(&$v) {} } F::f('string');

// Issue::PhanTypeParentConstructorCalled


// Issue::PhanUndeclaredTypeParameter
function f4(Undef $p) {}

// Issue::PhanUndeclaredTypeProperty
class C11 { /** @var Undef */ public $p; }

// Issue::PhanEmptyFile


// Issue::PhanParentlessClass
class C12 { function f() { $v = parent::f(); } }

// Issue::PhanTraitParentReference
trait T1 { function f() { return parent::f(); } }

// Issue::PhanUnanalyzable


// Issue::PhanUndeclaredClass


// Issue::PhanUndeclaredClassCatch


// Issue::PhanUndeclaredClassConstant


// Issue::PhanUndeclaredClassInstanceof
$v5 = null;
if ($v5 instanceof Undef) {}

// Issue::PhanUndeclaredClassMethod
function f5(Undef $p) { $p->f5(); }

// Issue::PhanUndeclaredClassReference


// Issue::PhanUndeclaredConstant


// Issue::PhanUndeclaredExtendedClass
class C13 extends Undef {}

// Issue::PhanUndeclaredFunction


// Issue::PhanUndeclaredInterface


// Issue::PhanUndeclaredMethod


// Issue::PhanUndeclaredProperty
class C14 {}
$v6 = (new C14)->undef;

// Issue::PhanUndeclaredStaticMethod


// Issue::PhanUndeclaredStaticProperty


// Issue::PhanUndeclaredTrait


// Issue::PhanUndeclaredVariable


// Issue::PhanVariableUseClause

