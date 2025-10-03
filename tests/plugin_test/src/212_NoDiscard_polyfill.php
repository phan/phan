<?php
// Polyfill the PHP 8.5 support for NoDiscard <https://wiki.php.net/rfc/marking_return_value_as_important>

function withoutSideEffects(): int {
    return 1;
}

class ClassWithoutSideEffects {
    public function instanceMethod(): int {
        return 2;
    }

    public static function staticMethod(): int {
        return 3;
    }
}

#[\NoDiscard]
function withSideEffects(): int {
    echo __FUNCTION__ . "\n";
    return 1;
}

class ClassWithSideEffects {
    #[\NoDiscard]
    public function instanceMethod(): int {
        echo __METHOD__ . "\n";
        return 2;
    }

    #[\NoDiscard]
    public static function staticMethod(): int {
        echo __METHOD__ . "\n";
        return 3;
    }
}

withoutSideEffects();
$o = new ClassWithoutSideEffects();
$o->instanceMethod();
ClassWithoutSideEffects::staticMethod();

withSideEffects();
$o = new ClassWithSideEffects();
$o->instanceMethod();
ClassWithSideEffects::staticMethod();
