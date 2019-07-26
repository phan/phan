<?php
declare(strict_types=1);

function ambiguoustest($x)
{
    echo "For $x\n";
    // For '1', this hits case '1e0'
    switch ($x) {
        case '1e0':
            echo "1e0\n";
            break;
        case '1':
            echo "1\n";
            break;
        case 0:
            echo "0\n";
            break;
    }
    // For "foo", this hits case 0
    switch ($x) {
        case 0:
            echo "zero\n";
            break;
        case 'foo':
            echo "foo\n";
            break;
    }
    echo "Last switch:\n";
    switch ($x) {
        case '1bar':
            echo "1bar\n";
            break;
        case '1':
            echo "1\n";
            break;
        case '1e0':
            echo "1e0\n";
            break;
    }
}
ambiguoustest('1');
ambiguoustest('foo');
ambiguoustest('1e0');
