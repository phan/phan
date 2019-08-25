<?php
if (is_int(true)) {
    echo "Impossible\n";
}
if (is_array(true)) {
    echo "impossible\n";
}
if (is_object([])) {
    echo "impossible\n";
}
// In php 7.x, the real type is ?bool (includes null for invalid args).
// In php 8.0, the real type is bool (throws for invalid args)
if (is_array(array_key_exists('foo', []))) {
    echo "impossible\n";
}
function test1($x) {
    if (is_array(key($x))) {
        echo "Impossible, key() only returns int|string|null\n";
    }
    return $x;
}
test1([]);
