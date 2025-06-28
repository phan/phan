<?php
/**
 * @template A
 * @template B
 */
class Pair {
    /** @var A */
    public $a;
    /** @var B */
    public $b;

    /**
     * @param A $a
     * @param B $b
     */
    function __construct($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }

    /** @return A */
    function getA() {
        return $this->a;
    }

    /** @return B */
    function getB() {
        return $this->b;
    }

    /** @param A $a */
    function setA($a): void {
        $this->a = $a;
    }

    /** @param B $b */
    function setB($b): void {
        $this->b = $b;
    }
}

/**
 * @template B
 * @template A
 * @extends Pair<B, A>
 */
class ShadowPair extends Pair {
    /**
     * @param B $a
     * @param A $b
     */
    function __construct($a, $b) {
        parent::__construct($a, $b);
    }

    /** @return B */
    function getX() {
        return $this->a;
    }

    /** @return A */
    function getY() {
        return $this->getB();
    }

    /** @return A */
    function getYErr1() {
        return $this->a;
    }

    /** @return A */
    function getYErr2() {
        return $this->getA();
    }

    /** @return A */
    function getYErr3() {
        return $this->getX();
    }

    /** @param B $x */
    function setX($x): void {
        $this->a = $x;
    }

    /** @param A $y */
    function setY($y): void {
        $this->setB($y);
    }

    /** @param A $y */
    function setYErr1($y): void {
        $this->a = $y;
    }

    /** @param A $y */
    function setYErr2($y): void {
        $this->setA($y);
    }

    /** @param A $y */
    function setYErr3($y): void {
        $this->setX($y);
    }
}

$foo = new Pair('a', 1);
$bar = new ShadowPair('a', 1);

// OK: $foo[a] = $bar[a]
$foo->a = $bar->a;
$foo->a = $bar->getA();
$foo->a = $bar->getX();
$foo->setA($bar->a);
$foo->setA($bar->getA());
$foo->setA($bar->getX());
// OK: $foo[a] = $foo[a]
$foo->a = $foo->a;
$foo->a = $foo->getA();
$foo->setA($foo->a);
$foo->setA($foo->getA());
// OK: $bar[a] = $bar[a]
$bar->a = $bar->a;
$bar->a = $bar->getA();
$bar->a = $bar->getX();
$bar->setA($bar->a);
$bar->setA($bar->getA());
$bar->setA($bar->getX());
$bar->setX($bar->a);
$bar->setX($bar->getA());
$bar->setX($bar->getX());
// OK: $bar[a] = $foo[a]
$bar->a = $foo->a;
$bar->a = $foo->getA();
$bar->setA($foo->a);
$bar->setA($foo->getA());
$bar->setX($foo->a);
$bar->setX($foo->getA());

// Error: $foo[b] = $bar[a]
$foo->b = $bar->a;
$foo->b = $bar->getA();
$foo->b = $bar->getX();
$foo->setB($bar->a);
$foo->setB($bar->getA());
$foo->setB($bar->getX());
// Error: $foo[b] = $foo[a]
$foo->b = $foo->a;
$foo->b = $foo->getA();
$foo->setB($foo->a);
$foo->setB($foo->getA());
// Error: $bar[b] = $bar[a]
$bar->b = $bar->a;
$bar->b = $bar->getA();
$bar->b = $bar->getX();
$bar->setB($bar->a);
$bar->setB($bar->getA());
$bar->setB($bar->getX());
$bar->setY($bar->a);
$bar->setY($bar->getA());
$bar->setY($bar->getX());
// Error: $bar[b] = $foo[a]
$bar->b = $foo->a;
$bar->b = $foo->getA();
$bar->setB($foo->a);
$bar->setB($foo->getA());
$bar->setY($foo->a);
$bar->setY($foo->getA());
