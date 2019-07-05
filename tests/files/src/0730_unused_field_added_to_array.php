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
// Should not warn - https://github.com/phan/phan/issues/2935
function modify_arg_by_reference(array $values) {
    $values[0]['value'] = 'result';
}
