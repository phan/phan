<?php
function test_mixed($val) {
    $x = [];
    if ($val) {
        $x['val'] = $val;
    }
    if (!empty($x['val'])) {
        if ($x['val']) {
            echo "This is redundant\n";
        }
    }
}
function test_obj($val) {
    if (!is_object($val)) {
        return;
    }
    $x = [];
    if (rand() % 2) {
        $x['val'] = $val;
    }
    if (!empty($x['val'])) {
        if ($x['val']) {
            echo "This is redundant\n";
        }
    }
}
// test_mixed([]);
