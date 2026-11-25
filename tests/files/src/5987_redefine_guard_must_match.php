<?php

// The guard has to check for the fully qualified name of the declaration
namespace NS5987\WrongName {
    if (!function_exists('helper')) {
        function helper(): void {
        }
    }
    function helper(): void {
    }
    if (!class_exists('Other\\Cls')) {
        class Cls {
        }
    }
    class Cls {
    }
}

// A guarded declaration does not hide a conflict between two other declarations
namespace NS5987\Three {
    if (!class_exists('NS5987\\Three\\Cls')) {
        class Cls {
        }
    }
    class Cls {
    }
    class Cls {
    }
    if (!function_exists('NS5987\\Three\\three')) {
        function three(): void {
        }
    }
    function three(): void {
    }
    function three(): void {
    }
}

// The guard says nothing about a second declaration in the same branch
namespace NS5987\Twice {
    if (!function_exists('NS5987\\Twice\\twice')) {
        function twice(): void {
        }
        function twice(): void {
        }
    }
}

// class_exists() does not find an interface
namespace NS5987\Kind {
    interface I {
    }
    if (!class_exists('NS5987\\Kind\\I')) {
        interface I {
        }
    }
    if (!interface_exists(J::class)) {
        interface J {
        }
    }
    interface J {
    }
}

// Only the global function_exists() is a guard
namespace NS5987\Callee {
    if (!namespace\function_exists('NS5987\\Callee\\notGuarded')) {
        function notGuarded(): void {
        }
    }
    function notGuarded(): void {
    }
    function function_exists(string $name): bool {
        return $name === '';
    }
}

// These are guarded
namespace NS5987\Ok {
    if (!\function_exists('NS5987\\Ok\\fine') && PHP_VERSION_ID > 80000) {
        function fine(): void {
        }
    }
    function fine(): void {
    }
    if (!class_exists(Good::class)) {
        class Good {
        }
    }
    class Good {
    }
    if (!enum_exists('\\NS5987\\Ok\\E')) {
        enum E {
        }
    }
    enum E {
    }
}
