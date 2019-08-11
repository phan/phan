<?php
function void_753() : void {
    echo "In " . __FUNCTION__ . "\n";
}
if (void_753() === null) {
    echo "Definitely null\n";
}
if (is_null(void_753())) {
    echo "Definitely null\n";
}
