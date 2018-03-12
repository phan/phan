<?php
function f1(array $p, array $p2) {
    if($p && true);
    if(true && $p);
    if($p == "string");
    if("string" == $p2);
}
/**
 * @param string[] $p
 * @param string[] $p2
 */
function f2($p, $p2) {
    if($p && true);
    if(true && $p);
    if($p == "string");
    if("string" == $p2);
}
