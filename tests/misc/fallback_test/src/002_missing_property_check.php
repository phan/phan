<?php
class C3{ public $a = []; }
function missing_property(C3 $a) : int {
    $prop = ['x'];
    $prop = $a->;
    return $prop;  // should warn.
}
