<?php
$myUsedGlobal = ['first', 'second'];
/** @throws RuntimeException */
function testRealGlobalTypes() {
    global $myUsedGlobal;
    // Should not emit PhanRedundantCondition - this can change elsewhere
    if (!is_array($myUsedGlobal)) {
        throw new RuntimeException("Something else changed this global");
    }
    foreach ($myUsedGlobal as $var) {
        // Should emit PhanTypeMismatchArgumentInternal (not ProbablyReal)
        echo count($var);
    }
}
