<?php

declare(strict_types=1);

/**
 * Polyfill for phan_array_shape_cache_key() when the phan_helpers extension is not available.
 *
 * @param array<string|int,\Phan\Language\UnionType|\Phan\Language\AnnotatedUnionType> $field_types
 */
function phan_array_shape_cache_key(array $field_types, bool $is_nullable): string
{
    $key = $is_nullable ? '1' : '0';
    foreach ($field_types as $field_key => $field_union_type) {
        /** @var \Phan\Language\UnionType|\Phan\Language\AnnotatedUnionType $field_union_type */
        $key .= '|';
        if (\is_string($field_key)) {
            $key .= 'S:' . \strlen($field_key) . ':' . $field_key;
        } else {
            $key .= 'I:' . $field_key;
        }
        $id = $field_union_type->generateUniqueId();
        $key .= ':' . \strlen($id) . ':' . $id;
    }
    return $key;
}
