<?php

/**
 * @param array{firstProp:string,secondProp:?int} $params
 * @return void
 */
function test426(array $params) {
    echo strlen($params['firstProp']);
}
test426(['firstProp' => 'value']);
test426(['secondProp' => 2]);
test426(['secondProp' => false]);
test426(['firstProp' => 'value', 'secondProp' => 22]);
test426(['firstProp' => 'value', 'secondProp' => null]);
test426(['firstProp' => 'value', 'secondProp' => false]);
