<?php
function test813(string $field, array $x = []) {
    return in_array($field, $x);
}
test813('x');
