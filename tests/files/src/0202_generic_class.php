<?php declare(strict_types=1);

/**
 * @template T0
 * The type of element 0
 *
 * @template T1
 * The type of element 1
 */
class Tuple2 {

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
        $this->$e0 = $e0;
        $this->$e1 = $e1;
    }

    /** @return T0 */
    public function getE0() {
        return $this->e0;
    }

    /** @return T1 */
    public function getE1() {
        return $this->e1;
    }

    /** @return T0 */
    public function failMismatch() {
        return 42;
    }

    /** @return T0 */
    public function failVoid() {
    }

    /** @return T0 */
    public function failAnotherGeneric() {
        return $this->e1;
    }

    /** @return T0 */
    public static function failStatic() {
        return 42;
    }

}

function f(string $p0, int $p1) {}
$tuple_a = new Tuple2(42, 'string');

f($tuple_a->e0, $tuple_a->e1);
f($tuple_a->getE0(), $tuple_a->getE1());

f($tuple_a->e1, $tuple_a->e0);
f($tuple_a->getE1(), $tuple_a->getE0());

