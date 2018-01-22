<?php

/**
 * @param array{field:string} $options (Phan didn't support option arrays in the type checker, so it casts this to string[])
 */
function test_options(array $options) {
    echo $options['field'];
}

test_options(['field' => 'a string']);
test_options(['field' => 11]);
