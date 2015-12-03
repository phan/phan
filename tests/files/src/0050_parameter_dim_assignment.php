<?php
function f(array $r) {
    $r['b'] = 's';
    foreach ($r['a'] as $i) {
        print $i;
    }
}
f(['a' => [1]]);


function g(array $arr) {
    if(isset($arr['extras'])) {
        foreach($arr['extras'] as $val) echo $val;
    }
}
g(['a','b','c']);
g(['extras'=>[1,2,3]]);

function h(int $i) : int {
    $i = 'string';
    return $i;
}
h(42);
