<?php
class X { public readonly array $x; }
// Should emit PhanTypeModifyImmutableObjectProperty. At runtime this throws
// > Error: Cannot initialize readonly property X::$x from scope Y
class Y extends X { public function __construct() { $this->x = []; } }
$y = new Y();
$y->x[] = 123;
