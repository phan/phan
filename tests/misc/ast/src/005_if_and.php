<?php
function foo($b) {
    if (($a = $b) && $a > 0) {
        return 42;
    }
}
