<?php
namespace NS;
if (defined('unknown_constant') &&
    unknown_constant &&
    (unknown_constant)->prop &&  // warns about definite non-object
    UNKNOWN_CONSTANT) {
    echo unknown_constant;  // does not warn
} else {
    echo "unknown constant is ", unknown_constant, "\n";
}
echo "unknown constant is ", unknown_constant, "\n";
