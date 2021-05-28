<?php

/**
 * @param non-empty-list $a not a valid class name
 * @param phan-intersection-type $b without template args, this is mixed
 */
function test($a, $b) {
    '@phan-debug-var $a, $b';
}
