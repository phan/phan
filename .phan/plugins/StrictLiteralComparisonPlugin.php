<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Type;
use Phan\Language\Type\IntType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Parse\ParseVisitor;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin warns about using `==`/`!=` for string literals.
 * For the vast majority of projects, this will have too many false positives to use.
 * Only use this if you are sure there are no weak type comparisons.
 * (e.g. strings from inputs/dbs used as numbers, floats compared to integers)
 *
 * Also see StrictComparisonPlugin for warning about comparing objects.
 */
class StrictLiteralComparisonPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability
{
    /**
     * @return string - The name of the visitor that will be called (formerly analyzeNode)
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return StrictLiteralComparisonVisitor::class;
    }
}

/**
 * Warns about using weak comparison operators when both sides are possibly objects
 */
class StrictLiteralComparisonVisitor extends PluginAwarePostAnalysisVisitor
{
    public const ComparisonNotStrictForScalar = 'PhanPluginComparisonNotStrictForScalar';
    public const ComparisonNotStrictForScalarSafe = 'PhanPluginComparisonNotStrictForScalarSafe';

    private const ISSUE_MESSAGE = 'Expected strict equality check when comparing {TYPE} to {TYPE} in {CODE}';
    private const ISSUE_MESSAGE_SAFE = 'Expected strict equality check when comparing {TYPE} to {TYPE} in {CODE} (a strict check gives the same result for these types)';
    private const ZERO_INT = 0;
    private const EMPTY_STRING = '';

    /**
     * @param Node $node
     * A node of kind ast\AST_BINARY_OP to analyze
     *
     * @override
     */
    public function visitBinaryOp(Node $node): void
    {
        if ($node->flags === ast\flags\BINARY_IS_NOT_EQUAL || $node->flags === ast\flags\BINARY_IS_EQUAL) {
            $this->analyzeEqualityCheck($node);
        }
    }

    /**
     * @param Node $node
     * A node of kind ast\AST_BINARY_OP for `==`/`!=` to analyze
     */
    private function analyzeEqualityCheck(Node $node): void
    {
        ['left' => $left, 'right' => $right] = $node->children;
        $left_is_const = ParseVisitor::isConstExpr($left, ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION);
        $right_is_const = ParseVisitor::isConstExpr($right, ParseVisitor::CONSTANT_EXPRESSION_FORBID_NEW_EXPRESSION);
        if ($left_is_const === $right_is_const) {
            return;
        }
        $const_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $left_is_const ? $left : $right
        );
        if ($const_type->isEmpty()) {
            return;
        }
        foreach ($const_type->getTypeSet() as $type) {
            if (!($type instanceof IntType || $type instanceof StringType)) {
                return;
            }
        }
        $scalar_value = $const_type->asSingleScalarValueOrNullOrSelf();
        $other_type = UnionTypeVisitor::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $left_is_const ? $right : $left
        );
        if (self::isStrictComparisonEquivalent($scalar_value, $other_type)) {
            $issue_type = self::ComparisonNotStrictForScalarSafe;
            $message = self::ISSUE_MESSAGE_SAFE;
        } else {
            $issue_type = self::ComparisonNotStrictForScalar;
            $message = self::ISSUE_MESSAGE;
        }
        $left_type = $left_is_const ? $const_type : $other_type;
        $right_type = $left_is_const ? $other_type : $const_type;
        self::emitPluginIssue(
            $this->code_base,
            $this->context,
            $issue_type,
            $message,
            [
                $left_type,
                $right_type,
                ASTReverter::toShortString($node),
            ]
        );
    }

    /**
     * Returns true if `===`/`!==` would give the same result as `==`/`!=` for the literal `$scalar_value`
     * and an operand of type `$other_type`: a non-zero int literal compared to `int` or `?int`
     * (`null == 1` is false), or a non-empty, non-numeric string literal compared to `string` or `?string`
     * (`null == 'a'` is false, and PHP 8 compares two strings numerically only if both are numeric).
     *
     * Comparisons to `0`, `''` and numeric strings such as `'10'` or `'1e1'`, or with operands of other or unknown
     * types, may rely on type juggling and are left to the caller to report as PhanPluginComparisonNotStrictForScalar.
     *
     * @param UnionType|int|string|float|bool|null $scalar_value the literal, or the UnionType if there is no single literal value
     */
    private static function isStrictComparisonEquivalent(
        UnionType|int|string|float|bool|null $scalar_value,
        UnionType $other_type
    ): bool {
        if (\is_int($scalar_value)) {
            return $scalar_value !== self::ZERO_INT && $other_type->isIntTypeOrNull();
        }
        if (!\is_string($scalar_value)
            || $scalar_value === self::EMPTY_STRING
            || \is_numeric($scalar_value)
            || $other_type->isEmpty()
        ) {
            return false;
        }
        return $other_type->allTypesMatchCallback(static function (Type $type): bool {
            return $type instanceof StringType || $type instanceof NullType;
        });
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new StrictLiteralComparisonPlugin();
