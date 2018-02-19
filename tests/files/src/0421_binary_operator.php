<?php

function example(int $left, int $right) {
    $left | -$right;
    $left ^ -$right;
    $left && print("Not empty");
    $left and print("Not empty");
    $left xor print("Printed");
    $left || print("empty");
    $left or print("empty");
    $left ?: print("empty");
    $left ?? print("empty");
    $left . $right;
    $left * $right;
    $left % $right;
    $left < $right;
    $left <=> $right;
    $left == $right;
    $left === $right;
}
