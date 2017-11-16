<?php

namespace NS;
class MyClass385 {
    const FIBONACCI_CB = 'NS\MyClass385::fibonacci';
    const FIBONACCI_NAME = 'fibonacci';

    /** @param string $n incorrect documentation and a terrible fibonacci implementation. */
    public static function fibonacci($n) {
        if ($n <= 1) {
            return 1;
        }
        $n = (int)$n;
        $left = call_user_func(__METHOD__, $n - 1);
        $right = call_user_func([__CLASS__, __FUNCTION__], $n - 2);
        return $left + $right;
    }

    /** @param string $n incorrect documentation and a terrible fibonacci implementation. */
    public static function fibonacci2($n) {
        if ($n <= 1) {
            return 1;
        }
        $n = (int)$n;
        $left = call_user_func(self::FIBONACCI_CB, $n - 1);
        $right = call_user_func([__CLASS__, self::FIBONACCI_NAME], $n - 2);
        return $left + $right;
    }
}
printf("fib(5) = %d\n", MyClass385::fibonacci(5));
printf("fib(5) = %d\n", MyClass385::fibonacci2(5));
