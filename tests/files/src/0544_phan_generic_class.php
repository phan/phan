<?php declare(strict_types=1);

/**
 * @phan-template T0
 * The type of element 0
 *
 * @phan-template T1
 * The type of element 1
 */
class PhanTuple2 {

    /** @var T0 */
    public $e0;

    /** @var T1 */
    public $e1;

    /**
     * @param T0 $e0
     * @param T1 $e1
     */
    public function __construct(
        $e0,
        $e1
    ) {
        $this->e0 = $e0;
        $this->e1 = $e1;
    }

}

function check(int $p0, string $p1) {}
function check2(bool $p0, string $p1) {}

$tuple_a = new PhanTuple2(42, 'string');

// Valid code
check($tuple_a->e0, $tuple_a->e1);

// Invalid code
check($tuple_a->e1, $tuple_a->e0);
