<?php

namespace NS638;

class Foo {
    public function bar($a = __FUNCTION__, $b = __METHOD__) {
        echo "$a $b\n";
    }
}
function bar($a = __FUNCTION__, $b = __METHOD__, $c = __CLASS__) {
    echo "$a $b $c\n";
}
function requiredAfterOpt(int $a = 2, int $b) {
    echo "$a $b\n";
}
bar('x', 'y');
requiredAfterOpt(1,2);

$f = new Foo;
$f->bar();
