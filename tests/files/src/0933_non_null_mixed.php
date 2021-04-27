<?php
/** @param mixed $isMixed */
function coalesce933($isMixed) {
    $var = $isMixed ?? [];
    // array{}|mixed(real=array{}|non-null-mixed) - should be non-null-mixed
    '@phan-debug-var $var';
    if (is_null($var)) {
        echo "should rule out\n";
    }
}
