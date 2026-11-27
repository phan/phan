<?php
namespace Acme\Lib;

trait Parts {
    /** @internal */
    public $part = 1;

    /** @internal */
    protected function innerPart(): int {
        return 2;
    }
}
