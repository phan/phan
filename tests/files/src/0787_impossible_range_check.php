<?php
function test787(string $str, bool $b, ?bool $nullable) {
    $value = rand() % 2 ? 3 : 4;
    var_export($value > 4);
    var_export('foo' >= $b);
    $s = rand(0,1) ? 'FOO' : 'bar';
    var_export($s >= $nullable);
}
test787('test', (bool)rand(0,1), null);
