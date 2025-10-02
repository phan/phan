<?php
class Base8 {
    function test(mixed $x): mixed {
        if (!$x) {
            return 2;
        }
        return $x;
    }
}
class Subclass8 extends Base8 {
    function test($x): mixed {
        return $x;
    }
}
class SubSubclass8 extends Subclass8 {
    function test(mixed $x): mixed {
        if (!$x) {
            return null;  // should warn about not returning a value.
        }
        return $x;
    }
}
var_export((new SubSubclass8())->test(false));
