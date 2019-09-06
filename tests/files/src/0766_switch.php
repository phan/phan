<?php
function test766(int $x) {
    $y=0;
    switch($x) {
        case 1:
            $y=2;
            //no break;
        case 2:
            echo ($y?:'-'); // should not emit PhanImpossibleCondition.
    }
}
