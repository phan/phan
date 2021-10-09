<?php
function check_assign_redundant(array $x) {
    if ($x[0] = 'a') {
        echo "should this be ==\n";
    }
    if (!$x[1] = false) {
        echo "should this be ==\n";
    }
}
