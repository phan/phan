<?php
// Regression test for #5575: a loop (or any compound statement) in the global scope
// must not hide later writes to globals made via `global $x` inside functions,
// and later global-scope assignments must still be visible via `global $y`.
$x = false;

for ($i = 0; $i < 10; $i++) {
}

function f5939(): void
{
    global $x;
    $x = true;
}

f5939();
if ($x) {
    echo "this is not false\n";
}
'@phan-debug-var $x';

$y = "str";
function takes_int5939(int $i): void
{
    var_export($i);
}
function g5939(): void
{
    global $y;
    takes_int5939($y);
}
g5939();

if (rand() < 5) {
    $z = 1;
}
function h5939(): void
{
    global $z;
    '@phan-debug-var $z';
}
h5939();
