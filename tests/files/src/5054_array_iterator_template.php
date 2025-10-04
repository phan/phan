<?php declare(strict_types=1);
/**
 * @return ArrayIterator<string,string>
 */
function generateIterator(): ArrayIterator
{
    return new ArrayIterator(['k1' => 'a', 'k2' => 'b']);
}

$iterator = generateIterator();
'@phan-debug-var $iterator';
foreach ($iterator as $key => $value) {
    '@phan-debug-var $key, $value';
}
