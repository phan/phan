<?php
/**
 * @param object $o
 * @return object
 */
function testObject(object $o) : object {
    return $o;
}
return testObject(new stdClass());
