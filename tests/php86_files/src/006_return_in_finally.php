<?php

namespace NS86Finally;

// PHP 8.6 deprecates returning from a finally block

function overrides(): int
{
    try {
        return 1;
    } finally {
        return 2;
    }
}

function swallows(bool $flag): string
{
    try {
        throw new \RuntimeException('lost');
    } finally {
        if ($flag) {
            return 'swallowed';
        }
        echo "cleanup\n";
    }
}

function fine(): int
{
    try {
        return 1;
    } finally {
        // A return in a function declared in the finally block is not a return from the finally block.
        $cleanup = static function (): int {
            return 3;
        };
        echo $cleanup(), (fn() => 4)();
    }
}

echo overrides(), swallows(true), fine();
