<?php

class Example7 {
    public static function custom_throws(string $message): never {
        throw new RuntimeException(__METHOD__ . ": $message");
    }
}
function custom_throws(string $message): never {
    throw new RuntimeException($message);
}
if (!isset($x) || !is_array($x)) {
    custom_throws('message');
}

echo spl_object_hash($x); // should warn, this is an array
Example7::custom_throws('finished');

echo "Unreachable\n";
