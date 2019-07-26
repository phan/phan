<?php

function float_check_not_redundant(float $x) {
    return !$x || rand() % 2 > 0;
}
