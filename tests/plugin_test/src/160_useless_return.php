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
