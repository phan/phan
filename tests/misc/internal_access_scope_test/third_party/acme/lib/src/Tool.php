<?php
namespace Acme\Lib;

class Tool {
    /** @internal */
    public static function secret(): int {
        return 1;
    }

    /** @internal */
    public const SECRET = 1;
}
