<?php declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

/**
 * Loads missing declarations
 */
class Shim
{
    /**
     * Loads the AST shim and any constants that are missing from older php-ast versions.
     * @return void
     */
    public static function load() : void
    {
        if (!class_exists('\ast\Node')) {
            // Fix for https://github.com/phan/phan/issues/2287
            require_once __DIR__ . '/ast_shim.php';
        }
        if (!defined('ast\AST_PROP_GROUP')) {
            define('ast\AST_PROP_GROUP', 545);
        }
        if (!defined('ast\AST_CLASS_NAME')) {
            define('ast\AST_CLASS_NAME', 287);
        }
    }
}
