<?php declare(strict_types=1);

namespace Phan\AST;

use ast\flags;
use ast\Node;
use Closure;
use Error;

/**
 * Utilities for inferring the value of operations in the analyzed code.
 */
class InferValue
{
    /**
     * @param Closure():(array|int|string|float|bool|null) $cb
     * @return Node|array|int|string|float|bool|null
     * @throws Error should be handled by caller, e.g. for `+[]`.
     */
    private static function evalSuppressingErrors(Closure $cb)
    {
        return @\with_disabled_phan_error_handler($cb);
    }

    /**
     * Compute result of a binary operator
     * @param int|string|float|bool|null $left left hand side of operation
     * @param int|string|float|bool|null $right right hand side of operation
     * @param int $flags the flags on the node
     * @return Node|array|int|string|float|bool|null
     *   Node is returned to indicate that the result could not be computed
     * @throws Error that should be handled by caller, e.g. for `+[]`.
     */
    public static function computeBinaryOpResult($left, $right, int $flags)
    {
        // Don't make errors in the analyzed code crash Phan (e.g. converting arrays to strings).
        return self::evalSuppressingErrors(/** @return Node|array|int|string|float|bool|null */ static function () use ($left, $right, $flags) {
            switch ($flags) {
                case flags\BINARY_CONCAT:
                    return $left . $right;
                case flags\BINARY_ADD:
                    return $left + $right;
                case flags\BINARY_SUB:
                    return $left - $right;
                case flags\BINARY_POW:
                    return $left ** $right;
                case flags\BINARY_MUL:
                    return $left * $right;
                case flags\BINARY_DIV:
                    return $left / $right;
                case flags\BINARY_BOOL_AND:
                    return $left && $right;
                case flags\BINARY_BOOL_OR:
                    return $left || $right;
                case flags\BINARY_BOOL_XOR:
                    return $left xor $right;
                case flags\BINARY_IS_IDENTICAL:
                    return $left === $right;
                case flags\BINARY_IS_NOT_IDENTICAL:
                    return $left !== $right;
                case flags\BINARY_IS_EQUAL:
                    return $left == $right;
                case flags\BINARY_IS_NOT_EQUAL:
                    return $left != $right;
                case flags\BINARY_IS_SMALLER:
                    return $left < $right;
                case flags\BINARY_IS_SMALLER_OR_EQUAL:
                    return $left <= $right;
                case flags\BINARY_IS_GREATER:
                    return $left > $right;
                case flags\BINARY_IS_GREATER_OR_EQUAL:
                    return $left >= $right;
                case flags\BINARY_SPACESHIP:
                    return $left <=> $right;
                case flags\BINARY_COALESCE:
                    return $left ?? $right;
                default:
                    return new Node();
            }
        });
    }

    /**
     * Compute result of a unary operator for a PhanPluginBothLiteralsBinaryOp warning
     * @param int|array|string|float|bool|null $operand operand of operation
     * @param int $flags the flags on the node
     * @return Node|array|int|string|float|bool|null
     *   Node is returned to indicate that the result could not be computed
     * @throws Error that should be handled by caller, e.g. for `+[]`.
     */
    public static function computeUnaryOpResult($operand, int $flags)
    {
        // Don't make errors in the analyzed code crash Phan (e.g. converting arrays to strings).
        return self::evalSuppressingErrors(/** @return Node|array|int|string|float|bool|null */ static function () use ($operand, $flags) {
            switch ($flags) {
                case flags\UNARY_BOOL_NOT:
                    return !$operand;
                case flags\UNARY_BITWISE_NOT:
                    // TODO: Check for acting on arrays
                    return ~$operand;
                case flags\UNARY_MINUS:
                    return -$operand;
                case flags\UNARY_PLUS:
                    return +$operand;
                case flags\UNARY_SILENCE:
                    return $operand;
                default:
                    return new Node();
            }
        });
    }
}
