<?php

$w =
/**
 * blank, should be ignored?
 * @annotation
 */
function() : string {
    // BoundClass264::a_static_method();  // should emit an issue?
    return $this->_frameworkProperty;
};
