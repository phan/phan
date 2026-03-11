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

    function test_magic_method_mismatch(): void {
        // Magic method with casing mismatch — should warn
        $obj = new MyClass317();
        $obj->__TOSTRING();  // should warn (method name)
    }

    function test_magic_method_correct(): void {
        // Magic method with correct casing — no warning
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

// --- Type hint casing mismatches ---

namespace TestNs317\TypeHints {
    use TestNs317\MyClass317;
    use TestNs317\MyInterface317;

    function test_param_type(myclass317 $x): void {  // should warn (param type)
        echo $x::class;
    }

    function test_return_type(): myclass317 {  // should warn (return type)
        return new MyClass317();
    }

    function test_nullable_param(?myclass317 $x): void {  // should warn (nullable param)
        echo $x !== null ? $x::class : 'null';
    }

    function test_union_type(myclass317|myinterface317 $x): void {  // should warn (both types)
        echo get_class($x);
    }

    class TypeHintProps317 {
        public myclass317 $prop;  // should warn (property type)
        public ?myclass317 $nullable_prop = null;  // should warn (nullable property)

        public function method_return(): myclass317 {  // should warn (method return)
            return $this->prop;
        }
    }

    // Correct casing — no warnings
    function test_correct_type_hints(MyClass317 $x): MyClass317 {
        return $x;
    }
}

// --- Class constant access ---

namespace TestNs317\ClassConst {
    use TestNs317\MyClass317;

    function test_class_const_mismatch(): void {
        // Class constant access with casing mismatch
        echo myclass317::class;  // should warn
    }

    // Correct casing — no warnings
    function test_class_const_correct(): void {
        echo MyClass317::class;
    }
}

// --- extends/implements casing ---

namespace TestNs317\Inheritance {
    use TestNs317\MyClass317;
    use TestNs317\MyInterface317;

    class ExtendsWrong317 extends myclass317 {}  // should warn (extends)

    class ImplementsWrong317 implements myinterface317 {}  // should warn (implements)

    // Correct casing — no warnings
    class ExtendsCorrect317 extends MyClass317 {}
    class ImplementsCorrect317 implements MyInterface317 {}
}

// --- Alias casing ---

namespace TestNs317\Alias {
    use TestNs317\MyClass317 as RenamedClass317;
    use function TestNs317\myFunc317 as renamedFunc317;

    function test_alias_mismatch(): void {
        $x = new renamedclass317();  // should warn (alias mismatch)
        echo $x::class;
    }

    function test_func_alias_mismatch(): void {
        RENAMEDFUNC317();  // should warn (function alias mismatch)
    }

    // Correct casing — no warnings
    function test_alias_correct(): void {
        $x = new RenamedClass317();
        echo $x::class;
        renamedFunc317();
    }
}

// --- Unqualified function call in namespace (no use statement) ---

namespace TestNs317 {
    function test_unqualified_func_in_ns(): void {
        // Unqualified call should resolve to \TestNs317\myFunc317 via namespace fallback
        MYFUNC317();  // should warn (function name mismatch)
    }

    // Correct casing — no warnings
    function test_unqualified_func_correct(): void {
        myFunc317();
    }
}

// --- Group use statement casing mismatches ---

namespace TestNs317\GroupUse {
    use TestNs317\{myclass317, MyInterface317};  // should warn (myclass317 class name)
    use TestNs317\{MyTrait317, myexception317};  // should warn (myexception317 class name)

    function test_group_use(): void {
        $x = new myclass317();
        echo $x::class;
    }
}

// --- Qualified NAME_NOT_FQ references with use alias prefix ---

namespace TestNs317\Sub {
    class SubClass317 {}
    function subFunc317(): void {}
}

namespace TestNs317\QualifiedRef {
    use TestNs317\Sub;

    // "sub" is the use alias (implicit: use TestNs317\Sub as Sub).
    // "sub\SubClass317" is a qualified NAME_NOT_FQ reference.
    // The first segment "sub" has wrong casing vs the alias "Sub".
    // The plugin should warn about the namespace segment.

    function test_qualified_class_alias_mismatch(): void {
        $x = new sub\SubClass317();  // should warn (namespace segment "sub" vs "Sub")
    }

    function test_qualified_func_alias_mismatch(): void {
        sub\subFunc317();  // should warn (namespace segment "sub" vs "Sub")
    }

    // Correct casing — no warnings
    function test_qualified_correct(): void {
        $x = new Sub\SubClass317();
        Sub\subFunc317();
    }
}

// --- Qualified NAME_NOT_FQ with explicit alias rename ---

namespace TestNs317\AliasPkg {
    class AliasPkgClass317 {}
    function aliasPkgFunc317(): void {}
}

namespace TestNs317\AliasPkgRef {
    use TestNs317\AliasPkg as Pkg317;

    function test_alias_segment_class_mismatch(): void {
        $x = new pkg317\AliasPkgClass317();  // should warn ("pkg317" vs alias "Pkg317")
    }

    function test_alias_segment_func_mismatch(): void {
        pkg317\aliasPkgFunc317();  // should warn ("pkg317" vs alias "Pkg317")
    }

    function test_alias_segment_correct(): void {
        $x = new Pkg317\AliasPkgClass317();  // no warning
        Pkg317\aliasPkgFunc317();  // no warning
    }
}

// --- NAME_RELATIVE (namespace\Foo) casing ---

namespace TestNs317\RelNs\Inner {
    class InnerRelClass317 {}
}

namespace TestNs317\RelNs {
    function test_relative_class_mismatch(): void {
        // Multi-segment NAME_RELATIVE: should resolve to \TestNs317\RelNs\Inner\InnerRelClass317
        $x = new namespace\Inner\innerrelclass317();  // should warn (class name mismatch)
    }

    function test_relative_class_correct(): void {
        $x = new namespace\Inner\InnerRelClass317();  // no warning
    }
}
