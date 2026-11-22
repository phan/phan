<?php

// A property that the doc comment of its class declares with @property and that the class also declares:
// the property from the doc comment takes precedence, and the custom annotations of the declaration in the class
// are recorded for it (the plugin is called with it, and its getCustomAnnotations() returns them).

/**
 * @property int $magic_and_real
 * @property int $magic_and_promoted
 * @property int $magic_only
 */
class PhpDocAndReal
{
    /** @phan-x-test-mark on a declaration of a property that the class also declares with @property */
    public $magic_and_real = 1;

    public function __construct(
        /** @phan-x-test-mark on a promoted property that the class also declares with @property */
        public int $magic_and_promoted = 0
    ) {
    }

    public function __get(string $name): int
    {
        return \strlen($name);
    }
}

class PhpDocAndRealChild extends PhpDocAndReal
{
}

// A property declared twice (PhanRedefineProperty): the second declaration is ignored, with its custom annotations.
class RedeclaredProperty
{
    /** @phan-x-test-mark on the first declaration */
    public $twice = 1;

    /** @phan-x-test-mark on the second declaration, which is ignored */
    public $twice = 2;
}

function use_phpdoc_properties(PhpDocAndReal $p, PhpDocAndRealChild $c, RedeclaredProperty $r): void
{
    echo $p->magic_and_real, $p->magic_and_promoted, $p->magic_only;
    echo $c->magic_and_real, $c->magic_and_promoted, $c->magic_only;
    echo $r->twice;
}
