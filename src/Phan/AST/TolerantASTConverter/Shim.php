<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

/**
 * Loads missing declarations
 */
class Shim
{
    /**
     * Loads the AST shim and any constants that are missing from older php-ast versions.
     */
    public static function load(): void
    {
        if (!\class_exists('\ast\Node')) {
            // Fix for https://github.com/phan/phan/issues/2287
            require_once __DIR__ . '/ast_shim.php';
        }
        if (!\defined('ast\AST_PROP_GROUP')) {
            \define('ast\AST_PROP_GROUP', 545);
        }
        if (!\defined('ast\AST_CLASS_NAME')) {
            \define('ast\AST_CLASS_NAME', 287);
        }
        if (!\defined('ast\AST_ARROW_FUNC')) {
            \define('ast\AST_ARROW_FUNC', 71);
        }
        if (!\defined('ast\AST_TYPE_UNION')) {
            \define('ast\AST_TYPE_UNION', 254);
        }
        if (!\defined('ast\flags\DIM_ALTERNATIVE_SYNTAX')) {
            \define('ast\flags\DIM_ALTERNATIVE_SYNTAX', 1 << 1);
        }
        if (!\defined('ast\flags\PARENTHESIZED_CONDITIONAL')) {
            \define('ast\flags\PARENTHESIZED_CONDITIONAL', 1);
        }
        if (!\defined('ast\flags\TYPE_FALSE')) {
            \define('ast\flags\TYPE_FALSE', 2);
        }
    }
}
