<?php declare(strict_types=1);

if ((int)phpversion()[0] < 7) {
    fwrite(
        STDERR,
        'Phan requires PHP version 7 or greater. See https://github.com/phan/phan#getting-started for more details.'
    );
    exit(1);
}

if (!(file_exists(__DIR__ . '/../vendor/autoload.php') || file_exists(__DIR__ . '/../../../autoload.php'))) {
    fwrite(
        STDERR,
        'Autoloader not found. Make sure you run `composer install` before running Phan. See https://github.com/phan/phan#getting-started for more details.'
    );
    exit(1);
}

// Fix Turkish locales(tr_TR) - strtolower('I') is not 'i', so phan lookup might fail.
// (But continue formatting times, etc. in the user's locale)
setlocale(LC_CTYPE, 'C');
