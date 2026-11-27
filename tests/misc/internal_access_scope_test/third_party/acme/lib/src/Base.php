<?php
namespace Acme\Lib;

class Base {
    /** @internal */
    public function innerOfBase(): int {
        return 4;
    }

    public function open(): int {
        return 5;
    }
}
