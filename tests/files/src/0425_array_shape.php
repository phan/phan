<?php

/**
 * @param array{Case:string} $params
 * @return void
 */
function test(array $params) {
    var_export($params);
}
test(['case' => 'x']);
test(['Case' => 'x']);
test(['Case' => null]);
test(['Case' => 'x', 'other' => false]);
$global425 = ['Case' => null];
test($global425);
