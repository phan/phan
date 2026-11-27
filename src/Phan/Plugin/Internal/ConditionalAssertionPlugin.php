<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\Analysis\ConditionVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Comment\ConditionalAssertion;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\PluginV3;
use Phan\PluginV3\ConditionalCallCapability;

/**
 * Narrows the types of the arguments of a call that is used as a condition, for the functions and methods
 * with `(at)phan-assert-if-true`, `(at)phan-assert-if-false` (or the phpstan-/psalm- forms) in their doc comment.
 *
 * e.g. `(at)phpstan-assert-if-true \WP_Error $thing` on `is_wp_error($thing)`:
 * `$x` is a `WP_Error` in the body of `if (is_wp_error($x))`, and not a `WP_Error` after `if (is_wp_error($x)) { return; }`.
 */
final class ConditionalAssertionPlugin extends PluginV3 implements ConditionalCallCapability
{
    /**
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,Node,list<Node|string|int|float>,bool):Context>
     * @override
     */
    public function getConditionalCallClosures(CodeBase $code_base): array
    {
        $result = [];
        foreach ($code_base->getFunctionMap() as $function) {
            $closure = self::createClosure($code_base, $function);
            if ($closure) {
                $result[$function->getFQSEN()->__toString()] = $closure;
            }
        }
        foreach ($code_base->getMethodSetWithoutLazyEntries() as $method) {
            // The copy of a method that a class inherits gets the closure of the method it was inherited from.
            if ($method instanceof Method && $code_base->isInheritedCopyWithSameSignature($method)) {
                continue;
            }
            $closure = self::createClosure($code_base, $method);
            if ($closure) {
                $result[$method->getFQSEN()->__toString()] = $closure;
            }
        }
        return $result;
    }

    /**
     * @return ?Closure(CodeBase,Context,FunctionInterface,Node,list<Node|string|int|float>,bool):Context
     */
    private static function createClosure(CodeBase $code_base, FunctionInterface $function): ?Closure
    {
        $comment = $function->getComment();
        if (!$comment) {
            return null;
        }
        $assertion_list = $comment->getConditionalAssertionList();
        if (!$assertion_list) {
            return null;
        }
        // Pairs of the index of the parameter and the assertion
        $indexed_assertions = [];
        foreach ($assertion_list as $assertion) {
            $i = null;
            foreach ($function->getParameterList() as $param_index => $parameter) {
                if ($parameter->getName() === $assertion->param_name) {
                    $i = $param_index;
                    break;
                }
            }
            if ($i === null) {
                Issue::maybeEmit(
                    $code_base,
                    $function->getContext(),
                    Issue::CommentParamAssertionWithoutRealParam,
                    $function->getContext()->getLineNumberStart(),
                    $assertion->param_name,
                    $function->getNameForIssue()
                );
                continue;
            }
            // (`static` can be nested, as in `static[]` or `Box<static>`)
            if ($assertion->union_type->hasTemplateTypeRecursive() || \preg_match('/\bstatic\b/', $assertion->union_type->__toString())) {
                // The type depends on the call: nothing is assumed.
                continue;
            }
            $indexed_assertions[] = [$i, $assertion];
        }
        if (!$indexed_assertions) {
            return null;
        }
        /**
         * @param list<Node|string|int|float> $args
         */
        return static function (CodeBase $code_base, Context $context, FunctionInterface $unused_function, Node $unused_node, array $args, bool $is_negated) use ($indexed_assertions): Context {
            foreach ($indexed_assertions as [$i, $assertion]) {
                $arg = $args[$i] ?? null;
                if (!($arg instanceof Node)) {
                    continue;
                }
                $context = self::applyAssertion($code_base, $context, $arg, $assertion, !$is_negated);
            }
            return $context;
        };
    }

    /**
     * @param bool $result whether the call is assumed to have returned a truthy value
     */
    private static function applyAssertion(CodeBase $code_base, Context $context, Node $arg, ConditionalAssertion $assertion, bool $result): Context
    {
        $has_type = !$assertion->is_negated;
        if ($result !== $assertion->if_true) {
            if (!$assertion->applies_to_other_branch) {
                return $context;
            }
            $has_type = !$has_type;
        }
        if ($has_type) {
            return ConditionVisitor::updateToHaveNarrowedType($code_base, $context, $arg, $assertion->union_type);
        }
        return ConditionVisitor::updateToNotHaveType($code_base, $context, $arg, $assertion->union_type);
    }
}
