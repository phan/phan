<?php

class Foo313 {
}

function test313() {
    $myVar = [];
    $myvar[] = [];
    $myvAR['x'] = [];
    $f = new Foo313();
    $f->prop[] = 'value';
    Foo313::$undef_static_prop[] = 3;
    list($myUndefListVar['x'], $f->otherProp) = [2];
}
