<?php
namespace A;

function strlen(string $a) : string {
    return "Hello, World";
}

function f(string $s) : string { return $s; }

print f(strlen("string"));
