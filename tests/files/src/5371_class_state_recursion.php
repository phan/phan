<?php

// Regression test for https://github.com/phan/phan/issues/5371

namespace NS5371;

class GuardedRecursion
{
    protected bool $x = false;

    public function foo(bool $x): int
    {
        if ($x === false) {
            $x = true;
            return $this->foo($x);
        }
        return rand(0, 1);
    }

    public function bar(): int
    {
        if ($this->x === false) {
            $this->x = true;
            return $this->bar();
        }
        return rand(0, 1);
    }
}

$test = new GuardedRecursion();
$test->foo(true);
$test->foo(false);
$test->bar();
