<?php

declare(strict_types=1);

// Only define if the extension didn't
if (!function_exists('phan_ast_hash')) {
    require __DIR__ . '/../polyfills/phan_ast_hash.php';
}

if (!function_exists('phan_unique_types')) {
    require __DIR__ . '/../polyfills/phan_unique_types.php';
}

if (!function_exists('phan_unique_union_id')) {
    require __DIR__ . '/../polyfills/phan_unique_union_id.php';
}

if (!function_exists('phan_array_shape_cache_key')) {
    require __DIR__ . '/../polyfills/phan_array_shape_cache_key.php';
}

