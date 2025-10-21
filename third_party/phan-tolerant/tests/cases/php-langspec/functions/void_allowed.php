/* Auto-generated from php/php-langspec tests */
<?php

function foo(): void {
    // okay
}

foo();

function bar(): void {
    return; // okay
}

bar();

echo "OK!", PHP_EOL;
