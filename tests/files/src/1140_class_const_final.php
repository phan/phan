<?php

class C14 {
    final const X = 123;
    const Y = 123;
    public static function foo(): void {
        if (static::X !== 123) {
            echo "Impossible\n";
        }
        if (static::Y !== 123) {
            echo "Possible\n";
        }
    }
}
C14::foo();
