<?php

class Fatal5932
{
    public function neverReturns(): never {
        exit();
    }
}

class NotFatal5932
{
    public function neverReturns(): void {
    }
}

class Runner5932
{
    // Issue #5553: a branch calling a never-returning instance method should be treated as unreachable.
    public function method(?object $param1): void {
        if (!is_object($param1)) {
            $foo = new Fatal5932();
            $foo->neverReturns();
        }

        $this->requiresObject($param1);
    }

    // A never-returning method whose body ends in a call to another class's never-returning instance method.
    public function neverReturns(): never {
        $foo = new Fatal5932();
        $foo->neverReturns();
    }

    // The variable was reassigned to an instance whose method does return - should still warn about ?object.
    public function methodReassigned(?object $param1): void {
        if (!is_object($param1)) {
            $foo = new Fatal5932();
            $foo = new NotFatal5932();
            $foo->neverReturns();
        }

        $this->requiresObject($param1);
    }

    // The variable may be reassigned in a nested block - should still warn about ?object.
    public function methodConditionallyReassigned(?object $param1, bool $cond): void {
        if (!is_object($param1)) {
            $foo = new Fatal5932();
            if ($cond) {
                $foo = new NotFatal5932();
            }
            $foo->neverReturns();
        }

        $this->requiresObject($param1);
    }

    protected function requiresObject(object $param1): void {
        echo get_class($param1);
    }
}

$runner = new Runner5932();
$runner->method(new stdClass());
$runner->methodReassigned(new stdClass());
$runner->methodConditionallyReassigned(new stdClass(), true);
