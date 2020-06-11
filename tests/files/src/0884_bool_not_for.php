<?php
/**
 * Test
 *
 * Code for testing only.
 *
 * @param string $s
 * @param int $i
 * @param bool $b
 */
function test884($s,$i,$b) {
    for ($done = false; !$done; ) {
        $done=true;
        $vardefinedinloop="";
        if($i++<0) {
            $done=false;
        }
        if($b) {
            $vardefinedinloop=$s;
        }
    }

    if($vardefinedinloop !== '') {
        return $s;
    } else {
        return "";
    }
}
