<?php
// Phan is able to infer if a function is pure when the only calls are self-references.
function fibonacci145(int $i) {
    if ($i <= 1) {
        return 1;
    }
    return 'fibonacci145'($i - 1) + fibonacci145($i - 2);
}
// Should infer that the value should be used.
fibonacci145(5);
class X145 {
    public static function f(int $i) {
        if ($i <= 1) {
            return 1;
        }
        return self::f($i - 1) + X145::f($i - 2);
    }

    public function g(int $i) {
        if ($i <= 1) {
            return 1;
        }
        return $this->g($i - 1) + $this->g($i - 2);
    }
}
// Should also warn for static and instance methods that call themselves
X145::f(4);
X145::f(-1);
(new X145())->g(3);
