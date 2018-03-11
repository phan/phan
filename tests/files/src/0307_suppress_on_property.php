<?php

class Foo307 {
    /**
     * @var ArrayAccess
     */
    public $x = [];

    /**
     * @var ArrayAccess
     * @suppress PhanTypeMismatchProperty
     */
    public $y = [];

    /**
     * @var ArrayAccess
     */
    public $z = ['key' => 'value'];
}
