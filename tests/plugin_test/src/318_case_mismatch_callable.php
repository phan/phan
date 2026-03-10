<?php

// --- Declarations ---

namespace TestNs318 {
    class MyClass318 {
        public function doSomething(): void {}
        public static function staticMethod(): void {}
    }

    function myFunc318(): void {}

    // User-defined function that accepts a callable
    function applyCallback(callable $callback, mixed $value): mixed {
        return $callback($value);
    }
}

// --- String callable: function name mismatch ---

namespace TestNs318\StringFunc {
    function test_call_user_func(): void {
        // Function name mismatch in call_user_func
        call_user_func('TestNs318\MYFUNC318');  // should warn (function name)
    }

    function test_call_user_func_array(): void {
        // Function name mismatch in call_user_func_array
        call_user_func_array('TestNs318\MYFUNC318', []);  // should warn (function name)
    }

    function test_array_map(): void {
        // Function name mismatch in array_map
        array_map('TestNs318\MYFUNC318', [1, 2, 3]);  // should warn (function name)
    }

    function test_user_defined_callable(): void {
        // Function name mismatch via user-defined function accepting callable
        \TestNs318\applyCallback('TestNs318\MYFUNC318', 42);  // should warn (function name)
    }

    // Correct casing — no warnings
    function test_correct_function_name(): void {
        call_user_func('TestNs318\myFunc318');
    }
}

// --- String callable: namespace mismatch ---

namespace TestNs318\StringNs {
    function test_namespace_mismatch(): void {
        // Namespace casing mismatch in string callable
        call_user_func('testns318\myFunc318');  // should warn (namespace)
    }

    function test_namespace_and_function_mismatch(): void {
        // Both namespace and function name mismatch
        call_user_func('testns318\MYFUNC318');  // should warn (namespace + function name)
    }

    // Correct casing — no warnings
    function test_correct_namespace(): void {
        call_user_func('TestNs318\myFunc318');
    }
}

// --- String callable: static method ('Class::method') ---

namespace TestNs318\StringMethod {
    function test_method_mismatch(): void {
        // Method name mismatch in 'Class::method' format
        call_user_func('TestNs318\MyClass318::STATICMETHOD');  // should warn (method name)
    }

    function test_class_mismatch(): void {
        // Class name mismatch in 'Class::method' format
        call_user_func('TestNs318\myclass318::staticMethod');  // should warn (class name)
    }

    function test_both_mismatch(): void {
        // Both class and method name mismatch
        call_user_func('TestNs318\myclass318::STATICMETHOD');  // should warn (class + method)
    }

    function test_namespace_class_method_mismatch(): void {
        // All three: namespace, class, and method mismatch
        call_user_func('testns318\myclass318::STATICMETHOD');  // should warn (ns + class + method)
    }

    // Correct casing — no warnings
    function test_correct_static_method(): void {
        call_user_func('TestNs318\MyClass318::staticMethod');
    }
}

// --- Array callable: [$obj, 'method'] ---

namespace TestNs318\ArrayCallable {
    use TestNs318\MyClass318;

    function test_array_method_mismatch(): void {
        $obj = new MyClass318();
        // Method name mismatch in array callable
        call_user_func([$obj, 'DoSomething']);  // should warn (method name)
    }

    function test_array_static_mismatch(): void {
        // Method name mismatch in static array callable
        call_user_func([MyClass318::class, 'STATICMETHOD']);  // should warn (method name)
    }

    function test_array_string_class_mismatch(): void {
        // String class name with casing mismatch in array callable
        call_user_func(['TestNs318\myclass318', 'staticMethod']);  // should warn (class name)
    }

    function test_array_both_mismatch(): void {
        // Both class and method casing mismatch in array callable
        call_user_func(['TestNs318\myclass318', 'STATICMETHOD']);  // should warn (class + method)
    }

    function test_array_namespace_mismatch(): void {
        // Namespace casing mismatch in string class name in array callable
        call_user_func(['testns318\MyClass318', 'staticMethod']);  // should warn (namespace)
    }

    function test_array_all_mismatch(): void {
        // Namespace, class, and method casing all wrong in array callable
        call_user_func(['testns318\myclass318', 'STATICMETHOD']);  // should warn (ns + class + method)
    }

    function test_usort_mismatch(): void {
        $arr = [3, 1, 2];
        // Method name mismatch in usort with array callable
        usort($arr, [new MyClass318(), 'DoSomething']);  // should warn (method name)
    }

    // Correct casing — no warnings
    function test_correct_array_callable(): void {
        $obj = new MyClass318();
        call_user_func([$obj, 'doSomething']);
        call_user_func([MyClass318::class, 'staticMethod']);
    }
}

// --- Closures and first-class callables: should NOT warn ---

namespace TestNs318\NoWarn {
    use TestNs318\MyClass318;

    function test_closure_no_warn(): void {
        // Closure — no name to check
        array_map(function ($x) { return $x; }, [1, 2, 3]);
    }

    function test_arrow_fn_no_warn(): void {
        // Arrow function — no name to check
        array_map(fn($x) => $x, [1, 2, 3]);
    }

    function test_first_class_callable_no_warn(): void {
        // First-class callable — casing checked by visitCall, not here
        array_map(\TestNs318\myFunc318(...), [1, 2, 3]);
    }

    function test_correct_string_callable(): void {
        // Correct casing string callables — no warnings
        call_user_func('TestNs318\myFunc318');
        call_user_func('TestNs318\MyClass318::staticMethod');
        call_user_func([new MyClass318(), 'doSomething']);
    }
}

// --- self/static/parent: correct method casing should NOT warn ---

namespace TestNs318\SelfStatic {
    use TestNs318\MyClass318;

    class Child318 extends MyClass318 {
        public static function test(): void {
            // Correct method casing — no warnings
            call_user_func('self::staticMethod');
            call_user_func('static::staticMethod');
            call_user_func('parent::staticMethod');
        }

        public static function testMethodMismatch(): void {
            // Method casing mismatch via self/static/parent — should warn
            call_user_func('self::STATICMETHOD');  // should warn (method name)
            call_user_func('static::STATICMETHOD');  // should warn (method name)
            call_user_func('parent::STATICMETHOD');  // should warn (method name)
        }
    }
}

// --- Variable callable: resolved from constant variable ---

namespace TestNs318\Variable {
    function test_variable_callable(): void {
        $fn = 'TestNs318\MYFUNC318';
        // Phan resolves constant variables — should warn (function name)
        call_user_func($fn);
    }
}
