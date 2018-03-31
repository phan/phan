<?php

class MyClass24 {
    /** @var int */
    private $a;

    /** @var stdClass */
    protected $b;

    /** @var MyClass24 */
    public $c;

    /**
     * @param int|false $a
     * @param stdClass|null $b
     * @param MyClass24|Traversable $c
     */
    public function __construct($a, $b, $c) {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }
}
