<?php

namespace Bar;

function main19() {
    var_export([
        __FILE__ => 1,
        __FUNCTION__ => 2,
        __LINE__ => 3, __LINE__ => 4,
        __FILE__ => 5,
        __NAMESPACE__ => 5,
        'Bar' => 6,
        'Bar' => 6,
        __DIR__ => 'dir',
        __DIR__ => 'dir',
    ]);
}
main19();
