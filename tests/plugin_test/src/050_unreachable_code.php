<?php

/**
 * @return void
 * @throws Exception
 */
function test50(int $x) {
    switch ($x) {
        case 2:
            break;
            echo $x;
        case 3:
            continue;
            break;
        case 4:
            return;
            break;
        default:
            throw new Exception("fail");
            break;
    }
}
test50(5);
