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
        $this->e0 = $e0;
        $this->e1 = $e1;
    }

    /** @return T0 */
    public function getE0() {
        return $this->e0;
    }

    /** @return T1 */
    public function getE1() {
        return $this->e1;
    }

    /** @param T0 $e0 */
    public function setE0($e0) {
        $this->e0 = $e0;
    }

    /** @param T1 $e1 */
    public function setE1($e1) {
        $this->e1 = $e1;
    }

    /**
     * @template X0
     * @param X0 $e0
     * @return static<X0, T1>
     */
    public function withE0($e0) {
        return new static($e0, $this->e1);
    }

    /**
     * @template X1
     * @param X1 $e1
     * @return static<T0, X1>
     */
    public function withE1($e1) {
        return new static($this->e0, $e1);
    }

    /** @return T0 */
    public function failMismatch() {
        return 42;
    }

    /** @return T0 */
    public function failVoid() {
    }

    /** @return T0 */
    public function failWrongGenericGet() {
        return $this->e1;
    }

    /** @param T0 $e0 */
    public function failWrongGenericSet($e0) {
        return $this->e1 = $e0;
    }

    /** @return T0 */
    public static function failStatic() {
        return 42;
    }

    /** @return T0 */
    public static function failStaticLeak() {
        return getUnparameterizedTuple()->e0;
    }

}

function check(int $p0, string $p1) {}
function check2(bool $p0, string $p1) {}


$tuple_a = new Tuple2(42, 'string');

// Valid code
check($tuple_a->e0, $tuple_a->e1);
check($tuple_a->getE0(), $tuple_a->getE1());
$tuple_a->e0 = 42;
$tuple_a->setE0(42);
$tuple_a2 = $tuple_a->withE0(false);
check2($tuple_a2->e0, $tuple_a2->e1);

// Invalid code
check($tuple_a->e1, $tuple_a->e0);
check($tuple_a->getE1(), $tuple_a->getE0());
$tuple_a->e1 = 42;
$tuple_a->setE1(42);
check2($tuple_a->e0, $tuple_a->e1);


function getUnparameterizedTuple(): Tuple2 {
    return new Tuple2(42, 'string');
}
$tuple_b = getUnparameterizedTuple();

// Valid code (currently emits issues, #4982)
check($tuple_b->e0, $tuple_b->e1);
check($tuple_b->getE0(), $tuple_b->getE1());
$tuple_b->e0 = 42;
$tuple_b->setE0(42);
$tuple_b2 = $tuple_b->withE0(false);
check2($tuple_b2->e0, $tuple_b2->e1);

// Invalid but unverifiable, since the type parameters are not known, should not emit issues
check($tuple_b->e1, $tuple_b->e0);
check($tuple_b->getE1(), $tuple_b->getE0());
$tuple_b->e1 = 42;
$tuple_b->setE1(42);
check2($tuple_b->e0, $tuple_b->e1);
