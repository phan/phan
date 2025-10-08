<?php
declare(strict_types=1);

require_once __DIR__ . '/4827_b.php';

takesString(new HasToString());
takesString(new HasToString(), new HasToString());

function takesString(string $value, int $arg = 0): void {}
new \Exception(new HasToString());
