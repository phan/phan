<?php
function foo919(array $foo): array
{
    foreach ($foo as $package) {
        if (class_exists($package['class']) || interface_exists($package['class'])) {
            '@phan-debug-var $package';
            return $package;
        }
    }
    return [];
}
