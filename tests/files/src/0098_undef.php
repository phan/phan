<?php

class C {}

// Issue::UndeclaredClassCatch
try {} catch (Undef $exception) {}

// Issue::UndeclaredStaticMethod
C::staticMethod();

// Issue::UndeclaredClassInstanceof
$v = null;
if ($v instanceof Undef) {}

// Issue::UndeclaredTypeParameter
function f(Undef $p) {}

// Issue::UndeclaredTypeProperty
class D { /** @var Undef */ public $p; }

// Issue::UndeclaredClassInherit
class E extends Undef {}

// Issue::ParentlessClass
class F { function f() { $v = parent::f(); } }

// Issue::UndeclaredProperty
$v = (new C)->undef;

// TODO: Issue::?
(new C)->undef = 'str';

// TODO: Issue::UndeclaredClassMethod
function g(Undef $v) { $v->f(); }

// Issue::TraitParentReference
trait T { function f() { return parent::f(); } }

// Issue::UndeclaredClassParent
class G { function f() { parent::f(); } }

// Issue::UndeclaredParentClass
class H extends Undef {}

// Issue::UndeclaredTypeReturnType
function j() : Undef {throw new RuntimeException('not implemented');}

// Issue::UndeclaredTypeReturnType
function k() : self {throw new RuntimeException('not implemented');}
