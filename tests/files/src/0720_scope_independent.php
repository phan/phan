<?php

function returns_stdclass() : stdClass {
    return new stdClass();
}

class X {
    public static function returns_object() : object {
        return new ArrayObject([]);
    }
    public static function returns_array() : array {
        return [2];
    }
}

if (returns_stdclass()) {
    echo "Always true\n";
}
if (X::returns_object()) {
    echo "Always true\n";
}
if (is_array(X::returns_object())) {
    echo "Always false\n";
}
if (is_array(X::returns_array())) {
    echo "Always false\n";
}
if (is_array(new ArrayObject([]))) {
    echo "Always false\n";
}
