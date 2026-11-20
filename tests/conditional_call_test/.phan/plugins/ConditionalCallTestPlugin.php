<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\Type;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\ConditionalCallCapability;

/**
 * The test plugin of the conditional call fixture suite (see test.sh).
 *
 * Its closures make their effect visible in the output of `'@phan-debug-var $x';` statements instead of emitting issues:
 * - narrow: if the first argument is a variable, it becomes `string` when the call is truthy, and loses `string` when it is falsey;
 * - record_seen: like narrow, and also sets `$seen` to the type that the first argument had when the closure ran;
 * - record_args: like narrow, and also sets `$args_seen` to a description of the normalized arguments;
 * - mark: sets `$checked` to `true` when the call is truthy and to `false` when it is falsey.
 */
final class ConditionalCallTestPlugin extends PluginV3 implements ConditionalCallCapability
{
    public function getConditionalCallClosures(CodeBase $code_base): array
    {
        /**
         * @param array<int,Node|string|int|float> $args
         */
        $narrow = static function (CodeBase $code_base, Context $context, FunctionInterface $function, Node $call, array $args, bool $is_negated): Context {
            return self::narrowFirstArgument($context, $args, $is_negated);
        };
        /**
         * @param array<int,Node|string|int|float> $args
         */
        $record_seen = static function (CodeBase $code_base, Context $context, FunctionInterface $function, Node $call, array $args, bool $is_negated): Context {
            $variable = self::getVariableOfArgument($context, $args[0] ?? null);
            if ($variable) {
                $context = self::withVariable($context, 'seen', $variable->getUnionType());
            }
            return self::narrowFirstArgument($context, $args, $is_negated);
        };
        /**
         * @param array<int,Node|string|int|float> $args
         */
        $record_args = static function (CodeBase $code_base, Context $context, FunctionInterface $function, Node $call, array $args, bool $is_negated): Context {
            $parts = [];
            foreach ($args as $i => $arg) {
                $parts[] = $i . ':' . ASTReverter::toShortString($arg);
            }
            $context = self::withVariable($context, 'args_seen', LiteralStringType::instanceForValue(implode('; ', $parts), false)->asPHPDocUnionType());
            return self::narrowFirstArgument($context, $args, $is_negated);
        };
        /**
         * @param array<int,Node|string|int|float> $args
         */
        $mark = static function (CodeBase $code_base, Context $context, FunctionInterface $function, Node $call, array $args, bool $is_negated): Context {
            $type = $is_negated ? FalseType::instance(false) : TrueType::instance(false);
            return self::withVariable($context, 'checked', $type->asPHPDocUnionType());
        };
        return [
            // 001_functions.php
            '\is_safe' => $narrow,
            '\is_ready' => $mark,
            // 002_namespaces.php
            '\CondNS\ns_safe' => $narrow,
            // 004_methods.php
            '\SafeChecker::check' => $narrow,
            '\Validator::isValid' => $narrow,
            '\ValidatorA::accepts' => $narrow,
            '\ValidatorB::accepts' => $narrow,
            '\BaseChecker::verify' => $narrow,
            '\ExternalBase::externalVerify' => $narrow,
            '\ReflectionEnum::hasCase' => $mark,
            // 005_internal_and_args.php
            '\in_array' => $record_seen,
            '\named_args' => $record_args,
            // 006_chaining.php (ConditionalCallSecondTestPlugin also has a closure for it)
            '\chained' => $narrow,
            // 007_unresolved.php
            '\int_check' => $narrow,
            '\PrivateChecker::check' => $narrow,
            '\FinalWithoutMethod::unused' => $narrow,
        ];
    }

    /**
     * @param Node|string|int|float|null $arg
     */
    private static function getVariableOfArgument(Context $context, $arg): ?Variable
    {
        if (!$arg instanceof Node || $arg->kind !== ast\AST_VAR) {
            return null;
        }
        $name = $arg->children['name'];
        if (!is_string($name) || !$context->getScope()->hasVariableWithName($name)) {
            return null;
        }
        return $context->getScope()->getVariableByName($name);
    }

    /**
     * @param array<int,Node|string|int|float> $args
     */
    private static function narrowFirstArgument(Context $context, array $args, bool $is_negated): Context
    {
        $variable = self::getVariableOfArgument($context, $args[0] ?? null);
        if (!$variable) {
            return $context;
        }
        $variable = clone $variable;
        if ($is_negated) {
            $variable->setUnionType($variable->getUnionType()->makeFromFilter(static function (Type $type): bool {
                return !$type instanceof StringType;
            }));
        } else {
            $variable->setUnionType(StringType::instance(false)->asPHPDocUnionType());
        }
        return $context->withScopeVariable($variable);
    }

    private static function withVariable(Context $context, string $name, UnionType $type): Context
    {
        return $context->withScopeVariable(new Variable($context, $name, $type, 0));
    }
}

return new ConditionalCallTestPlugin();
