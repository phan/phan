<?php

declare(strict_types=1);

/**
 * Polyfill for phan_unique_union_id() when the phan_helpers extension is not available.
 *
 * @param list<object>|array<int,object> $type_list
 * @param list<object>|array<int,object> $real_type_list
 */
function phan_unique_union_id(array $type_list, array $real_type_list = []): string
{
    $ids = [];
    foreach ($real_type_list as $type) {
        $ids[] = ~\spl_object_id($type);
    }
    foreach ($type_list as $type) {
        $ids[] = \spl_object_id($type);
    }
    \sort($ids);
    return \implode(',', $ids);
}
