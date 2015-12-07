<?php

class C {}
class D {}

function f($o) {
    if ($o instanceof C) {
        return true;
    }

    if ($o instanceof UndefClass) {
        return false;
    }

    return ($o instanceof C || $o instanceof D || $o instanceof UndefClass);
}

$v = f(new C);
$w = f(new D);
