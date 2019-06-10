<?php

/**
 * @param array|object $obj
 */
function test_sleep($obj) : void {
    if (is_object($obj)) {
        var_export($obj->__sleep());
    }
}
