<?php

namespace NS160;

function is_even(array $values) {
    foreach ($values as $value) {
        if ($value % 2 === 0) {
            return true;
        }
    }
    // Should warn about useless branch
    return true;
}
var_export(is_even([2]));
class Test {
    public static function is_even(array $values) {
        foreach ($values as $value) {
            if ($value % 2 === 0) {
                return true;
            }
        }
        // Should warn about useless branch
        return true;
    }
}
Test::is_even([]);
/** @phan-pure required because instance methods can't be checked for purity */
class TestItem {
    /**
     * @var Group[]
     */
    private $xyz;
    /**
     * Depends on xyz
     * @internal
     */
    public function fn() : bool
    {
        $i = 0;
        foreach ($this->xyz as $k => $v) {
            if ($k !== $i++ || $v->predicate()) {
                return true;
            }
        }
        return true;
    }
}

class Group {
    public function predicate() {
        return false;
    }
}

function test_chain(string $x) : bool {
    if (strtolower($x) === 'true') {
        return true;
    }
    if (strtolower($x) === 'false') {
        return false;
    }
    return false;
}
var_export(test_chain('true'));

function test_chain2(string $x) : int {
    if (strtolower($x) === 'zero') {
        return 0;
    } elseif (strtolower($x) === '1') {
        return 1+1;
    } else {
        return 3-1;
    }
}
var_export(test_chain2('true'));

function test_chain3(string $x) : ?array {
    switch ($x) {
    case 'zero':
        return [];
    case 'one':
        return [PHP_VERSION];
    default:
        return [PHP_VERSION];
    }
}
var_export(test_chain3('true'));

$f2 = function ($arg) : bool {
    if (is_string($arg)) {
        return true;
    } elseif (is_int($arg)) {
        return false;
    } else {
        return false;
    }
};

$f2('testing');
$f3 = function ($arg) {
    if (is_string($arg)) {
        return true;
    } elseif (is_int($arg)) {
        return;
    } else {
        return Null;
    }
};
var_export($f3(2));

function test_chain4(string $x) : ?array {
    switch ($x) {
    case 'x':
    case 'zero':
        return [PHP_VERSION];
    case 'one':
        return [PHP_VERSION];
    default:
        return [PHP_VERSION];
    }
}
var_export(test_chain4('true'));
