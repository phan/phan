<?php

/**
 * @param number $x (See .phan/config.php for this test case, has phpdoc_type_mapping overridden)
 * @param unknown_type $y (in phpdoc_type_mapping)
 * @param missingClass $z
 */
function accepts_number($x, $y, $z) : int {
    return strlen($x);
}
