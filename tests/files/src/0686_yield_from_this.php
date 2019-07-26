<?php

namespace NS676;

use Iterator;
use IteratorAggregate;

class Good implements Iterator  {
    public function yieldFromSelf() {
        yield from $this;
        yield from clone($this);
    }

    public $i = 0;

    public function current() {
        return $this->i;
    }

    public function key() {
        return $this->i;
    }

    public function next() : void {
        $this->i++;
    }

    public function rewind() : void {
        $this->i = 0;
    }

    public function valid() : bool {
        return $this->i < 10;
    }
}
foreach (new Good() as $key => $value) {
    echo "$key => $value\n";
}

class GoodB implements IteratorAggregate {
    public function yieldFromSelf() {
        yield from $this;
    }

    public $key = 'value';

    public function getIterator() {
        return new \ArrayObject($this);
    }
}
foreach (new GoodB() as $key => $value) {
    echo "$key => $value\n";
}

class Bad {
    public function yieldFromSelf() {
        yield from $this;
        yield from clone($this);
    }
}
