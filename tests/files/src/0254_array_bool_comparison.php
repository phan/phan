<?php
function f1(array $p) {
    if($p && true);
    if(true && $p);
    if($p == "string");
    if("string" == $p);
}
/** @param string[] $p */
function f2($p) {
    if($p && true);
    if(true && $p);
    if($p == "string");
    if("string" == $p);
}
