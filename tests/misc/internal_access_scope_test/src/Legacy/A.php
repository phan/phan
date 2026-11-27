<?php
namespace Legacy\App\A;

// `Legacy\App` is a PSR-0 prefix of the project, without a backslash at its end.
class A {
    /** @internal */
    public static function innerOfLegacy(): int {
        return 5;
    }
}
