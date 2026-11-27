<?php
namespace App\Tests;

// `autoload-dev` of the project is the same package.
function test(): int {
    return \App\Core::inner();
}
