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
        $x = new myclass018();
        echo $x::class;
    }

    function test_function_mismatch(): void {
        \FixerTest018\MYFUNC018();
    }

    function test_method_mismatch(): void {
        $obj = new MyClass018();
        $obj->DoSomething();
    }

    function test_static_method_mismatch(): void {
        MyClass018::StaticMethod();
    }

    function test_namespace_mismatch(): void {
        $x = new \fixertest018\MyClass018();
        echo $x::class;
    }

    function test_catch_mismatch(): void {
        try {
            throw new \RuntimeException();
        } catch (\fixertest018\myclass018 $e) {
            echo $e::class;
        }
    }
}

// --- Use statement casing mismatches ---

namespace FixerTest018\UseStmts {
    use FixerTest018\myclass018;

    function test_use(): void {
        $x = new myclass018();
        echo $x::class;
    }
}

// --- Type hint casing mismatches ---

namespace FixerTest018\TypeHints {
    use FixerTest018\MyClass018;

    function test_param_type(myclass018 $x): void {
        echo $x::class;
    }

    function test_return_type(): myclass018 {
        return new MyClass018();
    }

    function test_nullable_param(?myclass018 $x): void {
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
