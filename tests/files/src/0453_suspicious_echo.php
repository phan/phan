<?php
// Suspicious echos:
echo null,
     STDOUT,
     false,
     true;
echo 2.5;  // fine.
echo 'x';  // fine.
echo 2;  // fine.
print(null);
print(STDERR);
print(false);
print(true);
print(2.5);
echo new stdClass();

class NonStringableClass {
}
class StringableClass {
    public function __toString() {
        return self::class;
    }
}
echo new StringableClass();
echo new NonStringableClass();

$v = ((rand(0,1) > 0) ? ['key' => 'value'] : false);
echo $v;

/** @return void */
function returns_void() {

}
echo returns_void();
