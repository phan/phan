<?php
function do_echo(int $i): void {
    echo $i;
}
$x = [do_echo(1) => do_echo(2)];
echo (string)do_echo(3);
