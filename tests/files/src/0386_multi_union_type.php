<?php

/**
 * @return array<int,bool|null>
 */
function testPipeInTemplate386() {
    if (rand() % 2 > 0) {
        return [new stdClass()];
    } else {
        return [false, true, null];
    }
}
