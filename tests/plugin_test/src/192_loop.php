<?php
function test192() {
    $loop = true;
    for(;$loop;) {
        echo "test\n";
        $loop = false;
    }
    return $loop;
}
test192();
