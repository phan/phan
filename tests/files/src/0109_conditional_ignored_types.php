<?php

function f($p) {
    $v1 = $p ? $p : false;
    $v2 = $v1 ? $v1->m() : false;
}
