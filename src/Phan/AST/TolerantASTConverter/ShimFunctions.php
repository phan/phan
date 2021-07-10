<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use ast;

/**
 * Loads missing declarations
 * @internal - use methods from Parser instead
 */
class ShimFunctions
{
    private const KIND_LOOKUP = [
        ast\AST_ARG_LIST => 'AST_ARG_LIST',
        ast\AST_LIST => 'AST_LIST',
        ast\AST_ARRAY => 'AST_ARRAY',
        ast\AST_ENCAPS_LIST => 'AST_ENCAPS_LIST',
        ast\AST_EXPR_LIST => 'AST_EXPR_LIST',
        ast\AST_STMT_LIST => 'AST_STMT_LIST',
        ast\AST_IF => 'AST_IF',
        ast\AST_SWITCH_LIST => 'AST_SWITCH_LIST',
        ast\AST_CATCH_LIST => 'AST_CATCH_LIST',
        ast\AST_PARAM_LIST => 'AST_PARAM_LIST',
        ast\AST_CLOSURE_USES => 'AST_CLOSURE_USES',
        ast\AST_PROP_DECL => 'AST_PROP_DECL',
        ast\AST_CONST_DECL => 'AST_CONST_DECL',
        ast\AST_CLASS_CONST_DECL => 'AST_CLASS_CONST_DECL',
        ast\AST_NAME_LIST => 'AST_NAME_LIST',
        ast\AST_TRAIT_ADAPTATIONS => 'AST_TRAIT_ADAPTATIONS',
        ast\AST_USE => 'AST_USE',
        ast\AST_TYPE_INTERSECTION => 'AST_TYPE_INTERSECTION',
        ast\AST_TYPE_UNION => 'AST_TYPE_UNION',
        ast\AST_ATTRIBUTE_LIST => 'AST_ATTRIBUTE_LIST',
        ast\AST_MATCH_ARM_LIST => 'AST_MATCH_ARM_LIST',
        ast\AST_NAME => 'AST_NAME',
        ast\AST_CLOSURE_VAR => 'AST_CLOSURE_VAR',
        ast\AST_NULLABLE_TYPE => 'AST_NULLABLE_TYPE',
        ast\AST_FUNC_DECL => 'AST_FUNC_DECL',
        ast\AST_CLOSURE => 'AST_CLOSURE',
        ast\AST_METHOD => 'AST_METHOD',
        ast\AST_ARROW_FUNC => 'AST_ARROW_FUNC',
        ast\AST_CLASS => 'AST_CLASS',
        ast\AST_MAGIC_CONST => 'AST_MAGIC_CONST',
        ast\AST_TYPE => 'AST_TYPE',
        ast\AST_VAR => 'AST_VAR',
        ast\AST_CONST => 'AST_CONST',
        ast\AST_UNPACK => 'AST_UNPACK',
        ast\AST_CAST => 'AST_CAST',
        ast\AST_EMPTY => 'AST_EMPTY',
        ast\AST_ISSET => 'AST_ISSET',
        ast\AST_SHELL_EXEC => 'AST_SHELL_EXEC',
        ast\AST_CLONE => 'AST_CLONE',
        ast\AST_EXIT => 'AST_EXIT',
        ast\AST_PRINT => 'AST_PRINT',
        ast\AST_INCLUDE_OR_EVAL => 'AST_INCLUDE_OR_EVAL',
        ast\AST_UNARY_OP => 'AST_UNARY_OP',
        ast\AST_PRE_INC => 'AST_PRE_INC',
        ast\AST_PRE_DEC => 'AST_PRE_DEC',
        ast\AST_POST_INC => 'AST_POST_INC',
        ast\AST_POST_DEC => 'AST_POST_DEC',
        ast\AST_YIELD_FROM => 'AST_YIELD_FROM',
        ast\AST_GLOBAL => 'AST_GLOBAL',
        ast\AST_UNSET => 'AST_UNSET',
        ast\AST_RETURN => 'AST_RETURN',
        ast\AST_LABEL => 'AST_LABEL',
        ast\AST_REF => 'AST_REF',
        ast\AST_HALT_COMPILER => 'AST_HALT_COMPILER',
        ast\AST_ECHO => 'AST_ECHO',
        ast\AST_THROW => 'AST_THROW',
        ast\AST_GOTO => 'AST_GOTO',
        ast\AST_BREAK => 'AST_BREAK',
        ast\AST_CONTINUE => 'AST_CONTINUE',
        ast\AST_CLASS_NAME => 'AST_CLASS_NAME',
        ast\AST_CLASS_CONST_GROUP => 'AST_CLASS_CONST_GROUP',
        ast\AST_DIM => 'AST_DIM',
        ast\AST_PROP => 'AST_PROP',
        ast\AST_NULLSAFE_PROP => 'AST_NULLSAFE_PROP',
        ast\AST_STATIC_PROP => 'AST_STATIC_PROP',
        ast\AST_CALL => 'AST_CALL',
        ast\AST_CLASS_CONST => 'AST_CLASS_CONST',
        ast\AST_ASSIGN => 'AST_ASSIGN',
        ast\AST_ASSIGN_REF => 'AST_ASSIGN_REF',
        ast\AST_ASSIGN_OP => 'AST_ASSIGN_OP',
        ast\AST_BINARY_OP => 'AST_BINARY_OP',
        ast\AST_ARRAY_ELEM => 'AST_ARRAY_ELEM',
        ast\AST_NEW => 'AST_NEW',
        ast\AST_INSTANCEOF => 'AST_INSTANCEOF',
        ast\AST_YIELD => 'AST_YIELD',
        ast\AST_STATIC => 'AST_STATIC',
        ast\AST_WHILE => 'AST_WHILE',
        ast\AST_DO_WHILE => 'AST_DO_WHILE',
        ast\AST_IF_ELEM => 'AST_IF_ELEM',
        ast\AST_SWITCH => 'AST_SWITCH',
        ast\AST_SWITCH_CASE => 'AST_SWITCH_CASE',
        ast\AST_DECLARE => 'AST_DECLARE',
        ast\AST_PROP_ELEM => 'AST_PROP_ELEM',
        ast\AST_PROP_GROUP => 'AST_PROP_GROUP',
        ast\AST_CONST_ELEM => 'AST_CONST_ELEM',
        ast\AST_USE_TRAIT => 'AST_USE_TRAIT',
        ast\AST_TRAIT_PRECEDENCE => 'AST_TRAIT_PRECEDENCE',
        ast\AST_METHOD_REFERENCE => 'AST_METHOD_REFERENCE',
        ast\AST_NAMESPACE => 'AST_NAMESPACE',
        ast\AST_USE_ELEM => 'AST_USE_ELEM',
        ast\AST_TRAIT_ALIAS => 'AST_TRAIT_ALIAS',
        ast\AST_GROUP_USE => 'AST_GROUP_USE',
        ast\AST_ATTRIBUTE => 'AST_ATTRIBUTE',
        ast\AST_MATCH => 'AST_MATCH',
        ast\AST_MATCH_ARM => 'AST_MATCH_ARM',
        ast\AST_NAMED_ARG => 'AST_NAMED_ARG',
        ast\AST_METHOD_CALL => 'AST_METHOD_CALL',
        ast\AST_NULLSAFE_METHOD_CALL => 'AST_NULLSAFE_METHOD_CALL',
        ast\AST_STATIC_CALL => 'AST_STATIC_CALL',
        ast\AST_CONDITIONAL => 'AST_CONDITIONAL',
        ast\AST_TRY => 'AST_TRY',
        ast\AST_CATCH => 'AST_CATCH',
        ast\AST_FOR => 'AST_FOR',
        ast\AST_FOREACH => 'AST_FOREACH',
    ];

    /**
     * Get a string representation of the AST kind value.
     * @see Parser::
     */
    public static function getKindName(int $kind): string
    {
        $name = self::KIND_LOOKUP[$kind] ?? null;
        if (!$name) {
            throw new \LogicException("Unknown kind $kind");
        }
        return $name;
    }
}
Shim::load();
