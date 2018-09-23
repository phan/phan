<?php

class C98 {}

// Issue::UndeclaredClassCatch
try {} catch (Undef $exception) {}

// Issue::UndeclaredStaticMethod
C98::staticMethod();

// Issue::UndeclaredClassInstanceof
$v = null;
if ($v instanceof Undef) {}

// Issue::UndeclaredTypeParameter
function f(Undef $p) {}

// Issue::UndeclaredTypeProperty
class D98 { /** @var Undef */ public $p; }

// Issue::UndeclaredClassInherit
class E98 extends Undef {}

// Issue::ParentlessClass
class F98 { function f() { $v = parent::f(); } }

// Issue::UndeclaredProperty
$v = (new C98)->undef;

// TODO: Issue::?
(new C98)->undef = 'str';

// TODO: Issue::UndeclaredClassMethod
function g(Undef $v) { $v->f(); }

// Issue::TraitParentReference
trait T98 { function f() { return parent::f(); } }

// Issue::UndeclaredClassParent
class G98 { function f() { parent::f(); } }

// Issue::UndeclaredParentClass
class H98 extends Undef {}

// Issue::UndeclaredTypeReturnType
function j() : Undef {throw new RuntimeException('not implemented');}

// Issue::UndeclaredTypeReturnType
function k() : self {throw new RuntimeException('not implemented');}
