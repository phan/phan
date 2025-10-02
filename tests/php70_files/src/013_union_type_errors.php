<?php
function test_param13(int|string $param) {
}
test_param13(null);

function test_return13() : int|string {
    return null;
}

class X {
    public static int|null $prop;

    public static function getInstance(): static {
        return new static();
    }
}
$cb = function (Countable|false|null|array $x) {};
$false = static function (false $x): null { var_export($x); };
// Tests of arrow functions are omitted because tolerant-php-parser requires php 7.4+ to parse them.
// $cb = fn(Countable|int $params) => count($params) + 1;

X::getInstance()->missingMethod();
X::$prop = 'invalid';
