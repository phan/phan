<?php

/** @template T */
class Builder {
    /** @var T $value */
    public $value;
    /**
     * @param T $value
     */
    public function __construct($value) {
        $this->value = $value;
    }
    /** @return self<T> */
    public function foo(): self {
        return $this;
    }
    /** @return static<T> */
    public function foo2(): self {
        return $this;
    }
}
$x = new Builder(new stdClass());
'@phan-debug-var $x';  // type=Builder<\stdClass>
$y = $x->foo();
'@phan-debug-var $y';  // type=Builder<\stdClass>
$z = $x->foo2();
'@phan-debug-var $z';  // type=Builder<\stdClass>

