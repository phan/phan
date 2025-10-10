<?php

declare(strict_types=1);

/**
 * Polyfill for phan_unique_types() when the phan_helpers extension is not available.
 *
 * Fast deduplication of Type objects using object identity.
 *
 * @param list<object> $type_list Array of Type objects to deduplicate
 * @return list<object> Deduplicated array of Type objects
 * @suppress PhanRedefineFunctionInternal,UnusedSuppression
 */
function phan_unique_types(array $type_list): array
{
    $new_type_list = [];
    if (\count($type_list) >= 8) {
        // This approach is faster, but only when there are 8 or more types (tested in php 7.3)
        // See https://github.com/phan/phan/pull/3475#issuecomment-550570579
        foreach ($type_list as $type) {
            $new_type_list[\spl_object_id($type)] = $type;
        }
        return \array_values($new_type_list);
    }
    foreach ($type_list as $type) {
        if (!\in_array($type, $new_type_list, true)) {
            $new_type_list[] = $type;
        }
    }
    return $new_type_list;
}
