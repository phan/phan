<?php
namespace NS869;
class Base {
    public function foo($x = true) { return $x; }
}
class Subclass extends Base {
    public function foo($x) { return $x; }  // issue message should mention $x = true
}
