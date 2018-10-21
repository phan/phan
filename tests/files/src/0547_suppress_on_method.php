<?php

/**
 * @template T
 */
class C{
    /**
     * @suppress PhanGenericConstructorTypes
     */
    public function __construct(int $x) {
    }
}
$c = new C(2);
