<?php

function to_array411(iterable $input): array {
    if (is_array($input)) {
        return iterator_to_array($input);
    } else {
        return iterator_to_array($input, $preserve_keys = true);
    }
}
var_export(iterator_to_array('string'));  // should warn. But the above uses are valid.
