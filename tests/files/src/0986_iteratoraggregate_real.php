<?php

namespace NS986;

class MyAggregate implements \IteratorAggregate {
    public $arr;

    /** @param array<int,string> $modelArray */
    public function __construct($modelArray = [])
    {
        $this->arr = $modelArray;
    }

    /**
     * @return \Iterator<int,string>
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->arr);
    }
}

$aggregate = new MyAggregate( [ 'not an integer' => [ 'array, not string' ] ] );
foreach ($aggregate as $key => $value) {
    // No real types should be inferred here.
    '@phan-debug-var $key, $value';
    echo "$key - $value\n";
}
