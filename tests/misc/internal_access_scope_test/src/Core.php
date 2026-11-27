<?php
namespace App;

class Core {
    /** @internal */
    public static function inner(): int {
        return 1;
    }

    /** @internal */
    public $state = 0;

    /** @internal */
    public const LIMIT = 10;
}

/** @internal */
class Hidden {
}
