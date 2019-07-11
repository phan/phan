<?php
function choose_xy($x, $y) {
    return $x ? $y : $y;
}
var_export(choose_xy(1,2));
