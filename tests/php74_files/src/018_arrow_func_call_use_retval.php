<?php
function double18(array $args) : array {
    return array_map(fn ($x) => $x * 2, $args);
}
double18([2,3]);
