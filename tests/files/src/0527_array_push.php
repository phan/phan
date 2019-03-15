<?php

class Example527 {
	public $p = [];
	public $a = [];
}

function test(Example527 $o)
{
    var_dump($o->p); // no error

    $o->a = [1, 2, 3];
    array_push($o->a, 4);

    var_dump($o->p); // Should not warn after array_push
    $arr = [];
    $b = 'arr';
    array_push($$b, 4);  // should not crash
    var_export($arr);
}
