<?php

// --- Declarations ---

namespace FixerTest018 {
    class MyClass018 {
        public function doSomething(): void {}
        public static function staticMethod(): void {}
    }

    interface MyInterface018 {}

    function myFunc018(): void {}
}

// --- References with casing mismatches ---

namespace FixerTest018\Refs {
    use FixerTest018\MyClass018;

    function test_class_mismatch(): void {
        $x = new MyClass018();
        echo $x::class;
    }

    function test_function_mismatch(): void {
        \FixerTest018\myFunc018();
    }

    function test_method_mismatch(): void {
        $obj = new MyClass018();
        $obj->doSomething();
    }

    function test_static_method_mismatch(): void {
        MyClass018::staticMethod();
    }

    function test_namespace_mismatch(): void {
        $x = new \FixerTest018\MyClass018();
        echo $x::class;
    }

    function test_catch_mismatch(): void {
        try {
            throw new \RuntimeException();
        } catch (\FixerTest018\MyClass018 $e) {
            echo $e::class;
        }
    }
}

// --- Use statement casing mismatches ---

namespace FixerTest018\UseStmts {
    use FixerTest018\MyClass018;

    function test_use(): void {
        $x = new MyClass018();
        echo $x::class;
    }
}

// --- Type hint casing mismatches ---

namespace FixerTest018\TypeHints {
    use FixerTest018\MyClass018;

    function test_param_type(MyClass018 $x): void {
        echo $x::class;
    }

    function test_return_type(): MyClass018 {
        return new MyClass018();
    }

    function test_nullable_param(?MyClass018 $x): void {
        echo $x !== null ? $x::class : 'null';
    }
}

// --- Correct casing (no changes expected) ---

namespace FixerTest018\Correct {
    use FixerTest018\MyClass018;

    function test_correct(): void {
        $x = new MyClass018();
        $x->doSomething();
        MyClass018::staticMethod();
        \FixerTest018\myFunc018();
    }
}
