<?php
function weak_equality_731($value, bool $strict, object $o) {
    if ($value != null || ($strict === true && $value !== null)) {
        echo "This is non-null\n";
    }
    if ($o == true) {
        if (is_object($o)) {
            echo "Still an object\n";
        }
    }
}
