<?php
declare(strict_types=1);

try {
    echo "test\n";
} catch(Countable $exceptionalCounter) {
    '@phan-debug-var $exceptionalCounter';
    echo $exceptionalCounter->getTrace(); // wrong, this is an array
    echo strlen($exceptionalCounter->count()); // wrong, this is a string
}
