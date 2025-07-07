<?php

namespace NS986;

use Iterator;

class MyIterator implements Iterator {

    public function current(): string {
        return $GLOBALS['a'];
    }

    public function next() {
    }


    public function key(): string {
        return $GLOBALS['c'];
    }

    public function valid() {
        return (bool)rand();
    }

    public function rewind() {
    }
}

class MyAggregate implements \IteratorAggregate {
    public $arr;

    /** @param array<int,string> $arr */
    public function __construct(array $arr) {
        $this->arr = $arr;
    }

    /**
     * @return Iterator<int,string>
     */
    public function getIterator() {
        return new \ArrayIterator($this->arr);
    }
}

( static function () {
    // Real types should be inferred for loop variables in the first loop only.

    $iterator = new MyIterator();
    foreach ( $iterator as $iKey => $iVal ) {
        '@phan-debug-var $iKey, $iVal';
        echo "$iKey - $iVal\n";
    }

    // @phan-suppress-next-line PhanTypeMismatchArgument
    $aggregate = new MyAggregate( [ 'not an integer' => true ] );
    foreach ($aggregate as $aKey => $aVal) {
        '@phan-debug-var $aKey, $aVal';
        echo "$aKey - $aVal\n";
    }
} )();
