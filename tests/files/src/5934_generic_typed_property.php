<?php
// Regression test for https://github.com/phan/phan/issues/5556
// A property with a native type declaration AND a generic @var annotation used to lose the
// template parameter, e.g. `/** @var Box<A> */ private Box $box;` was inferred as `Box|Box<A>`
// instead of `Box<A>`, so calling a template-returning method on it resolved to `mixed`
// instead of `A`. A property with only the phpdoc type (no native type) was unaffected.

/** @template T */
class Box5934 {
    /** @var T */
    public $t;

    /**
     * @param T $t
     */
    public function __construct($t) {
        $this->t = $t;
    }

    /**
     * @template U
     * @param U $default
     * @return T|U
     */
    public function getOrDefault($default = null) {
        return $this->t ?: $default;
    }
}

class A5934 {}
class B5934 {}

class Test5934 {
    /** @var Box5934<A5934> */
    private Box5934 $box1;
    /** @var Box5934<A5934> */
    private $box2;

    public function testWithoutAssignment() {
        $a1 = $this->box1->getOrDefault();
        $a2 = $this->box2->getOrDefault();
        '@phan-debug-var $a1, $a2';

        $b1 = $this->box1->getOrDefault(new B5934());
        $b2 = $this->box2->getOrDefault(new B5934());
        '@phan-debug-var $b1, $b2';
    }

    public function testAfterAssignment() {
        $this->box1 = new Box5934(new A5934());
        $this->box2 = new Box5934(new A5934());

        $a1 = $this->box1->getOrDefault();
        $a2 = $this->box2->getOrDefault();
        '@phan-debug-var $a1, $a2';
    }
}

class Widen5934 {
    // The @var type is wider than the native type - stdClass[] must survive the merge,
    // only the redundant bare \Traversable should be dropped.
    /** @var Traversable<int, stdClass>|stdClass[] */
    private Traversable $activities;

    public function test() {
        $x = $this->activities;
        '@phan-debug-var $x';
    }
}

class Incompatible5934 {
    // The @var type doesn't describe the native type at all - neither is a subtype of the
    // other, so both must still be kept (pre-existing union behavior, unchanged by this fix).
    /** @var int */
    private string $s;

    public function test() {
        $x = $this->s;
        '@phan-debug-var $x';
    }
}

class Nullable5934 {
    // The native type is nullable and the @var type is not - dropping the redundant bare
    // \Box5934 must not silently drop the nullability that only it was carrying.
    /** @var Box5934<A5934> */
    private ?Box5934 $box = null;

    public function test() {
        $x = $this->box;
        '@phan-debug-var $x';
    }
}
