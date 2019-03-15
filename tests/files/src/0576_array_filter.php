<?php

/**
 * @param array<string,bool> $args
 * @return array<string,false>
 */
function test(array $args) {
    return array_filter($args);
}
