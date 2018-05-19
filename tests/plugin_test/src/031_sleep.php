<?php

/**
 * @property int $z
 * @phan-file-suppress PhanUnreferencedPrivateProperty
 * @phan-file-suppress PhanUnreferencedProtectedProperty
 * @phan-file-suppress PhanUnreferencedPublicProperty
 */
class A31 {
    private $_y;
    protected $_z;

    const PROPS_TO_SERIALIZE = ['_y', '_Z'];

    public function __sleep() {
        if (rand() % 2 > 0) {
            return ['_x', 'z', '_z', __CLASS__, 2, 42.1, new stdClass()];
        } else {
            return self::PROPS_TO_SERIALIZE;  // should warn
        }
    }
    public function __get($x) {
        return 'x';
    }
}

class B31 {
    public $_myProp;
    public $myOtherProp;
    public $unknownProp;

    public function __sleep() {
        $x = 'unknownProp';
        if (rand() % 2 > 0) {
            return 2;
        } elseif (rand() % 3 > 0) {
            return;
        } else {
            return ['x', '_myprop', 'myOtherProp', $x];
        }
    }
}

new A31();
new B31();
