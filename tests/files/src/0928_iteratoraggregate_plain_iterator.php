<?php
class X {
    /** @deprecated */
    public $x;
}

class Y implements IteratorAggregate {
    /** @var list<X> */
    public $xs;
    /** @return Iterator<int, X> */
    function getIterator() {
        return new ArrayIterator($this->xs);
    }
}

/** @param Y $y */
function f1($y) {
    '@phan-debug-var $y';
    foreach ($y as $k => $x) {
        '@phan-debug-var $k, $x';
        echo $x->x;
    }
}

/** @param Y|Iterator<X> $y */
function f2($y) {
    foreach ($y as $x) {
        echo $x->x;
    }
}
