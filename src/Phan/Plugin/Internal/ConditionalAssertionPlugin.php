<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ConditionVisitor\NarrowsToTypeCondition;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Comment\ConditionalAssertion;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\ConditionalCallCapability;

/**
 * Narrows the types of the arguments of a call that is used as a condition, for the functions and methods
 * with `(at)phan-assert-if-true`, `(at)phan-assert-if-false` (or the phpstan-/psalm- forms) in their doc comment.
 * Also the type of the object that a method is called on, and of its properties, for an assertion about `$this` or `$this->prop`.
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
        // The assertions about `$this` and `$this->prop`: about the object that the method is called on
        $receiver_assertions = [];
        foreach ($assertion_list as $assertion) {
            if ($assertion->param_name === 'this' && $function instanceof Method && !$function->isStatic()) {
                if (!self::dependsOnCall($assertion)) {
                    $receiver_assertions[] = $assertion;
                }
                continue;
            }
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
            if (self::dependsOnCall($assertion)) {
                continue;
            }
            $indexed_assertions[] = [$i, $assertion];
        }
        if (!$indexed_assertions && !$receiver_assertions) {
            return null;
        }
        /**
         * @param list<Node|string|int|float> $args
         */
        return static function (CodeBase $code_base, Context $context, FunctionInterface $called_function, Node $node, array $args, bool $is_negated) use ($indexed_assertions, $receiver_assertions): Context {
            foreach ($indexed_assertions as [$i, $assertion]) {
                $arg = $args[$i] ?? null;
                if (!($arg instanceof Node)) {
                    continue;
                }
                $context = self::applyAssertion($code_base, $context, $arg, $assertion, !$is_negated);
            }
            // (`$item?->isReady()` is also falsey when $item is null and nothing was called)
            if ($receiver_assertions && !($is_negated && $node->kind === \ast\AST_NULLSAFE_METHOD_CALL)) {
                [$receiver, $receiver_property_name] = self::receiverOfCall($node);
                if ($receiver !== null) {
                    foreach ($receiver_assertions as $assertion) {
                        if ($receiver_property_name !== null) {
                            // `$this->item->isReady()`: an assertion about `$this` is about `$this->item`.
                            // (One about `$this->prop` would be about `$this->item->prop`, which is not narrowed)
                            if ($assertion->property_name === null) {
                                $context = self::applyPropertyAssertion($code_base, $context, $receiver, $receiver_property_name, $assertion, !$is_negated, null);
                            }
                            continue;
                        }
                        $context = $assertion->property_name !== null
                            ? self::applyPropertyAssertion($code_base, $context, $receiver, $assertion->property_name, $assertion, !$is_negated, $called_function)
                            : self::applyAssertion($code_base, $context, $receiver, $assertion, !$is_negated);
                    }
                }
            }
            return $context;
        };
    }

    /**
     * @return bool whether the type of the assertion depends on the call (a template type, or `static`): nothing is assumed then.
     */
    private static function dependsOnCall(ConditionalAssertion $assertion): bool
    {
        // (`static` can be nested, as in `static[]` or `Box<static>`)
        return $assertion->union_type->hasTemplateTypeRecursive() || \preg_match('/\bstatic\b/', $assertion->union_type->__toString()) > 0;
    }

    /**
     * @param Node $node the node of a call
     * @return array{0:?Node,1:?string} the object that the method is called on, if its type can be narrowed:
     * the node of a variable (`$this->isReady()`, `$item->isReady()`), and for a property of a variable
     * (`$this->item->isReady()`, `$holder->item->isReady()`) the name of that property as well.
     */
    private static function receiverOfCall(Node $node): array
    {
        if ($node->kind !== \ast\AST_METHOD_CALL && $node->kind !== \ast\AST_NULLSAFE_METHOD_CALL) {
            return [null, null];
        }
        $receiver = $node->children['expr'];
        $property_name = null;
        if ($receiver instanceof Node && $receiver->kind === \ast\AST_PROP) {
            $property_name = $receiver->children['prop'];
            $receiver = $receiver->children['expr'];
            if (!\is_string($property_name)) {
                return [null, null];
            }
        }
        if (!($receiver instanceof Node) || $receiver->kind !== \ast\AST_VAR || !\is_string($receiver->children['name'])) {
            return [null, null];
        }
        return [$receiver, $property_name];
    }

    /**
     * @param bool $result whether the call is assumed to have returned a truthy value
     * @return ?bool whether the assertion says that its target has the type (true) or does not have it (false) for that result,
     * or null if it says nothing for that result
     */
    private static function hasTypeForResult(ConditionalAssertion $assertion, bool $result): ?bool
    {
        $has_type = !$assertion->is_negated;
        if ($result !== $assertion->if_true) {
            if (!$assertion->applies_to_other_branch) {
                return null;
            }
            $has_type = !$has_type;
        }
        return $has_type;
    }

    /**
     * Narrows the type of the property of the object that the method was called on: `$this->prop` or `$variable->prop`.
     *
     * @param Node $receiver the node of the variable
     * @param bool $result whether the call is assumed to have returned a truthy value
     * @param ?FunctionInterface $method the method with the assertion, for an assertion about `$this->prop`: it is about the property
     * with that name of the class that declares the method. Null for an assertion about `$this` that is applied to a property.
     */
    private static function applyPropertyAssertion(CodeBase $code_base, Context $context, Node $receiver, string $property_name, ConditionalAssertion $assertion, bool $result, ?FunctionInterface $method): Context
    {
        $has_type = self::hasTypeForResult($assertion, $result);
        if ($has_type === null) {
            return $context;
        }
        $variable_name = $receiver->children['name'];
        if (!\is_string($variable_name)) {
            return $context;
        }
        $old_type = self::typeOfAccessibleProperty($code_base, $context, $receiver, $property_name, $method);
        if ($old_type === null) {
            return $context;
        }
        $new_type = $has_type
            ? NarrowsToTypeCondition::narrow($code_base, $context, $old_type, $assertion->union_type)
            : ConditionVisitor::excludeMatchingTypes($code_base, $old_type, $assertion->union_type);
        if ($new_type->isEmpty()) {
            // (Nothing is left, e.g. `!null` for a property that is only known to be null: nothing is assumed)
            return $context;
        }
        if ($variable_name === 'this') {
            return $context->withThisPropertySetToTypeByName($property_name, $new_type);
        }
        return $context->withVariablePropertySetToTypeByName($variable_name, $property_name, $new_type);
    }

    /**
     * @return ?UnionType the type that the property of the variable has here, or null if the code here cannot read the property
     * (a private property of another class: an assertion about it has no use for the caller), or if it is not known which property it is.
     */
    private static function typeOfAccessibleProperty(CodeBase $code_base, Context $context, Node $receiver, string $property_name, ?FunctionInterface $method): ?UnionType
    {
        try {
            $accessing_class = $context->isInClassScope() ? $context->getClassFQSEN() : null;
            $class_list = \iterator_to_array(
                UnionTypeVisitor::unionTypeFromNode($code_base, $context, $receiver)->asClassList($code_base, $context),
                false
            );
            if (\count($class_list) !== 1) {
                return null;
            }
            $class = $class_list[0];
            if (!$class->hasPropertyWithName($code_base, $property_name)) {
                return null;
            }
            $property = $class->getPropertyByName($code_base, $property_name);
            if (!$property->isAccessibleFromClass($code_base, $accessing_class)) {
                return null;
            }
            if ($method instanceof Method && !self::isSamePropertyForMethod($code_base, $method, $property)) {
                return null;
            }
            // The type that reading the property gives here: with what was inferred about it, `static` and the template types
            // of the object, and the types of the property of a parent class.
            $property_node = new Node(\ast\AST_PROP, 0, ['expr' => $receiver, 'prop' => $property_name], $receiver->lineno);
            // The code with the call does not read the property: nothing is reported for this read (a deprecated property, an internal one).
            $old_is_discarding_issues = Issue::$is_discarding_issues;
            Issue::$is_discarding_issues = true;
            // The types of nodes are cached in the context by the object id of the node, and this node is gone after the read
            // (the next node that is made can get its id): it is read with a copy of the context, of which the cache is not kept.
            $read_context = clone($context);
            $read_context->clearCachedUnionTypes();
            try {
                $type = UnionTypeVisitor::unionTypeFromNode($code_base, $read_context, $property_node, false);
            } finally {
                Issue::$is_discarding_issues = $old_is_discarding_issues;
            }
            // (The object may not say what a template type is)
            return $type->hasTemplateTypeRecursive() ? null : $type;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param Method $method a method with an assertion about `$this->name`
     * @param Property $property the property with that name of the object that the method is called on
     * @return bool whether that is the property that the method means: not when the class that declares the method
     * has a private property with that name, and the class of the object declares its own.
     */
    private static function isSamePropertyForMethod(CodeBase $code_base, Method $method, Property $property): bool
    {
        $method_class_fqsen = $method->getSelfContext()->getClassFQSEN();
        if (!$code_base->hasClassWithFQSEN($method_class_fqsen)) {
            return true;
        }
        $method_class = $code_base->getClassByFQSEN($method_class_fqsen);
        $name = $property->getName();
        if (!$method_class->hasPropertyWithName($code_base, $name)) {
            return true;
        }
        $property_of_method_class = $method_class->getPropertyByName($code_base, $name);
        // (A property that is not private is the same one when a class that extends this one declares it again)
        return !$property_of_method_class->isPrivate() || $property_of_method_class->getDefiningFQSEN() === $property->getDefiningFQSEN();
    }

    /**
     * @param bool $result whether the call is assumed to have returned a truthy value
     */
    private static function applyAssertion(CodeBase $code_base, Context $context, Node $arg, ConditionalAssertion $assertion, bool $result): Context
    {
        $has_type = self::hasTypeForResult($assertion, $result);
        if ($has_type === null) {
            return $context;
        }
        if ($has_type) {
            return ConditionVisitor::updateToHaveNarrowedType($code_base, $context, $arg, $assertion->union_type);
        }
        return ConditionVisitor::updateToNotHaveType($code_base, $context, $arg, $assertion->union_type);
    }
}
