<?php

/** @param string|int $a */
function foo($a) : string {
    if (!is_string($a)) { return sprintf('bar%d', $a); } return $a;
}
