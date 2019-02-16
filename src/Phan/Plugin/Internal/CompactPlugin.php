<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCallCapability;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class CompactPlugin extends PluginV2 implements
    AnalyzeFunctionCallCapability
{

    /**
     * @param CodeBase $code_base @phan-unused-param
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $analyzers;
    }

    /**
     * @return array<string,\Closure>
     */
    private static function getAnalyzeFunctionCallClosuresStatic() : array
    {
        /**
         * @param array<int,Node|int|float|string> $args
         * @return void
         */
        $compact_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $unused_func,
            array $args
        ) {
            $maybe_emit_issue = static function (string $variable_name, $arg = null) use ($code_base, $context) {
                if (!$context->getScope()->hasVariableWithName($variable_name)) {
                    Issue::maybeEmitWithParameters(
                        $code_base,
                        $context,
                        Issue::UndeclaredVariable,
                        $arg->lineno ?? $context->getLineNumberStart(),
                        [$variable_name],
                        IssueFixSuggester::suggestVariableTypoFix($code_base, $context, $variable_name)
                    );
                }
            };
            foreach ($args as $arg) {
                if (\is_string($arg)) {
                    $maybe_emit_issue($arg);
                    // NOTE: compact is **not** aware of superglobals
                    continue;
                }
                if (!($arg instanceof Node)) {
                    continue;
                }
                $value = (new ContextNode($code_base, $context, $arg))->getEquivalentPHPValue(ContextNode::RESOLVE_DEFAULT & ~ContextNode::RESOLVE_ARRAY_KEYS);
                if (\is_string($value)) {
                    $maybe_emit_issue($value, $arg);
                    continue;
                }
                if (\is_array($value)) {
                    foreach ($value as $value_element) {
                        if (\is_string($value_element)) {
                            $maybe_emit_issue($value_element, $arg);
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
