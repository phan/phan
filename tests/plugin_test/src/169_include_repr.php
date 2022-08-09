<?php

declare(strict_types=1);

function test169(int $i, int $j) : void {
    echo strlen([
        require __DIR__ . '/000_plugins.php',
        require_once __DIR__ . '/000_plugins.php',
        include __DIR__ . '/000_plugins.php',
        include_once __DIR__ . '/000_plugins.php',
        eval('echo "test";'),
    ]);
    echo intdiv("{$i}2{$j}", 2);
}
