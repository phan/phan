<?php

function callable16b() { return 16; }
function callable16bUnused() { return 16; }
function main16b() {
    return Closure::fromCallable('callable16b');
}
main16b();
