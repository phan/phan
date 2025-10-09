<?php

/** Ensure switch fall-through case defines the variable for later use. */
function issue4274(string $str): void
{
    switch ($str) {
        case 'a':
            // fall through to initialize $tmp in the next case
        case 'b':
            $tmp = 'x';
            break;
        default:
            $tmp = 'y';
            break;
    }

    echo $tmp;
}
