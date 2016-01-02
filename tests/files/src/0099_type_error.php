<?php

// Issue::TypeMismatchProperty
function f(int $p = false) {}

// Issue::TypeArraySuspicious
$a = false; if($a[1]) {}

// Issue::TypeMismatchProperty
class C { /** @var int */ public $p; } (new C)->p = 'str';

// Issue::TypeInstantiateAbstract
abstract class D {} (new D);

// Issue::TypeInstantiateInterface
interface E {} (new E);

// Issue::TypeNonVarPassByRef
class F { static function f(&$v) {} } F::f('string');

// Issue::TypeMismatchReturn
class G { function f() : int { return 'string'; } }

// Issue::TypeMissingReturn
class H { function f() : int {} }

