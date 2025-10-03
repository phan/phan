<?php
function test_str_replace($value)  {
    do {
        $value = preg_replace_callback('/ab/', function ($_) {
            return '';
        }, $value, -1, $count);
    } while ($count);
    return $value;
}
