<?php
function iter8(array $arr) {
    while (list($k, $v) = each($arr)) {
        echo "$k: $v\n";
    }
}
