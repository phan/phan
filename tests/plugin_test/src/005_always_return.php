<?php

function test5() : ?string {
    if (rand() % 2 > 0) {
        return 'rand';
    }
    // This is a bug which will cause a TypeError.
}

/**
 * @return ?string
 */
function soft_nullable_test5() {
    if (rand() % 2 > 0) {
        return 'rand';
    }
    // This might be a bug, but won't cause a TypeError.
}

/**
 * @return string
 */
function soft_non_nullable_test5() {
    if (rand() % 2 > 0) {
        return 'rand';
    }
    // This is a bug.
}

test5();
soft_nullable_test5();
soft_non_nullable_test5();




class C5 {

    public static function test() : ?string {
        if (rand() % 2 > 0) {
            return 'rand';
        }
        // This is a bug that will cause a TypeError.
    }

    /**
     * @return ?string
     */
    public function soft_nullable_test5() {
        if (rand() % 2 > 0) {
            return 'rand';
        }
        // This might be a bug, but won't cause a TypeError.
    }

    /**
     * @return string
     */
    public function soft_non_nullable_test5() {
        if (rand() % 2 > 0) {
            return 'rand';
        }
        // This is a bug.
    }
}
C5::test();
$c = new C5();
$c->soft_nullable_test5();
$c->soft_non_nullable_test5();
