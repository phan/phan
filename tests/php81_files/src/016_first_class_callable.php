<?php

$s = strlen(...);
$s2 = $s(...);
'@phan-debug-var $s2';
var_dump($s2(new stdClass()));
$var = 'strlen';
var_dump($var(...)(new stdClass()));
$var(...);
var_dump(...);

// This is wrong, the closure is always truthy
if (strlen(...)) {
    echo "This is a closure\n";
}

class C16 {
    public static function staticMethod(int $arg): bool {}
    public function instanceMethod(stdClass $arg): C16 {}
}
// Test detection of incorrect types
echo spl_object_id(C16::instanceMethod(...)(1));
echo strlen(C16::staticMethod(...)(new stdClass()));
// Test detection of unused results
C16::staticMethod(...);
(new C16())->instanceMethod(...);
