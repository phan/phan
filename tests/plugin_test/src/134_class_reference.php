<?php
namespace X\Y\Z;
class Foo{}
class Other{}

namespace A\B\C;
use X\Y\Z\Foo;
// Unconditionally treat Foo::class (ast\AST_CLASS_NAME) as a reference to that class,
// in the same way using a constant from that class (ast\AST_CLASS_CONST) would count as a reference.
$something = Foo::class;
