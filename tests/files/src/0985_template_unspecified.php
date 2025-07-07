<?php
/** @template T */
class Container {
    /** @var T */
    private $t;

    /** @param T $t */
    function __construct($t = null) {
        $this->t = $t;
        '@phan-debug-var $this';
    }

    /** @return T */
    function getValue() {
        return $this->t;
    }

    /** @return T */
    function getValueFoo(): Foo {
        return $this->t;
    }

    /**
     * Returns a Container<T> (or Container<null> when called with 0 arguments).
     * @template T
     * @param T $value
     * @return static<T>
     */
    static function newTyped($value = null) {
        $that = new static($value);
        '@phan-debug-var $that';
        return $that;
    }

    /**
     * Returns a Container<T> (or Container<never> when called with 0 arguments).
     * @template T
     * @param T ...$values
     * @return static<T>
     */
    static function newVariadic(...$values) {
        $that = new static($values[0] ?? null);
        '@phan-debug-var $that';
        return $that;
    }

    /**
     * Returns an unparameterized Container type.
     * @param mixed $value
     * @return static
     */
    static function newUnparameterized($value = null) {
        $that = new static($value);
        '@phan-debug-var $that';
        return $that;
    }
}

/**
 * @template T
 * @param T $value
 * @return Container<T>
 */
function newContainerTyped($value = null) {
    $cont = new Container($value);
    '@phan-debug-var $cont';
    return $cont;
}

/**
 * @template T
 * @param T ...$values
 * @return Container<T>
 */
function newContainerVariadic(...$values) {
    $cont = new Container($values[0] ?? null);
    '@phan-debug-var $cont';
    return $cont;
}

/**
 * @param mixed $value
 * @return Container
 */
function newContainerUnparameterized($value = null) {
    $cont = new Container($value);
    '@phan-debug-var $cont';
    return $cont;
}

// Classes for test case below
class Foo {}
class Bar extends Foo {}

$a = new Container(new stdClass);
$b = Container::newTyped(new stdClass);
$c = Container::newVariadic(new stdClass);
$d = Container::newUnparameterized(new stdClass);
$e = newContainerTyped(new stdClass);
$f = newContainerVariadic(new stdClass);
$g = newContainerUnparameterized(new stdClass);
'@phan-debug-var $a, $b, $c, $d, $e, $f, $g';

$aa = $a->getValue();
$bb = $b->getValue();
$cc = $c->getValue();
$dd = $d->getValue();
$ee = $e->getValue();
$ff = $f->getValue();
$gg = $g->getValue();
'@phan-debug-var $aa, $bb, $cc, $dd, $ee, $ff, $gg';

$aC = new Container(new Bar);
$bC = Container::newTyped(new Bar);
$cC = Container::newVariadic(new Bar);
$dC = Container::newUnparameterized(new Bar);
$eC = newContainerTyped(new Bar);
$fC = newContainerVariadic(new Bar);
$gC = newContainerUnparameterized(new Bar);
'@phan-debug-var $aC, $bC, $cC, $dC, $eC, $fC, $gC';

$aaC = $aC->getValueFoo();
$bbC = $bC->getValueFoo();
$ccC = $cC->getValueFoo();
$ddC = $dC->getValueFoo();
$eeC = $eC->getValueFoo();
$ffC = $fC->getValueFoo();
$ggC = $gC->getValueFoo();
'@phan-debug-var $aaC, $bbC, $ccC, $ddC, $eeC, $ffC, $ggC';

$a0 = new Container();
$b0 = Container::newTyped();
$c0 = Container::newVariadic();
$d0 = Container::newUnparameterized();
$e0 = newContainerTyped();
$f0 = newContainerVariadic();
$g0 = newContainerUnparameterized();
'@phan-debug-var $a0, $b0, $c0, $d0, $e0, $f0, $g0';

$aa0 = $a0->getValue();
$bb0 = $b0->getValue();
$cc0 = $c0->getValue();
$dd0 = $d0->getValue();
$ee0 = $e0->getValue();
$ff0 = $f0->getValue();
$gg0 = $g0->getValue();
'@phan-debug-var $aa0, $bb0, $cc0, $dd0, $ee0, $ff0, $gg0';
