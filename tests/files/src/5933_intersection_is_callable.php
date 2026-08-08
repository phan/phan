<?php
// Regression test for https://github.com/phan/phan/issues/5555
// is_callable()/!is_callable() narrowing of an intersection type used to crash with
// "ArgumentCountError: Too few arguments to function Phan\Language\Type::isCallable()"
// because IntersectionType::isCallable() called anyTypePartsMatchMethod() (0 args)
// instead of anyTypePartsMatchMethodWithArgs() (passes $code_base through).

/**
 * @template T
 */
class HandlerStack5933 {
    /**
     * @var (callable&T)|null
     */
    private $handler;

    /**
     * @param (callable&T)|null $handler
     */
    public function __construct(?callable $handler = null) {
        $this->handler = $handler;
    }

    public function resolve(): void {
        if (!\is_callable($this->handler)) {
            $handler = $this->handler; '@phan-debug-var $handler';
        } else {
            $handler = $this->handler; '@phan-debug-var $handler';
        }
    }
}

// Plain interface intersection with no templates involved - this crashed too,
// since the very first type part's isCallable() call would throw.
function test_intersection_is_callable5933(Countable&Traversable $x): void {
    if (is_callable($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
}

interface Invokable5933 {
    public function __invoke(): void;
}

// An intersection part that is genuinely callable (declares __invoke()),
// exercising the $code_base-dependent branch of Type::isCallable().
function test_intersection_invokable_is_callable5933(Countable&Invokable5933 $x): void {
    if (is_callable($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
}
