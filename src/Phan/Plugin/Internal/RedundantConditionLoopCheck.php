<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast;
use ast\flags;
use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Parse\ParseVisitor;

use function is_string;

/**
 * Utilities to check for for loops such as `for (...; $i < MAXIMUM; decrease($i))`
 */
class RedundantConditionLoopCheck
{
    /**
     * @param Node|int|float|string|null $cond_node
     * @return associative-array<int|string,bool>
     *
     * Maps varName to a boolean
     * True if this is asserting a value is less than something, false if it's asserting the value is greater than something
     * @internal
     */
    public static function extractComparisonDirections($cond_node, bool $negate = false): array
    {
        if (!$cond_node instanceof Node) {
            return [];
        }
        switch ($cond_node->kind) {
            case ast\AST_UNARY_OP:
                return self::extractComparisonDirectionsFromUnaryOp($cond_node, $negate);
            case ast\AST_BINARY_OP:
                return self::extractComparisonDirectionsFromBinaryOp($cond_node, $negate);
        }
        return [];
    }

    /**
     * @return associative-array<int|string,bool>
     */
    private static function extractComparisonDirectionsFromUnaryOp(Node $cond_node, bool $negate): array
    {
        if ($cond_node->flags === flags\UNARY_BOOL_NOT) {
            $negate = !$negate;
        } elseif ($cond_node->flags === flags\UNARY_SILENCE) {
            // do nothing
        } else {
            return [];
        }

        return self::extractComparisonDirections($cond_node->children['expr'], $negate);
    }

    /**
     * @return associative-array<int|string,bool>
     */
    private static function extractComparisonDirectionsFromBinaryOp(Node $cond_node, bool $negate): array
    {
        ['left' => $left_node, 'right' => $right_node] = $cond_node->children;
        switch ($cond_node->flags) {
            case flags\BINARY_IS_SMALLER:
            case flags\BINARY_IS_SMALLER_OR_EQUAL:
                // @phan-suppress-next-line PhanUnusedVariable FIXME This is used in the fallthrough; Phan shouldn't warn
                $negate = !$negate;
                // fallthrough
            case flags\BINARY_IS_GREATER:
            case flags\BINARY_IS_GREATER_OR_EQUAL:
                if (is_string($name = self::getVarName($left_node))) {
                    // e.g. if $i < 10
                    if (ParseVisitor::isConstExpr($right_node)) {
                        return [$name => $negate];
                    }
                } elseif (is_string($name = self::getVarName($right_node))) {
                    // e.g. if 10 < $i
                    if (ParseVisitor::isConstExpr($left_node)) {
                        return [$name => !$negate];
                    }
                }
                return [];
            case flags\BINARY_BOOL_AND:
            case flags\BINARY_BOOL_OR:
                if ($negate xor ($cond_node->flags === flags\BINARY_BOOL_OR)) {
                    // Don't analyze `if (!(x && y))` or `if (x || y)`
                    return [];
                }
                $left_flags = self::extractComparisonDirections($left_node, $negate);
                $right_flags = self::extractComparisonDirections($right_node, $negate);
                foreach ($left_flags as $key => $value) {
                    if (($right_flags[$key] ?? $value) !== $value) {
                        unset($left_flags[$key]);
                        unset($right_flags[$key]);
                    }
                }
                return $left_flags + $right_flags;
        }
        return [];
    }

    /**
     * Extract the directions in which this for loop increments variables
     * @param Node|int|string|float|null $cond_node
     * @return associative-array<int|string,bool>
     */
    public static function extractIncrementDirections(CodeBase $code_base, Context $context, $cond_node): array
    {
        if (!$cond_node instanceof Node) {
            return [];
        }
        switch ($cond_node->kind) {
            case ast\AST_EXPR_LIST:
                $result = [];
                foreach ($cond_node->children as $c) {
                    $result += self::extractIncrementDirections($code_base, $context, $c);
                }
                return $result;
            case ast\AST_PRE_INC:
            case ast\AST_POST_INC:
            case ast\AST_PRE_DEC:
            case ast\AST_POST_DEC:
                $var_name = self::getVarName($cond_node->children['var']);
                if (is_string($var_name)) {
                    return [$var_name => \in_array($cond_node->kind, [ast\AST_PRE_INC, ast\AST_POST_INC], true)];
                }
                return [];
            case ast\AST_ASSIGN_OP:
                $var_name = self::getVarName($cond_node->children['var']);
                if (!is_string($var_name)) {
                    return [];
                }
                switch ($cond_node->flags) {
                    case ast\flags\BINARY_SUB:
                        $is_subtraction = true;
                        break;
                    case ast\flags\BINARY_ADD:
                        $is_subtraction = false;
                        break;
                    default:
                        // Unable to determine
                        return [];
                }

                return self::extractIncrementDirectionForAssignOp($code_base, $context, $var_name, $cond_node->children['expr'], $is_subtraction);
            case ast\AST_ASSIGN:
                $var_name = self::getVarName($cond_node->children['var']);
                if (!is_string($var_name)) {
                    return [];
                }
                $operation = $cond_node->children['expr'];
                if (!($operation instanceof Node && $operation->kind === ast\AST_BINARY_OP)) {
                    return [];
                }
                switch ($operation->flags) {
                    case ast\flags\BINARY_SUB:
                        $is_subtraction = true;
                        break;
                    case ast\flags\BINARY_ADD:
                        $is_subtraction = false;
                        break;
                    default:
                        // Unable to determine
                        return [];
                }
                // handle $i = $i +- count;
                $left_var_name = self::getVarName($operation->children['left']);
                if (is_string($left_var_name)) {
                    if ($left_var_name !== $var_name) {
                        return [];
                    }
                    return self::extractIncrementDirectionForAssignOp($code_base, $context, $var_name, $operation->children['right'], $is_subtraction);
                }
                // handle $i = count + $i; but not $i = count - $i
                if ($is_subtraction) {
                    return [];
                }
                $right_var_name = self::getVarName($operation->children['right']);
                if ($right_var_name !== $var_name) {
                    return [];
                }

                return self::extractIncrementDirectionForAssignOp($code_base, $context, $var_name, $operation->children['left'], $is_subtraction);
        }
        return [];
    }

    /**
     * @param Node|string|int|float|null $expr
     * @return associative-array<int|string,bool>
     */
    private static function extractIncrementDirectionForAssignOp(CodeBase $code_base, Context $context, string $var_name, $expr, bool $is_subtraction): array
    {
        // TODO: Extract constants
        if ($expr instanceof Node) {
            try {
                $expr = (UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr, false))->asSingleScalarValueOrNullOrSelf();
            } catch (\Exception $_) {
                return [];
            }
        }

        if (\is_numeric($expr) && (float)$expr) {
            return [$var_name => $expr > 0 xor $is_subtraction];
        }
        return [];
    }

    /**
     * @param Node|mixed $node
     */
    private static function getVarName($node): ?string
    {
        if ($node instanceof Node && $node->kind === ast\AST_VAR) {
            $name = $node->children['name'];
            if (\is_string($name)) {
                return $name;
            }
        }
        return null;
    }
}
