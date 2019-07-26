<?php
// Should not emit PhanRedundantCondition
function test_with_retries() {
    $retries = 3;
    while ($retries--) {
        if ($retries !== 0) {
            echo "Retrying\n";
        }
    }
}
