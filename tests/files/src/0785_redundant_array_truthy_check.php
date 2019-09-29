<?php
function test785(array $x, bool $flag) {
    if ($x) {
        if ($x) {  // should warn about being redundant.
            // Should infer type and warn.
            echo strlen($x) . json_encode($flag) . "\n";
        }
    }
}
