<?php
function test181($unused = false) {
    echo "In test181\n";
}
test181(true);
$cb = function ($unusedParam1, $unusedParam2 = 'default') {
};
$cb(1, 2);
class Example181 {
    public function __construct(int $unusedVar = 0) {
    }
    public function dump(int $unused_x = null, string $required, int $_ = 2) {
        var_dump($required);
    }
    public function overridden($unusedParam = null) {
    }
}
class Override181 extends Example181 {
    public function overridden($param = null) {
        var_dump($param);
    }
}
$a = new Example181(123);
$a->dump(1, 'required', -1);
function main181(Example181 $var) {
    $var->overridden('blah');
}
main181($a);
