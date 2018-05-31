<?php
/** @phan-file-suppress PhanUnusedVariable common here */
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
        $c = $arr[0];
        $c = $arr[0x1f];
        $c = $arr['otherOffset'];
        $c = $arr[A354::OFFSET];
        $c = $arr[A354::OTHER_OFFSET];
        $c = $arr[self::OFFSET];
        $c = $arr[self::OTHER_OFFSET];
    }

    public function testFetchInt() {
        $x = [1, 2, 3 => 'x'];
        echo strlen($x[1]);
        echo strlen($x[2]);
        echo strlen($x[3]);
        echo intdiv($x[3], 2);
    }

    const OFFSET = 'offset';
    const OTHER_OFFSET = 'offset2';
}
