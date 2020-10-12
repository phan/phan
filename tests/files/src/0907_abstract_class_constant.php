<?php

namespace NA\NA;

class Foo {
    /** @abstract should warn */
    const X1 = '';

    /** @phan-abstract */
    const X2 = '';

    /** @abstract should not warn */
    private const Y = 123;
}

class Bar extends Foo {
    const X2 = 'man';
}

class Bas extends Foo {
    const X1 = 'soon';
}

abstract class AbstractBase extends Foo {
}

class Bat extends AbstractBase {
    /** @override */
    const X1 = 'man';
}
