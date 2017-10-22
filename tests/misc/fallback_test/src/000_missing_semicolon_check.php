<?php

function test_missing_semicolon() : string {
    $x = []
    return strlen($x)
}
