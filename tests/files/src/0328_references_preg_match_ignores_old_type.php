<?php

function test328(string $str) {
    $matches = 'ignored';
    preg_match('/foo/', 'foobar', $matches);  // doesn't warn about incompatibility
    preg_match('/foo/', $str, 'hello');  // warns about non-reference being passed in
    if ($matches) {
        echo strlen($matches);  // warns that $matches is actually an array
    }
}
