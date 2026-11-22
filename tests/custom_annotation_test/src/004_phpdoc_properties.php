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

// The same with @suppress in the doc comment of the declaration: it applies to the issues that the plugins emit
// for the property from the doc comment of the class. (test.sh also runs UnusedSuppressionPlugin: it reports
// the unused suppression of the declaration with a custom annotation, and not that of the declaration without one,
// whose doc comment Phan ignores.)
/**
 * @property int $suppressed_real
 * @property int $suppressed_promoted
 * @property int $suppressed_without_annotation
 */
class SuppressedPhpDocAndReal
{
    /**
     * @suppress PhanPluginCustomAnnotationSeen
     * @suppress PhanUndeclaredClassMethod
     * @phan-x-test-mark on a declaration with a suppression, of a property that the class also declares with @property
     */
    public $suppressed_real = 1;

    /**
     * @suppress PhanUndeclaredClassMethod
     */
    public $suppressed_without_annotation = 2;

    public function __construct(
        /**
         * @suppress PhanPluginCustomAnnotationSeen
         * @phan-x-test-mark on a promoted property with a suppression, that the class also declares with @property
         */
        public int $suppressed_promoted = 0
    ) {
    }

    public function __get(string $name): int
    {
        return \strlen($name);
    }
}

class SuppressedPhpDocAndRealChild extends SuppressedPhpDocAndReal
{
}

function use_suppressed_phpdoc_properties(SuppressedPhpDocAndReal $p, SuppressedPhpDocAndRealChild $c): void
{
    echo $p->suppressed_real, $p->suppressed_promoted, $p->suppressed_without_annotation;
    echo $c->suppressed_real, $c->suppressed_promoted, $c->suppressed_without_annotation;
}
