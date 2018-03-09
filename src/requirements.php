<?php declare(strict_types = 1);

assert(
    (int)phpversion()[0] >= 7,
    'Phan requires PHP version 7 or greater. See https://github.com/phan/phan#getting-it-running for more details.'
);

assert(
    file_exists(__DIR__ . '/../vendor/autoload.php') || file_exists(__DIR__ . '/../../../autoload.php'),
    'Autoloader not found. Make sure you run `composer install` before running Phan. See https://github.com/phan/phan#getting-it-running for more details.'
);

// Fix turkish locales(tr_TR) - strtolower('I') is not 'i', so phan lookup might fail.
// (But continue formatting times, etc. in the user's locale)
setlocale(LC_CTYPE, 'C');
