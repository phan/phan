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
function f6($i) {}
f6();

// Issue::PhanParamTooFewInternal
strlen();

// Issue::PhanParamTooMany
function f7($i) {}
f7(1, 2);

// Issue::PhanParamTooManyInternal
strlen('str', 42);

// Issue::PhanParamTypeMismatch

// Issue::PhanRedefineClass
class C15 {}
class C15 {}

// Issue::PhanRedefineClassInternal
class DateTime {}

// Issue::PhanRedefineFunction
function f9() {}
function f9() {}

// Issue::PhanRedefineFunctionInternal
function strlen() {}

// Issue::PhanStaticCallToNonStatic
class C19 { function f() {} }
C19::f();

// Issue::PhanNonClassMethodCall
$v8 = null;
$v8->f();

// Issue::PhanTypeArrayOperator


// Issue::PhanTypeArraySuspicious
$v4 = false; if($v4[1]) {}

// Issue::PhanTypeComparisonFromArray
if ([1, 2] == 'string') {}

// Issue::PhanTypeComparisonToArray
if (42 == [1, 2]) {}

// Issue::PhanTypeConversionFromArray


// Issue::PhanTypeInstantiateAbstract
abstract class C6 {} (new C6);

// Issue::PhanTypeInstantiateInterface
interface C7 {} (new C7);

// Issue::PhanTypeInvalidLeftOperand


// Issue::PhanTypeInvalidRightOperand


// Issue::PhanTypeMismatchArgument
function f8(int $i) {}
f8('string');

// Issue::PhanTypeMismatchArgumentInternal
strlen(42);

// Issue::PhanTypeMismatchDefault


// Issue::PhanTypeMismatchForeach
foreach (null as $i) {}

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

// Issue::PhanParentlessClass
class C12 { function f() { $v = parent::f(); } }

// Issue::PhanTraitParentReference
trait T1 { function f() { return parent::f(); } }

// Issue::PhanUnanalyzable

// Issue::PhanUndeclaredClass


// Issue::PhanUndeclaredClassCatch
try {} catch (Undef $exception) {}

// Issue::PhanUndeclaredClassConstant
class C16 {}
$v7 = C16::C;

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
f10();

// Issue::PhanUndeclaredInterface
class C17 implements C18 {}


// Issue::PhanUndeclaredMethod


// Issue::PhanUndeclaredProperty
class C14 {}
$v6 = (new C14)->undef;

// Issue::PhanUndeclaredStaticMethod
class C21 {}
C21::f();

// Issue::PhanUndeclaredStaticProperty
class C22 {}
$v11 = C22::$p;

// Issue::PhanUndeclaredTrait
class C20 { use T2; }

// Issue::PhanUndeclaredVariable
$v9 = $v10;

// Issue::PhanVariableUseClause

