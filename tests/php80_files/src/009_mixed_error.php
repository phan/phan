<?php
class Base9 {
    public function test(): mixed {
        return;  // Fatal error: A function with return type must return a value
    }
}

class Base9B {
    public function test(): mixed {
        if (rand(0, 1)) {
            return 'literal';
        }
        return [];
    }

    /**
     * @return ?mixed
     */
    public function mixedParam(mixed $a) {
        return $a ?: null;
    }
}

class Other9B extends Base9B {
    public function test() {  // Cannot replace mixed return type with the absense of a type
        return 'literal';
    }

    public function mixedParam(int $a) {
        return $a;
    }
}
