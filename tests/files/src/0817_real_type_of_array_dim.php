<?php
function main(string $name) {
    $vals = explode($name, 2);
    '@phan-debug-var $vals';
    [$className, $methodName] = $vals;
    '@phan-debug-var $methodName';
    if (isset($methodName[0])) {
        '@phan-debug-var $methodName';
        echo "Saw $methodName\n";
    }
}
