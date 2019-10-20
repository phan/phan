<?php

$nullVarInGlobalScope = null;
$zeroInGlobalScope = 0;
function test_in_loop() {
    $x = null;
    foreach ([2] as $y) {
        // Emits PhanCoalescingAlwaysNullInLoop (not a false positive)
        $value = $x ?? $y;
        echo "Found $value\n";
        $x = null;
        var_export(function () { return 'never called'; } ?? 'default');  // This is definitely non-null whether it's in a loop or not.
    }
    $varInLoop = 2.3;
    echo $varInLoop ?? 'blah';
    foreach ([2, 3] as $y) {
        echo $y ?? null;  // Emits PhanCoalescingNeverNullInLoop
        if ($x) {
            // Should emit PhanImpossibleConditionInLoop (not a false positive)
            echo "$x could be truthy\n";
        }
        $x = false;
        // Emits PhanCoalescingNeverNullInLoop (false positive)
        echo $varInLoop ?? 'blah';
    }
}

echo $nullVarInGlobalScope ?? "default";  // PhanCoalescingAlwaysNullInGlobalScope
echo null ?? rand(0,2);  // PhanCoalescingAlwaysNull
echo $zeroInGlobalScope ?? rand(0,2);  // PhanCoalescingNeverNullInGlobalScope
var_export($zeroInGlobalScope === $nullVarInGlobalScope);  // PhanImpossibleTypeComparisonInGlobalScope
var_export(__LINE__ ?? 'default');  // This is definitely non-null whether it's in the global scope or not.
