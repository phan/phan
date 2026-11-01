<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AnalyzeMethodSkipsInheritedCopiesCapability;
use Phan\PluginV3\ReturnTypeOverrideCapability;

/**
 * A test plugin of the lazy_inherited_methods fixture suite (src/018_plugin_ops.php).
 *
 * Its closures target a root method (\V018Base::make and ::run, declared in a file excluded from analysis) and one copy
 * of them (\C018Child::make and ::run). Analysis::loadMethodPlugins() attaches the closures of the root to every copy;
 * those still lazy then get them when they are created (LazyInheritedMethods::replayOps()), in the same order,
 * except that a copy with its own return type override keeps that one (the own-key rule), and that a method gets
 * one function call analyzer per plugin (the first one attached).
 */
final class LazyOpsTestPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    AnalyzeMethodCapability,
    AnalyzeMethodSkipsInheritedCopiesCapability,
    ReturnTypeOverrideCapability
{
    public function getReturnTypeOverrides(CodeBase $code_base): array
    {
        $make = static function (string $type): Closure {
            return static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args) use ($type): UnionType {
                return UnionType::fromFullyQualifiedPHPDocString($type);
            };
        };
        return [
            '\V018Base::make' => $make('array<int,int>'),
            '\C018Child::make' => $make('array<int,string>'),
        ];
    }

    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        $make = function (string $label, string $suffix): Closure {
            return function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $node = null) use ($label, $suffix): void {
                self::emitIssue(
                    $code_base,
                    $context,
                    'LazyOpsTestPluginCall' . $suffix,
                    'The closure for {STRING_LITERAL} saw a call to {METHOD} with {COUNT} argument(s)',
                    [$label, (string)$function->getFQSEN(), \count($args)]
                );
            };
        };
        return [
            // The copy first: a method gets one closure per plugin, so C018Child::run keeps this one
            '\C018Child::run' => $make('C018Child::run', 'Child'),
            '\V018Base::run' => $make('V018Base::run', 'Root'),
        ];
    }

    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            // An inherited copy (AnalyzeMethodSkipsInheritedCopiesCapability)
            return;
        }
        if ($method->getName() === 'reportedByLazyOpsTestPlugin') {
            self::emitIssue(
                $code_base,
                $method->getContext(),
                'LazyOpsTestPluginMethod',
                'Analyzed the declaration of {METHOD}',
                [(string)$method->getFQSEN()]
            );
        }
    }
}

return new LazyOpsTestPlugin();
