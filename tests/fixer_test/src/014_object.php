<?php
/**
 * @param object $o
 * @return object
 */
function testObject($o) {
    return $o;
}
return testObject(new stdClass());
