<?php

// Phan does not have a strong understanding of goto, which is rare in PHP code.
// This is a regression test for a false positive.
function goto11($x) {
    if ($x > 0) {
        if ($x > 5) {
            goto end;
        }
        return;
    } else {
        throw new RuntimeException("Missing $x");
        echo "This is unreachable\n";
    }
    end:
    echo "This is the end $x\n";
}
goto11(10);
