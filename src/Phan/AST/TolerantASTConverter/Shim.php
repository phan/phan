<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use ast;

use function class_exists;
use function define;
use function defined;

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
        if (!class_exists('\ast\Node')) {
            // Fix for https://github.com/phan/phan/issues/2287
            require_once __DIR__ . '/ast_shim.php';
        }
        // Define node kinds that may be absent
        if (!defined('ast\AST_PROP_GROUP')) {
            define('ast\AST_PROP_GROUP', 0x221);
        }
        if (!defined('ast\AST_CLASS_CONST_GROUP')) {
            define('ast\AST_CLASS_CONST_GROUP', 0x220);
        }
        if (!defined('ast\AST_CLASS_NAME')) {
            define('ast\AST_CLASS_NAME', 287);
        }
        if (!defined('ast\AST_ARROW_FUNC')) {
            define('ast\AST_ARROW_FUNC', 71);
        }
        if (!defined('ast\AST_TYPE_UNION')) {
            define('ast\AST_TYPE_UNION', 254);
        }
        if (!defined('ast\AST_ATTRIBUTE_LIST')) {
            define('ast\AST_ATTRIBUTE_LIST', 253);
        }
        if (!defined('ast\AST_MATCH_ARM_LIST')) {
            define('ast\AST_MATCH_ARM_LIST', 252);
        }
        if (!defined('ast\AST_ATTRIBUTE_GROUP')) {
            define('ast\AST_ATTRIBUTE_GROUP', 251);
        }
        if (!defined('ast\AST_MATCH')) {
            define('ast\AST_MATCH', 0x2fc);
        }
        if (!defined('ast\AST_MATCH_ARM')) {
            define('ast\AST_MATCH_ARM', 0x2fb);
        }
        if (!defined('ast\AST_ATTRIBUTE')) {
            define('ast\AST_ATTRIBUTE', 0x2fa);
        }
        if (!defined('ast\AST_NAMED_ARG')) {
            define('ast\AST_NAMED_ARG', 0x2f9);
        }
        if (!defined('ast\AST_NULLSAFE_PROP')) {
            define('ast\AST_NULLSAFE_PROP', 0x2f8);
        }
        if (!defined('ast\AST_NULLSAFE_METHOD_CALL')) {
            define('ast\AST_NULLSAFE_METHOD_CALL', 0x3ff);
        }
        // Define flags
        if (!defined('ast\flags\DIM_ALTERNATIVE_SYNTAX')) {
            define('ast\flags\DIM_ALTERNATIVE_SYNTAX', 1 << 1);
        }
        if (!defined('ast\flags\PARENTHESIZED_CONDITIONAL')) {
            define('ast\flags\PARENTHESIZED_CONDITIONAL', 1);
        }
        $max_param_flag = \max(ast\flags\PARAM_REF, ast\flags\PARAM_VARIADIC);
        if (!defined('ast\flags\PARAM_MODIFIER_PUBLIC')) {
            define('ast\flags\PARAM_MODIFIER_PUBLIC', $max_param_flag << 1);
        }
        if (!defined('ast\flags\PARAM_MODIFIER_PROTECTED')) {
            define('ast\flags\PARAM_MODIFIER_PROTECTED', $max_param_flag << 2);
        }
        if (!defined('ast\flags\PARAM_MODIFIER_PRIVATE')) {
            define('ast\flags\PARAM_MODIFIER_PRIVATE', $max_param_flag << 3);
        }
        if (!defined('ast\flags\TYPE_FALSE')) {
            define('ast\flags\TYPE_FALSE', 2);
        }
        if (!defined('ast\flags\TYPE_STATIC')) {
            define('ast\flags\TYPE_STATIC', \PHP_MAJOR_VERSION >= 80000 ? 15 : 20);
        }
        if (!defined('ast\flags\TYPE_MIXED')) {
            define('ast\flags\TYPE_MIXED', \PHP_MAJOR_VERSION >= 80000 ? 16 : 21);
        }
    }
}
