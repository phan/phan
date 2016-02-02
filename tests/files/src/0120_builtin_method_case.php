<?php
function f(string $p) {
    $v = DateTime::createFromFormat('n/j/Y G:i:s', $p);
    return $v->getTimestamp();
}
