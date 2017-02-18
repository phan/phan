<?php
function foo265_good() : string {
    $x = [];
    return is_string($x) ? urldecode($x) : "default";
}

function foo265_issue() : string {
    $x = [];
    return is_int($x) ? urldecode($x) : "default";
}
