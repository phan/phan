<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for possible debugging statements.
 */
class RemoveDebugStatementPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    PostAnalyzeNodeCapability
{
    const ISSUE_GROUP = 'PhanPluginRemoveDebugAny';

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return RemoveDebugStatementVisitor::class;
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string, Closure(CodeBase,Context,Func,array):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        $warn_remove_debug_call = static function (CodeBase $code_base, Context $context, FunctionInterface $function): void {
            self::emitIssue(
                $code_base,
                $context,
                'PhanPluginRemoveDebugCall',
                'Saw call to {FUNCTION} for debugging',
                [(string)$function->getFQSEN()]
            );
        };
        /**
         * @param list<Node|string|int|float> $unused_args the nodes for the arguments to the invocation
         */
        $always_debug_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $unused_args
        ) use ($warn_remove_debug_call): void {
            if (self::shouldSuppressDebugIssues($code_base, $context)) {
                return;
            }
            $warn_remove_debug_call($code_base, $context, $function);
        };
        /**
         * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
         * Based on DependentReturnTypeOverridePlugin check
         */
        $var_export_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use ($warn_remove_debug_call): void {
            if (self::shouldSuppressDebugIssues($code_base, $context)) {
                return;
            }

            if (count($args) >= 2) {
                $result = (new ContextNode($code_base, $context, $args[1]))->getEquivalentPHPScalarValue();
                // @phan-suppress-next-line PhanSuspiciousTruthyString
                if (is_object($result) || $result) {
                    return;
                }
            }
            $warn_remove_debug_call($code_base, $context, $function);
        };

        /**
         * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
         */
        $fwrite_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use ($warn_remove_debug_call): void {
            $file = $args[0] ?? null;
            // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
            if (!$file instanceof Node || $file->kind !== ast\AST_CONST || !in_array($file->children['name']->children['name'], ['STDOUT', 'STDERR'], true)) {
                // Could resolve the constant, but low priority
                return;
            }
            if (self::shouldSuppressDebugIssues($code_base, $context)) {
                return;
            }

            $warn_remove_debug_call($code_base, $context, $function);
        };

        return [
            'var_dump'              => $always_debug_callback,
            'printf'                => $always_debug_callback,
            'debug_print_backtrace' => $always_debug_callback,
            'debug_zval_dump'       => $always_debug_callback,
            // Warn for these functions unless the second argument is false
            'var_export'            => $var_export_callback,
            'print_r'               => $var_export_callback,

            // check for STDOUT/STDERR
            'fwrite'                => $fwrite_callback,
            'fprintf'               => $fwrite_callback,
        ];
    }

    /**
     * Returns true if any debug issue should be suppressed
     */
    public static function shouldSuppressDebugIssues(CodeBase $code_base, Context $context): bool
    {
        return Issue::shouldSuppressIssue($code_base, $context, RemoveDebugStatementPlugin::ISSUE_GROUP, $context->getLineNumberStart(), []);
    }
}

/**
 * Analyzes node kinds that are associated with debugging
 */
class RemoveDebugStatementVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @param Node $node a node of kind ast\AST_ECHO
     */
    public function visitPrint(Node $node): void
    {
        $this->visitEcho($node);
    }

    /**
     * @param Node $node a node which echoes or prints
     */
    public function visitEcho(Node $node): void
    {
        if (RemoveDebugStatementPlugin::shouldSuppressDebugIssues($this->code_base, $this->context)) {
            return;
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginRemoveDebugEcho',
            "Saw output expression/statement in {CODE}",
            [ASTReverter::toShortString($node)]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new RemoveDebugStatementPlugin();
