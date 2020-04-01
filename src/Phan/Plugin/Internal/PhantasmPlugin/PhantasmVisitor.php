<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\PhantasmPlugin;

use ast;
use ast\Node;
use Exception;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\Language\Context;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;

use function in_array;
use function strtolower;

/**
 * Replaces expressions with simplified expressions using Phan's information about the entire codebase.
 */
final class PhantasmVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * Visits a node of kind ast\AST_CLASS_CONST to replace the class constant with its value, in cases where it would help performance.
     *
     * For example, opcache can't optimize away the code or check for `if (ADifferentClass::DEBUG) { ... }`, because the class is in a different file.
     * @suppress PhanUndeclaredProperty
     */
    public function visitClassConst(Node $node): void
    {
        if (!isset($node->tolerant_ast_node)) {
            return;
        }
        if (!self::isOptimizableClassNameReference($node->children['class'])) {
            return;
        }
        // CLI::printToStderr("TODO: Support " . ASTReverter::toShortString($node) . "\n");
        try {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClassConst();
        } catch (Exception $_) {
            // Swallow any other types of exceptions. We'll log the errors
            // elsewhere.
            return;
        }
        if ($constant->isPHPInternal()) {
            // Don't optimize stubs for php constants or the values of php constants
            return;
        }
        $original_node = $constant->getNodeForValue();
        if ($original_node === null) {
            return;
        }
        if (!$constant->isPublic()) {
            // For now, only optimize uses of public class constants.
            return;
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        if (!$this->isSafeNodeToSubstitute($constant->getContext(), $original_node)) {
            return;
        }
        $class_fqsen = $constant->getClassFQSEN();
        if ($this->code_base->hasClassWithFQSEN($class_fqsen->withAlternateId(1))) {
            // Give up on copies of the class.
            return;
        }
        // CLI::printToStderr("TODO: Replace {$constant} with " . ASTReverter::toShortString($constant->getNodeForValue()) . "\n");

        // TODO: If the original tolerant_ast_node had no parenthesis, omit the parenthesis here.
        $node->tolerant_ast_node->string_replacement = ' /* ' . ASTReverter::toShortString($node) . ' */ (' . ASTReverter::toShortString($original_node) . ')';
    }

    /**
     * Check if the value node of a constant can be safely substituted in other files
     * @param Node|string|float|int|null $value_node
     */
    public function isSafeNodeToSubstitute(Context $context, $value_node): bool
    {
        if (!$value_node instanceof Node) {
            // TODO: floats might lose precision when converted back to strings by phantasm. Use the original expression based on the parent node?
            return \is_int($value_node) || \is_string($value_node);  // not null
        }
        // TODO: Guard against infinite recursion if AST_CLASS_CONST gets supported
        switch ($value_node->kind) {
            /* TODO: Convert class references to fully qualified class references in the output
            case ast\AST_CLASS_CONST:
                $name_node = $value_node->children['name'];
                if ($name_node instanceof Node && $name_node->kind === ast\AST_NAME) {
                    if (!self::isSafeNodeToSubstitute($name_node)) {
                        return false;
                    }
                }
                return false;
             */
            case ast\AST_CONST:
                return $this->isSafeNodeToSubstitute($context, $value_node->children['name']);
            case ast\AST_NAME:
                // TODO: Support other constants, convert those to fully qualified form before substituting
                // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                return \in_array(strtolower($value_node->children['name']), ['true', 'false', 'null'], true);
            case ast\AST_UNARY_OP:
            case ast\AST_BINARY_OP:
                foreach ($value_node->children as $c) {
                    if (!self::isSafeNodeToSubstitute($context, $c)) {
                        return false;
                    }
                }
        }
        return false;
    }

    /**
     * Check if the reference to a class name can be optimized.
     * @param Node|string|int|float $node
     */
    public static function isOptimizableClassNameReference($node): bool
    {
        if (!$node instanceof Node || $node->kind !== ast\AST_NAME) {
            return false;
        }
        // Closures can be rebound. For now, treat references to self:: as unsafe to optimize
        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
        return !in_array(strtolower($node->children['name']), ['self', 'static', 'parent'], true);
    }
}
