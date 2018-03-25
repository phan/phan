<?php

/**
 * @param string $class
 * Based on example from https://github.com/phan/phan/issues/1587
 */
function a461($class) {
    $parameters = [];

    if (@array_key_exists("class", $parameters)) {
        $foreign_class = $parameters['class'];
    } else {
        $foreign_class = $class;
    }

    $object = new $foreign_class();
}

a461("stdClass");
