<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\NonEmptyStringType;
use Phan\Language\Type\StringType;
use Phan\PluginV3;
use Phan\PluginV3\ConditionalCallCapability;

/**
 * The second test plugin of the conditional call fixture suite (see test.sh), loaded after ConditionalCallTestPlugin.
 *
 * Its closure for `chained()` runs after the closure of ConditionalCallTestPlugin and is passed the Context it returned:
 * when the call is truthy, it changes the first argument to `non-empty-string` if that closure made it `string`,
 * and to `float` otherwise.
 */
final class ConditionalCallSecondTestPlugin extends PluginV3 implements ConditionalCallCapability
{
    public function getConditionalCallClosures(CodeBase $code_base): array
    {
        return [
            /**
             * @param array<int,Node|string|int|float> $args
             */
            '\chained' => static function (CodeBase $code_base, Context $context, FunctionInterface $function, Node $call, array $args, bool $is_negated): Context {
                $arg = $args[0] ?? null;
                if ($is_negated || !$arg instanceof Node || $arg->kind !== ast\AST_VAR) {
                    return $context;
                }
                $name = $arg->children['name'];
                if (!is_string($name) || !$context->getScope()->hasVariableWithName($name)) {
                    return $context;
                }
                $variable = clone $context->getScope()->getVariableByName($name);
                $type = $variable->getUnionType()->isType(StringType::instance(false)) ? NonEmptyStringType::instance(false) : FloatType::instance(false);
                $variable->setUnionType($type->asPHPDocUnionType());
                return $context->withScopeVariable($variable);
            },
        ];
    }
}

return new ConditionalCallSecondTestPlugin();
