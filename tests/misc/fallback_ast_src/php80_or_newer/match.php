<?php
match(1+1) {};
$x = match($x) {
    1, 2 => $val,
    default => null,
};
