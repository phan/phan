<?php

/**
 * @template TA
 */
class Base {
    /** @var TA */
    public $x;

    /**
     * @param TA $x
     */
    public function __construct($x) {
        $this->x = $x;
    }
}

/**
 * @template TX
 */
class DerivedBase {
    /** @var TX */
    public $y;

    /** @param Base<TX> $b */
    public function __construct(Base $b) {
        $this->y = $b->x;
    }
}

call_user_func(function () {
    $b1 = new Base(new stdClass());
    $b2 = new Base(rand(0,10));

    echo strlen($b1);
    $db1 = new DerivedBase($b1);
    $db2 = new DerivedBase($b2);
    echo strlen($db1);
    echo strlen($db1->y);
    echo strlen($db2);
    echo strlen($db2->y);
});
