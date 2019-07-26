<?php
class C736 {
    public static function incorrect($a = null, $b = null, $c) {
        return [$a, $b, $c];
    }
}
function incorrect736($a, $b = null, $c) {
    return [$a, $b, $c];
}
incorrect736(1, 2);  // Expected: Emit PhanParamTooFew
C736::incorrect(1, 2);  // Expected: Emit PhanParamTooFew
incorrect736(1, 2, 3, 4);  // Expected: Emit PhanParamTooMany
C736::incorrect(1, 2, 3, 4);  // Expected: Emit PhanParamTooMany
