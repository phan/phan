<?php

function test_loop_reuse705() {
    $r = new ReflectionClass('ArrayObject');
    foreach ($r->getMethods() as $r) {
        echo intdiv($r, 2);
    }
    $r = new ReflectionClass('ArrayObject');
    foreach ($r->getMissingMethod() as $r) {
        var_dump($r);
    }
}
