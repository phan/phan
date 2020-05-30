<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Plugin\Internal\VariableTracker\VariableTrackerVisitor;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class CompactPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability
{

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,array,?Node):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $analyzers;
    }

    /**
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,array,?Node):void>
     */
    private static function getAnalyzeFunctionCallClosuresStatic(): array
    {
        /**
         * @param list<Node|int|float|string> $args
         */
        $compact_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_func,
            array $args,
            ?Node $node
        ): void {
            $check_variable_usage = static function (string $variable_name, $arg = null) use ($code_base, $context, $node): void {
                VariableTrackerVisitor::recordDynamicVariableUse($variable_name, $node);
                if (!$context->getScope()->hasVariableWithName($variable_name)) {
                    Issue::maybeEmitWithParameters(
                        $code_base,
                        $context,
                        Variable::chooseIssueForUndeclaredVariable($context, $variable_name),
                        $arg->lineno ?? $context->getLineNumberStart(),
                        [$variable_name],
                        IssueFixSuggester::suggestVariableTypoFix($code_base, $context, $variable_name)
                    );
                }
            };
            foreach ($args as $arg) {
                if (\is_string($arg)) {
                    // The argument is the variable name.
                    $check_variable_usage($arg);
                    // NOTE: compact is **not** aware of superglobals
                    continue;
                }
                if (!($arg instanceof Node)) {
                    continue;
                }
                $value = (new ContextNode($code_base, $context, $arg))->getEquivalentPHPValue(ContextNode::RESOLVE_DEFAULT & ~ContextNode::RESOLVE_ARRAY_KEYS);
                if (\is_string($value)) {
                    $check_variable_usage($value, $arg);
                    continue;
                }
                if (\is_array($value)) {
                    foreach ($value as $value_element) {
                        if (\is_string($value_element)) {
                            $check_variable_usage($value_element, $arg);
                        }
                    }
                }
            }
        };
        return [
            'compact' => $compact_callback,
        ];
    }
}
