--TEST--
PHP Spec test generated from ./expressions/bitwise_shift_operators/bitwise_shift_negative.php
--FILE--
<?php

try {
    var_dump(1 >> -1);
} catch (ArithmeticError $e) {
    echo $e->getMessage(), "\n";
}
try {
    var_dump(1 << -1);
} catch (ArithmeticError $e) {
    echo $e->getMessage(), "\n";
}
--EXPECTF--
Bit shift by negative number
Bit shift by negative number
