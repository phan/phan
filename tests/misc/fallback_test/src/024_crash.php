<?php
// Phan should not crash.
// The fallback parser starts parsing `$notAFunction = function() ...` as if it were a parameter with an invalid default
class example {
    private function $notAFunction = function() : int {
        return 2;
    };
}
