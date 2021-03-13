<?php
function a931() : void{
    $x = 1;
    define("CONST_{$x}", true);
    echo strlen(CONST_1); // should warn about true
}
