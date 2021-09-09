<?php

function a203($arg) {
    if ( rand() > 0 ) {
        global $myGlobal;
        var_dump( $arg, $myGlobal );
    }
}

a203(true);
a203(false);
