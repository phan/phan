<?php

class ASTRewritingCheck {
    /**
     * @param string $a
     */
    public static function bar($a) : int {
        if (!is_int($a)) {
            return strlen($a);
        }
        echo strlen($a);  // Emits issue: this is treated like it is within a block `if (is_int($a))`
        return $a;
    }

    public static function f1(string $y) : int {
        if ($x = strlen($y)) {
            return $x;
        }
        return $x;
    }

    public static function f2(string $y) : int {
        if (($a = strlen($y)) && $a > 5) {  // Does not emit undefined variable warning for $b, but does for $c
            $b = strlen($a);  // Emits issue: Passing an integer to strlen
            return $a + $b;
        }
        return -1;
    }
}
