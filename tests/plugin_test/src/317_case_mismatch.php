<?php

// --- Declarations ---

namespace TestNs317 {
    class MyClass317 {
        public function doSomething(): void {}
        public static function staticMethod(): void {}
        public function __toString(): string { return ''; }
    }

    interface MyInterface317 {}

    trait MyTrait317 {}

    class MyException317 extends \RuntimeException {}

    function myFunc317(): void {}
}

// --- References with casing mismatches ---

namespace TestNs317\Refs {
    use TestNs317\MyClass317;
    use TestNs317\MyInterface317;

    function test_class_mismatch(): void {
        // Class name mismatch (unqualified, resolved via use)
        $x = new myclass317();  // should warn
        echo $x::class;
    }

    function test_interface_mismatch(): void {
        $x = null;
        // Interface name mismatch (unqualified, resolved via use)
        if ($x instanceof myinterface317) {  // should warn
            echo 'yes';
        }
    }

    function test_function_mismatch(): void {
        // Function name mismatch
        \TestNs317\MYFUNC317();  // should warn (function name)
    }

    function test_method_mismatch(): void {
        $obj = new MyClass317();
        // Method name mismatch
        $obj->DoSomething();  // should warn
    }

    function test_static_method_mismatch(): void {
        // Static method name mismatch
        MyClass317::StaticMethod();  // should warn
    }

    function test_catch_mismatch(): void {
        try {
            throw new \TestNs317\MyException317();
        } catch (\TestNs317\myexception317 $e) {  // should warn (class name)
            echo $e->getMessage();
        }
    }

    function test_namespace_mismatch_fq(): void {
        // Fully-qualified with namespace casing mismatch
        $x = new \testns317\MyClass317();  // should warn (namespace)
    }

    // --- No false positives ---

    function test_correct_casing(): void {
        // Correct casing — no warnings
        $x = new MyClass317();
        $x->doSomething();
        MyClass317::staticMethod();
        \TestNs317\myFunc317();
    }

    function test_magic_method(): void {
        // Magic methods — no warnings regardless of casing
        $obj = new MyClass317();
        echo (string) $obj;
    }

    function test_self_static_parent(): void {
        // self/static/parent — no warnings
        $x = new MyClass317();
    }
}

namespace TestNs317\TraitUser {
    // Trait name mismatch
    class UsesWrongCase317 {
        use \TestNs317\mytrait317;  // should warn (class name)
    }
}

// --- Use statement casing mismatches ---

namespace TestNs317\UseStmts {
    // Class name mismatch in use statement
    use TestNs317\myclass317;  // should warn (class name)
    // Namespace mismatch in use statement
    use testns317\MyInterface317;  // should warn (namespace)
    // Both namespace and class name mismatch
    use testns317\myexception317;  // should warn (namespace + class name)
    // Function name mismatch in use statement
    use function TestNs317\MYFUNC317;  // should warn (function name)
    // Correct casing — no warnings
    use TestNs317\MyTrait317;

    function test_use_stmt_mismatches(): void {
        $x = new myclass317();
        echo $x::class;
        if ($x instanceof MyInterface317) {
            echo 'yes';
        }
    }
}
