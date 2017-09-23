<?php

class A354 {
    /** @var string */
    public $foo = 'aaa';

    public function test() {
        $this->foo[1] = 'x';
        intdiv($this->foo, 2);
        $this->foo[] = 'x';  // should warn
        $x = 'aaa';
        $x[2] = '1';
        echo count($x);  // should warn.
        $x[] = 'c';  // should warn
        $y = 'aaa';
        $y['offset'] = 'c';
    }

    public function testFetch() {
        $str = 'abc';
        $x = $str[0];
        $y = $str[[]];  // wrong
        $z = $str['offset'];  // wrong
        $a = $str[null];  // wrong
        $arr = ['offset' => 'c'];
        $b = $arr['offset'];
        $c = $arr[[]];  // wrong
        $c = $arr[null];  // wrong
        $c = $arr[0];  // Phan isn't able to tell that this is wrong.
    }
}
