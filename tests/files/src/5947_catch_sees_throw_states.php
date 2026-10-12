<?php

class User5947 {
    public function id(): int { return 1; }
}

function make_user5947(): User5947 {
    return new User5947();
}

// The catch block sees the state at each throw within a branch of the try block.
function catch_sees_branch_throw5947(?string $x): int {
    $user = null;
    try {
        if (isset($x)) {
            $user = make_user5947();
            if (!$user->id()) {
                throw new RuntimeException('x');
            }
            return 2;
        } else {
            throw new RuntimeException('y');
        }
    } catch (Exception) {
        '@phan-debug-var $user';
        return $user?->id() ?: 0;
    }
}

// A variable assigned only before a throw is possibly undefined in the catch block.
function catch_sees_possibly_undefined5947(bool $flag): string {
    try {
        if ($flag) {
            $message = 'flag was set';
            throw new RuntimeException($message);
        }
        return 'ok';
    } catch (RuntimeException) {
        '@phan-debug-var $message';
        return $message;
    }
}

// A throw in an inner try block is seen by the inner catch block.
// A throw in the inner catch block is seen by the outer catch block.
function nested_try5947(bool $a, bool $b): string {
    $state = 'start';
    try {
        try {
            if ($a) {
                $state = 'inner';
                throw new InvalidArgumentException('inner');
            }
        } catch (InvalidArgumentException $e) {
            '@phan-debug-var $state';
            if ($b) {
                $state = 'rethrown';
                throw new RuntimeException('outer', 0, $e);
            }
        }
        $state = 'done';
    } catch (RuntimeException) {
        '@phan-debug-var $state';
    }
    return $state;
}

// Throws within closures and arrow functions declared in the try block do not affect the catch block.
/** @return list<Closure> */
function closure_throw_does_not_leak5947(): array {
    $value = null;
    try {
        $f = function (bool $flag): void {
            if ($flag) {
                $value = 'closure';
                throw new RuntimeException($value);
            }
        };
        $g = fn(?string $value) => $value ?? throw new RuntimeException('arrow');
        $value = 'end';
        return [$f, $g];
    } catch (RuntimeException) {
        '@phan-debug-var $value';
        echo $value;
        return [];
    }
}

// The state at a throw expression is seen by the catch block.
function throw_expression5947(?string $y): string {
    try {
        $v = 'before';
        $w = $y ?? throw new InvalidArgumentException('missing');
        $v = 'after';
        return $w;
    } catch (InvalidArgumentException) {
        '@phan-debug-var $v, $w';
        return $v;
    }
}

// The finally block sees the end state of a catch block that falls through, which includes the state at the throw.
function finally_after_catch5947(bool $flag): void {
    $status = 'initial';
    try {
        if ($flag) {
            $status = 'failed';
            throw new RuntimeException('x');
        }
        $status = 'ok';
    } catch (RuntimeException) {
        '@phan-debug-var $status';
    } finally {
        '@phan-debug-var $status';
        echo $status;
    }
}

// A throw within a try block without catch clauses is seen by the catch blocks of the enclosing try statement.
// (The finally block itself does not see the state at the throw.)
function try_finally_inside_try_catch5947(bool $flag): void {
    $step = 0;
    try {
        try {
            if ($flag) {
                $step = 1;
                throw new RuntimeException('x');
            }
            $step = 2;
        } finally {
            '@phan-debug-var $step';
            echo $step;
        }
        $step = 3;
    } catch (RuntimeException) {
        '@phan-debug-var $step';
    }
    echo $step;
}

// Merging the catch blocks into the state after the try statement does not modify the state before the try statement,
// which the `else` branch still uses.
function merge_does_not_modify_other_branch5947(bool $c): void {
    $x = 1;
    if ($c) {
        try {
            if (rand() % 2) {
                $x = 'a';
                throw new RuntimeException('x');
            }
            echo intdiv(1, rand());
        } catch (RuntimeException) {
            '@phan-debug-var $x';
        }
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
    echo $x;
}

// An exception that the catch clauses of the inner try statement don't catch reaches the outer catch block.
function nested_not_caught_by_inner5947(bool $flag): ?User5947 {
    $user = null;
    try {
        try {
            if ($flag) {
                $user = make_user5947();
                throw new RuntimeException('not caught by the inner try statement');
            }
        } catch (InvalidArgumentException) {
            return null;
        }
        return $user;
    } catch (RuntimeException) {
        '@phan-debug-var $user';
        return $user;
    }
}

// An exception that the inner try statement catches reaches the outer catch block only through the end state of the inner catch block.
function nested_caught_by_inner5947(bool $flag): string {
    $state = 'start';
    try {
        try {
            if ($flag) {
                $state = 'thrown';
                throw new InvalidArgumentException('caught by the inner try statement');
            }
        } catch (InvalidArgumentException) {
            '@phan-debug-var $state';
            $state = 'handled';
        }
    } catch (Exception) {
        '@phan-debug-var $state';
    }
    return $state;
}

// A catch clause for a parent class or one of several classes definitely catches the exception.
function nested_caught_by_parent_class5947(bool $flag, bool $other): string {
    $state = 'start';
    try {
        try {
            if ($flag) {
                $state = 'runtime';
                throw new RuntimeException('caught by catch (Exception)');
            }
        } catch (Exception) {
            $state = 'handled';
        }
        try {
            if ($other) {
                $state = 'length';
                throw new LengthException('caught by catch (UnexpectedValueException|LogicException)');
            }
        } catch (UnexpectedValueException|LogicException) {
            $state = 'handled again';
        }
    } catch (Throwable) {
        '@phan-debug-var $state';
    }
    return $state;
}

// A thrown value of an unknown type may not be caught by the inner try statement.
function nested_unknown_thrown_type5947(bool $flag, $e): string {
    $state = 'start';
    try {
        try {
            if ($flag) {
                $state = 'thrown';
                throw $e;
            }
        } catch (InvalidArgumentException) {
            return 'handled';
        }
    } catch (Exception) {
        '@phan-debug-var $state';
    }
    return $state;
}
