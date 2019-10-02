<?php
function has_si(string $str) {
    if (preg_match('/si/', $str) > 1) {
        echo "Impossible\n";
    }
    if (preg_match('/si/', $str) > 0) {
        echo "Possible\n";
    }
    for ($i = 0; $i < 10; $i++) {
        $str .= rand(0,4);
        if (preg_match('/23/', $str) > 0) {
            echo "Possible\n";
        }
    }
}
has_si('mississippi');
