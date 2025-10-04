<?php

function demo_loop(string $input): void
{
    do {
        $input = preg_replace('/ph/', '(f)', $input, -1, $count);
    } while ($count > 0);
}
