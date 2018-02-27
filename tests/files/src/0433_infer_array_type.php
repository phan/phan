<?php

function example432() {
    $x = ['key' => 'value'];
    $x['yz'] = [];
    echo count($x['key']);
    echo strlen($x['yz']);
}
