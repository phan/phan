<?php
function test183(int $x) {
    echo intdiv("\t\v$x\r\n", 2);
}
test183(2);
