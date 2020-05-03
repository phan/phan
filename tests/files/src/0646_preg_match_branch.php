<?php
/** @return array|string */
function preg_match_inference() {
    $matches = 'default';
    if (rand() % 2) {
        preg_match('/foo?/', 'foobar', $matches);
        echo strlen($matches);  // should warn
    } else {
        echo $matches;
    }
    return $matches;
}

/** @param string $matches */
function inline_var_inference($matches) {
    if (rand() % 2) {
        '@phan-var string[] $matches';
        echo strlen($matches);  // should warn
    } else {
        echo $matches;
    }
    return $matches;
}
