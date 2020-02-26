<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for accesses to unknown class elements that can't be type checked.
 *
 * - E.g. `$unknown->someMethod(null)`
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * UnknownClassElementAccessPlugin hooks into one event:
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
class UnknownClassElementAccessPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability
{

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return UnknownClassElementAccessVisitor::class;
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in post-order.
 */
class UnknownClassElementAccessVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @param Node $node a node of kind ast\AST_METHOD_CALL, representing a call to an instance method
     */
    public function visitMethodCall(Node $node): void
    {
        try {
            // Fetch the list of valid classes, and warn about any undefined classes.
            // (We have more specific issue types such as PhanNonClassMethodCall below, don't emit PhanTypeExpected*)
            $union_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
        } catch (Exception $_) {
            // Phan should already throw for this
            return;
        }
        foreach ($union_type->getTypeSet() as $type) {
            if ($type->isObjectWithKnownFQSEN()) {
                return;
            }
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginUnknownObjectMethodCall',
            'Phan could not infer any class/interface types for the object of the method call {CODE} - inferred a type of {TYPE}',
            [
                ASTReverter::toShortString($node),
                $union_type->isEmpty() ? '(no types)' : $union_type
            ]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnknownClassElementAccessPlugin();
