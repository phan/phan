<?php
function warn_assign($x) {
    if ($x = null) {
        echo "Saw null $x\n";
    }
}
warn_assign(true);
