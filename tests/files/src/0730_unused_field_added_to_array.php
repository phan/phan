<?php
function append_values(array $arg) {
    $arg[] = 2;
    $arg[] = 3;
}
function double_values(array $input) : void {
    $result = [];
    foreach ($input as $e) {
        $result[] = $e * 2;
    }
    echo "Result is: not used\n";
}
/** @param array $arg */
function append_values_phpdoc($arg) {
    $arg[] = 2;
    $arg[] = 3;
}
/** @param array $result */
function double_values_phpdoc(array $input, $result) : void {
    foreach ($input as $e) {
        $result[] = $e * 2;
    }
    echo "Result is: not used\n";
}
