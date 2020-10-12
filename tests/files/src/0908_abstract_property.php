<?php

namespace NA\NA\NA;

class Foo {
    /** @abstract should warn */
    protected static $X1 = '';

    /** @phan-abstract */
    public $x2 = '';

    /** @abstract should not warn */
    private $y = 123;
}

class Bar extends Foo {
    public $x2 = 'man';
}

class Bas extends Foo {
    protected static $X1 = 'soon';
}

abstract class AbstractBase extends Foo {
}

class Bat extends AbstractBase {
    /** @override */
    protected static $X1 = 'man';
    /** @override */
    protected static $notAnOverride;
    /**
     * @override
     * @suppress PhanCommentOverrideOnNonOverrideProperty
     */
    protected static $notAnOverride2;
}
