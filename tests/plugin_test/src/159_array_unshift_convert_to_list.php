<?php

/**
 * @return associative-array<int,string>
 */
function test_unshift_converts_assoc_int_to_list(int $i) : array {
    $values = [$i => "value $i"];
    array_unshift($values, "before $i");
    return $values;  // should warn, this is a list.
}

/**
 * @return list<string>
 */
function test_unshift_doesnt_convert_assoc_string(int $i) : array {
    $values = ["key $i" => "value $i"];
    array_unshift($values, "before $i");
    return $values;  // should warn, this is also an array with string keys
}
test_unshift_doesnt_convert_assoc_string(2);
test_unshift_converts_assoc_int_to_list(3);
