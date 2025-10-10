<?php

// Test deprecated partially supported callables (PHP 8.2)

class CallableTest {
    public static function staticMethod(): void {
        echo "static";
    }
    public function instanceMethod(): void {
        echo "instance";
    }
}

class CallableChild extends CallableTest {
    public function testCallables(): void {
        // These should emit deprecation warnings
        call_user_func('self::staticMethod');
        call_user_func('static::staticMethod');
        call_user_func('parent::staticMethod');

        // Array notation with class constants - should NOT emit warnings
        call_user_func([self::class, 'staticMethod']);
        call_user_func([static::class, 'staticMethod']);
        call_user_func([parent::class, 'staticMethod']);
    }
}

// Note: In global scope, self:: and static:: are undeclared classes
// so they emit PhanUndeclaredClassInCallable instead
