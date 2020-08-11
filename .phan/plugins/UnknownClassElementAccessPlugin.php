<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for accesses to unknown class elements that can't be type checked.
 *
 * - E.g. `$unknown->someMethod(null)`
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * UnknownClassElementAccessPlugin hooks into two events:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed in post-order
 * - finalizeProcess
 *   This is called after the other forms of analysis are finished running.
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
    PostAnalyzeNodeCapability,
    FinalizeProcessCapability
{
    public const UnknownObjectMethodCall = 'PhanPluginUnknownObjectMethodCall';
    /**
     * @var array<string,list<array{0:Context,1:string, 2:UnionType}>>
     * Map from file name+line+node hash to the union type to a closure to emit the issue
     */
    private static $deferred_unknown_method_issues = [];

    /**
     * @var array<string,true>
     * Set of file name+line+node hashes where the union type is known.
     */
    private static $known_method_set = [];

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return UnknownClassElementAccessVisitor::class;
    }

    private static function generateKey(FileRef $context, int $lineno, string $node_string): string
    {
        // Sadly, the node can either be from the parse phase or any analysis phase, so we can't use spl_object_id.
        return $context->getFile() . ':' . $lineno . ':' . sha1($node_string);
    }

    /**
     * Emit an issue if the object of the method call isn't found later/earlier
     */
    public static function deferEmittingMethodIssue(Context $context, Node $node, UnionType $union_type): void
    {
        $node_string = ASTReverter::toShortString($node);
        $key = self::generateKey($context, $node->lineno, $node_string);
        if (isset(self::$known_method_set[$key])) {
            return;
        }
        self::$deferred_unknown_method_issues[$key][] = [(clone $context)->withLineNumberStart($node->lineno), $node_string, $union_type];
    }

    /**
     * Prevent this plugin from warning about $node_string at this file and line
     */
    public static function blacklistMethodIssue(Context $context, Node $node): void
    {
        $node_string = ASTReverter::toShortString($node);
        $key = self::generateKey($context, $node->lineno, $node_string);
        self::$known_method_set[$key] = true;
        unset(self::$deferred_unknown_method_issues[$key]);
    }

    public function finalizeProcess(CodeBase $code_base): void
    {
        foreach (self::$deferred_unknown_method_issues as $issues) {
            foreach ($issues as [$context, $node_string, $union_type]) {
                $this->emitIssue(
                    $code_base,
                    $context,
                    self::UnknownObjectMethodCall,
                    'Phan could not infer any class/interface types for the object of the method call {CODE} - inferred a type of {TYPE}',
                    [
                        $node_string,
                        $union_type->isEmpty() ? '(empty union type)' : $union_type
                    ]
                );
            }
        }
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in post-order.
 */
class UnknownClassElementAccessVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @param Node $node a node of kind ast\AST_NULLSAFE_METHOD_CALL, representing a call to an instance method
     */
    public function visitNullsafeMethodCall(Node $node): void
    {
        $this->visitMethodCall($node);
    }

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
                UnknownClassElementAccessPlugin::blacklistMethodIssue($this->context, $node);
                return;
            }
        }
        if (Issue::shouldSuppressIssue($this->code_base, $this->context, UnknownClassElementAccessPlugin::UnknownObjectMethodCall, $node->lineno, [])) {
            return;
        }
        UnknownClassElementAccessPlugin::deferEmittingMethodIssue($this->context, $node, $union_type);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnknownClassElementAccessPlugin();
