<?php

/**
 * @param list<int> $list
 */
function takes_list(array $list): void {
    '@phan-debug-var $list';
}

$list = [1, 1, 2, 2];
$list = array_unique($list);
'@phan-debug-var $list';
takes_list($list);
