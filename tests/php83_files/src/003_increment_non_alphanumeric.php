<?php

// Regression test for https://github.com/phan/phan/issues/4860

( static function () {
    $var = '';
    $var++;
    echo $var;
} )();
