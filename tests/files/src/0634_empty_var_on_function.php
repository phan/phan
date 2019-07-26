<?php

namespace EmptyVar;

/** @var int unrelated */
function test() {
}

class C {
    /** @phan-var int unrelated */
    public static function test() {
    }
}
