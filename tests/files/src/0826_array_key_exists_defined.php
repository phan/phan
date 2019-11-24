<?php

function test(bool $add)  {
    $x = [];
    if ($add) {
        $x['field'] = $add;
    }
    if (array_key_exists('field', $x)) {
        $result = $x['field'];
        echo count($result);
    }
}

