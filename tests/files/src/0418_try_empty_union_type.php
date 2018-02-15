<?php
/**
 * Regression test for analysis of try block where catch block rethrows
 * @param object $input
 */
function test($input, bool $filter) {
    try {
        $rooms = $input->method();  // This has an empty union type
        $x = 2;
        foreach ($rooms as &$a) {  // This should not warn
            $a['field'] = true;
        }
    } catch (Exception $e) {
        throw new Exception("generic message");
    }

    // In the same way as the above try block, this should not warn either
    foreach ($rooms as $room) {
        var_export($room);
    }
    echo count($x);  // should warn. (Sanity checking that variable types are known)
}
