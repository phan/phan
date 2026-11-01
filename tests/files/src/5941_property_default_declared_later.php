<?php
// Regression test for #5578: the phpdoc check of a promoted property's default must not depend on
// whether the classes used by the default value were parsed before the property.

class Foo5941 {
    /**
     * @param callable(int): int $callback
     * @param BazInterface5941 $impl
     * @param callable $notInvokable
     * @param BazInterface5941 $wrongClass
     * @param callable $suppressed
     */
    public function __construct(
        private $callback = new Bar5941(),
        private $impl = new Impl5941(),
        private $notInvokable = new NoInvoke5941(),
        private $wrongClass = new NoInvoke5941(),
        /** @suppress PhanTypeMismatchPropertyDefault */
        private $suppressed = new NoInvoke5941(),
        private Baz5941 $real = new Sub5941(),
    ) {
        var_dump($this->callback, $this->impl, $this->notInvokable, $this->wrongClass, $this->suppressed, $this->real);
    }
}

class Bar5941 {
    public function __invoke(int $x): int {
        return $x;
    }
}

interface BazInterface5941 {}
class Impl5941 implements BazInterface5941 {}
class NoInvoke5941 {}
class Baz5941 {}
class Sub5941 extends Baz5941 {}

return new Foo5941();

// The same checks when the classes were declared before use, including an inherited property
// (inherited properties are clones; the pending check must only be repeated for the declaring property).
class SubBefore5941 extends FooBefore5941 {}

class FooBefore5941 {
    /** @var int */
    public $scalarMismatch = 'str';
    /** @var string */
    public $scalarOk = 'ok';
    /**
     * @var int
     * @suppress PhanTypeMismatchPropertyDefault
     */
    public $scalarSuppressed = 'str';

    /**
     * @param callable(int): int $callback
     * @param BazInterface5941 $impl
     * @param callable $notInvokable
     */
    public function __construct(
        private $callback = new Bar5941(),
        private $impl = new Impl5941(),
        private $notInvokable = new NoInvoke5941(),
    ) {
        var_dump($this->callback, $this->impl, $this->notInvokable);
    }
}

class SubAfter5941 extends FooBefore5941 {}

return [new SubBefore5941(), new SubAfter5941()];
