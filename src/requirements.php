<?php declare(strict_types = 1);

assert(
    extension_loaded('ast'),
    'The php-ast extension must be loaded in order for Phan to work. See https://github.com/etsy/phan#getting-it-running for more details.'
);

assert(
    (int)phpversion()[0] >= 7,
    'Phan requires PHP version 7 or greater. See https://github.com/etsy/phan#getting-it-running for more details.'
);

assert(
    file_exists(__DIR__ . '/../vendor/autoload.php'),
    'Autoloader not found. Make sure you run `composer install` before running Phan. See https://github.com/etsy/phan#getting-it-running for more details.'
);


