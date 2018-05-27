<?php
/**
 * @suppress PhanPluginNumericalComparison
 */
function unused_return(int $v) {
    for ($i = 0; $i < 2; $i++) {
        if ($v === 3) {
            $myVar = 3;  // should warn
            $myUnusedVar = 4;
            return;
        } elseif ($v === -3) {
            $myVar = 3;  // should not warn
            $myUnusedVar = 4;
            continue;
        } elseif ($v === 4) {
            if (rand() % 2 > 0) {
                $myVar = 3;  // should warn
                return;
            }
            $myVar = 4;  // *should warn*, this is overwritten below
            $myUnusedVar = 4;
        } elseif ($v % 2 > 0) {
            $myVar = 2;  // should warn, this throws immediately
            $myUnusedVar = 5;
            throw new RuntimeException("end");
        }
        $myVar = 22;
    }
    echo $myVar;
}
unused_return(rand() % 20 - 10);
