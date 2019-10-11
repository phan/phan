<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for uses of in_array where $strict is not true.
 * This is specific to some coding styles - Some code may need to use weak comparisons to work properly.
 *
 * This is used in Phan for the following reasons:
 *
 * 1. To avoid accidentally using weak comparison on objects, which may cause issues such as stack overflow when comparing a Type to itself (Type has reference cycles).
 * 2. To avoid mistakes due to weak type comparison.
 * 3. For slightly better performance.
 *
 * This implements the following helpers:
 *
 * - getAnalyzeFunctionCallClosures
 *   This method returns a map from function/method FQSEN to closures that are called on invocations of those closures.
 */
class StrictComparisonPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    PostAnalyzeNodeCapability
{
    const ComparisonNotStrictInCall         = 'PhanPluginComparisonNotStrictInCall';
    const ComparisonObjectEqualityNotStrict = 'PhanPluginComparisonObjectEqualityNotStrict';
    const ComparisonObjectOrdering          = 'PhanPluginComparisonObjectOrdering';

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string, Closure(CodeBase,Context,Func,array):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        /**
         * @return Closure(CodeBase,Context,Func,array):void
         */
        $make_callback = static function (int $index, string $index_name, int $min_args) : Closure {
            /**
             * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                Func $func,
                array $args
            ) use (
                $index,
                $index_name,
                $min_args
) : void {
                if (count($args) < $min_args) {
                    return;
                }
                $strict_node = $args[$index] ?? null;
                if ($strict_node instanceof Node) {
                    $type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $strict_node)->asSingleScalarValueOrNullOrSelf();
                    if ($type === true) {
                        return;
                    } elseif ($type === false) {
                        return;
                    }
                }
                self::emitPluginIssue(
                    $code_base,
                    $context,
                    self::ComparisonNotStrictInCall,
                    "Expected {FUNCTION} to be called with a $index_name argument for {PARAMETER} (either true or false)",
                    [$func->getName(), '$strict']
                );
            };
        };
        // More functions might be added in the future
        $always_warn_third_not_strict = $make_callback(2, 'third', 0);

        return [
            'in_array' => $always_warn_third_not_strict,
            'array_search' => $always_warn_third_not_strict,
        ];
    }

    /**
     * @return string - The name of the visitor that will be called (formerly analyzeNode)
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return StrictComparisonVisitor::class;
    }
}

/**
 * Warns about using weak comparison operators when both sides are possibly objects
 */
class StrictComparisonVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @param Node $node
     * A node of kind ast\AST_BINARY_OP to analyze
     *
     * @override
     */
    public function visitBinaryOp(Node $node) : void
    {
        switch ($node->flags) {
            case ast\flags\BINARY_IS_EQUAL:
            case ast\flags\BINARY_IS_NOT_EQUAL:
                if ($this->bothSidesArePossiblyObjects($node)) {
                    // TODO: Also check arrays of objects?
                    $this->emit(
                        StrictComparisonPlugin::ComparisonObjectEqualityNotStrict,
                        'Saw a weak equality check on possible object types {TYPE} and {TYPE} in {CODE}',
                        [
                            UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['left']),
                            UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['right']),
                            ASTReverter::toShortString($node),
                        ]
                    );
                }
                break;
            case ast\flags\BINARY_IS_GREATER_OR_EQUAL:
            case ast\flags\BINARY_IS_SMALLER_OR_EQUAL:
            case ast\flags\BINARY_IS_GREATER:
            case ast\flags\BINARY_IS_SMALLER:
            case ast\flags\BINARY_SPACESHIP:
                if ($this->bothSidesArePossiblyObjects($node)) {
                    $this->emit(
                        StrictComparisonPlugin::ComparisonObjectOrdering,
                        'Using comparison operator on possible object types {TYPE} and {TYPE} in {CODE}',
                        [
                            UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['left']),
                            UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['right']),
                            ASTReverter::toShortString($node),
                        ]
                    );
                }
                break;
        }
    }

    private function bothSidesArePossiblyObjects(Node $node) : bool
    {
        ['left' => $left, 'right' => $right] = $node->children;
        if (!($left instanceof Node) || !($right instanceof Node)) {
            return false;
        }
        return UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $left)->hasObjectTypes() &&
               UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $right)->hasObjectTypes();
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new StrictComparisonPlugin();
