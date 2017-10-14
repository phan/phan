<?php

trait T2 {
    public static function trait_main() {
        echo call_user_func('parent::foo', 4);
    }
}
class Parent370 {
    use T2;

    public static function foo(int $x) {
        return "Got $x\n";
    }

    public static function main() {
        echo call_user_func('self::foo', 2);
        echo call_user_func('STATIC::foo', 3);
        echo call_user_func('parent::foo', 4);
    }
}

class C370 extends Parent370 {
    public static function main() {
        // TODO: Also analyze call_user_func as a standalone statement.
        echo call_user_func('self::foo', 2);
        echo call_user_func('STATIC::foo', 3);
        echo call_user_func('parent::foo', 4);

        echo call_user_func(['self', 'foo'], 5);
        echo call_user_func(['static', 'foo'], 6);
        echo call_user_func(['parent', 'foo'], 7);

        echo call_user_func('\parent::foo', 2);  // incorrect, looks for class '\self'
        echo call_user_func('\static::foo', 2);  // incorrect, looks for class '\static'
        echo call_user_func('\self::foo', 2);  // incorrect, looks for class '\self'

        echo call_user_func('self::foo', []);
        echo call_user_func('static::foo', []);
        echo call_user_func('parent::foo', []);
    }
    public static function foo(int $x) {
        echo "C370 got " . $x . "\n";
    }
}
C370::main();
// invalid
echo call_user_func('self::foo', 2);
echo call_user_func('static::foo', 2);
echo call_user_func('parent::foo', 2);
$x = call_user_func('C370::foo', new stdClass());
call_user_func('C370::foo', new stdClass());
