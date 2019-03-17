<?php
function fn644() {
    $arr = ['x' => true];

    if ($arr['x'] !== null) {  // regression test for false positive PhanTypeInvalidDimOffset
        unset( $arr['x'] );
    }
}
