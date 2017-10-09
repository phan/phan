<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\Analysis\ArgumentType;
use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Type\ClosureType;
use Phan\Language\UnionType;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use Phan\PluginV2;
use ast\Node;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Refactor this.
 */
final class ClosureReturnTypeOverridePlugin extends PluginV2 implements ReturnTypeOverrideCapability {

    /**
     * @return \Closure[]
     */
    private static function getReturnTypeOverridesStatic(CodeBase $code_base) : array
    {
        $call_user_func_callback = static function(
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) : UnionType {
            $element_types = new UnionType();
            if (\count($args) < 1) {
                return $element_types;
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
            if (\count($function_like_list) === 0) {
                return $element_types;
            }
            $arguments = \array_slice($args, 1);
            foreach ($function_like_list as $function_like) {
                ArgumentType::analyzeForCallback(
                    $function_like, $arguments, $context, $code_base, null
                );
                if ($function_like->hasDependentReturnType()) {
                    $element_types->addUnionType($function_like->getDependentReturnType($code_base, $context, $arguments));
                } else {
                    $element_types->addUnionType($function_like->getUnionType());
                }
            }
            return $element_types;
        };
        $call_user_func_array_callback = static function(
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) : UnionType {
            $element_types = new UnionType();
            if (\count($args) < 2) {
                return $element_types;
            }
            // Currently, only analyze calls of the form call_user_func_array(callable expression, [$arg1, $arg2...])
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
            if (\count($function_like_list) === 0) {
                return $element_types;
            }
            $arg_array_node = $args[1];
            if (($arg_array_node instanceof Node) && $arg_array_node->kind === \ast\AST_ARRAY) {
                $arguments = [];
                // TODO: Sanity check keys.
                foreach ($arg_array_node->children as $child) {
                    $arguments[] = $child->children['value'];
                }
            } else {
                $arguments = null;
            }
            $element_types = new UnionType();
            foreach ($function_like_list as $function_like) {
                if ($arguments !== null) {
                    ArgumentType::analyzeForCallback(
                        $function_like, $arguments, $context, $code_base, null
                    );
                }
                if ($arguments !== null && $function_like->hasDependentReturnType()) {
                    $element_types->addUnionType($function_like->getDependentReturnType($code_base, $context, $arguments));
                } else {
                    $element_types->addUnionType($function_like->getUnionType());
                }
            }
            return $element_types;
        };
        $from_callable_callback = static function(
            CodeBase $code_base,
            Context $context,
            Method $unused_method,
            array $args
        ) : UnionType {
            if (\count($args) < 1) {
                return ClosureType::instance(false)->asUnionType();
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
            if (\count($function_like_list) === 0) {
                return ClosureType::instance(false)->asUnionType();
            }
            $closure_types = new UnionType();
            foreach ($function_like_list as $function_like) {
                $closure_types->addType(ClosureType::instanceWithClosureFQSEN($function_like->getFQSEN()));
            }
            return $closure_types;
        };
        return [
            // call
            'call_user_func'            => $call_user_func_callback,
            'forward_static_call'       => $call_user_func_callback,
            'call_user_func_array'      => $call_user_func_array_callback,
            'forward_static_call_array' => $call_user_func_array_callback,
            'Closure::fromCallable'     => $from_callable_callback,
        ];
    }

    /**
     * @return \Closure[]
     */
    public function getReturnTypeOverrides(CodeBase $code_base) : array
    {
        return self::getReturnTypeOverridesStatic($code_base);
    }
}
