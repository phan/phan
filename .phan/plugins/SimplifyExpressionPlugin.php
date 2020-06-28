<?php

declare(strict_types=1);

use ast\flags;
use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Type\BoolType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for expressions that can be simplified based on the union types.
 * This is similar to `DuplicateExpressionPlugin`, which generally does not check union types.
 *
 * - E.g. `$x > 0 ? true : false` can be simplified to `$x > 0`
 *
 * Note that in PHP 7, many functions did not yet have real return types
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * DuplicateExpressionPlugin hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed in post-order
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class SimplifyExpressionPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability
{

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return SimplifyExpressionVisitor::class;
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * that can be simplified, and is called on nodes in post-order.
 */
class SimplifyExpressionVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * Returns true if all types are strictly subtypes of `bool`
     */
    protected static function isDefinitelyBool(UnionType $union_type): bool
    {
        $real_type_set = $union_type->getRealTypeSet();
        if (!$real_type_set) {
            return false;
        }
        foreach ($real_type_set as $type) {
            if (!$type->isInBoolFamily() || $type->isNullable()) {
                return false;
            }
            if (count($real_type_set) === 1) {
                // If the expression is `true` or `false`, assume that ExtendedDependentReturnPlugin or some other plugin
                // inferred a literal value instead of the expression being guaranteed to be a boolean.
                // (e.g. `strpos(SOME_CONST, 'val') === false`)
                //
                // TODO: Could check if the expression is a call and what the getRealReturnType is for that function.
                return $type instanceof BoolType;
            }
        }
        return true;
    }

    /**
     * @param Node|string|int|float|null $node
     * @return ?bool if this is the name of a boolean, the value. Otherwise, returns null.
     */
    private static function getBoolConst($node): ?bool
    {
        if (!$node instanceof Node) {
            return null;
        }
        if ($node->kind !== ast\AST_CONST) {
            return null;
        }
        // @phan-suppress-next-line PhanPossiblyUndeclaredProperty, PhanPartialTypeMismatchArgumentInternal
        switch (strtolower($node->children['name']->children['name'])) {
            case 'false':
                return false;
            case 'true':
                return true;
        }
        return null;
    }

    /**
     * @param Node $node
     * A ternary operation node of kind ast\AST_CONDITIONAL to analyze
     * @override
     */
    public function visitConditional(Node $node): void
    {
        // Detect conditions such as`$bool ?: null` or `$bool ? true : false`
        $true_node = $node->children['true'];
        $value_if_true = $true_node !== null ? self::getBoolConst($true_node) : true;
        if (!is_bool($value_if_true)) {
            return;
        }
        $value_if_false = self::getBoolConst($node->children['false']);
        if ($value_if_false !== !$value_if_true) {
            return;
        }
        $this->suggestBoolSimplification($node, $node->children['cond'], !$value_if_true);
    }

    /**
     * @param Node|string|int|float $inner_expr
     */
    private function suggestBoolSimplification(Node $node, $inner_expr, bool $negate): void
    {
        if (!self::isDefinitelyBool(UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $inner_expr))) {
            return;
        }
        // TODO: Use redundant condition detection helper methods to handle loops
        $new_inner_repr = ASTReverter::toShortString($inner_expr);
        if ($negate) {
            $new_inner_repr = "!($new_inner_repr)";
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginSimplifyExpressionBool',
            '{CODE} can probably be simplified to {CODE}',
            [
                ASTReverter::toShortString($node),
                $new_inner_repr,
            ]
        );
    }

    /**
     * @param Node $node
     * A binary op node of kind ast\AST_BINARY_OP to analyze
     * @override
     */
    public function visitBinaryOp(Node $node): void
    {
        $is_negated_assertion = false;
        switch ($node->flags) {
            case flags\BINARY_IS_NOT_IDENTICAL:
            case flags\BINARY_IS_NOT_EQUAL:
            case flags\BINARY_BOOL_XOR:
                $is_negated_assertion = true;
            case flags\BINARY_IS_EQUAL:
            case flags\BINARY_IS_IDENTICAL:
                ['left' => $left_node, 'right' => $right_node] = $node->children;
                $left_const = self::getBoolConst($left_node);
                if (is_bool($left_const)) {
                    // E.g. `$x === true` can be simplified to `$x`
                    $this->suggestBoolSimplification($node, $right_node, $left_const === $is_negated_assertion);
                    return;
                }
                $right_const = self::getBoolConst($right_node);
                if (is_bool($right_const)) {
                    $this->suggestBoolSimplification($node, $left_node, $right_const === $is_negated_assertion);
                }
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new SimplifyExpressionPlugin();
