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

if (is_array(array_key_exists('foo', []))) {
    echo "impossible\n";
}
