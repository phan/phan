<?php

/**
 * @return iterable<object>
 */
function f_iterable()
{
    return array();
}

$v = null;
if (rand(0, 1) === 0) $v = f_iterable();

if ($v) {
    // should infer iterable<object>
    foreach ($v as $o) {
        echo strlen($o);
    }
}
