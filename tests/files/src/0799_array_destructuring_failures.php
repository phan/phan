<?php
function test799(array $values) {
    [] = $values;
    [$prog, 2 => $arg] = $values;
    [, 2 => $arg] = $values;
    [[]] = $values;
    // These are also invalid in foreach.
    foreach ($values as []) {
    }
    foreach ($values as [$a, 'other' => $b]) {
    }
}
