<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Property;

/**
 * Utilities for calls of the hook of the parent class's property from a property hook (PHP 8.4+):
 * `parent::$prop::get()` and `parent::$prop::set($value)`.
 *
 * These are nodes of kind ast\AST_STATIC_CALL with a 'class' of kind ast\AST_STATIC_PROP,
 * but they do not access a static property.
 * PHP only allows them in the hook with the same name of the property with the same name.
 */
final class ParentPropertyHookCall
{
    /**
     * @param Node $node a node of kind ast\AST_STATIC_CALL
     * @return bool whether this is `parent::$prop::get()` in the get hook of `$prop`, or `parent::$prop::set(...)` in its set hook
     */
    public static function isParentHookCall(Context $context, Node $node): bool
    {
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node) || $class_node->kind !== ast\AST_STATIC_PROP) {
            return false;
        }
        $hook_scope = $context->getScope()->getPropertyHookScopeOrNull();
        if (!$hook_scope) {
            return false;
        }
        $parent_node = $class_node->children['class'];
        if (!($parent_node instanceof Node) || $parent_node->kind !== ast\AST_NAME) {
            return false;
        }
        $class_name = $parent_node->children['name'];
        $method_name = $node->children['method'];
        return \is_string($class_name)
            && \strcasecmp($class_name, 'parent') === 0
            && $class_node->children['prop'] === $hook_scope->getHookPropertyFQSEN()->getName()
            && \is_string($method_name)
            && \strcasecmp($method_name, $hook_scope->getHookName()) === 0;
    }

    /**
     * @param Node $node a node for which isParentHookCall() is true
     * @return ?Property the property of the parent class, if the parent class and the property are known
     */
    public static function getParentProperty(CodeBase $code_base, Context $context, Node $node): ?Property
    {
        if (!$context->isInClassScope()) {
            return null;
        }
        $class = $context->getClassInScope($code_base);
        if (!$class->hasParentType()) {
            return null;
        }
        $parent_fqsen = $class->getParentClassFQSEN();
        if (!$code_base->hasClassWithFQSEN($parent_fqsen)) {
            return null;
        }
        $parent_class = $code_base->getClassByFQSEN($parent_fqsen);
        $prop_name = $node->children['class']->children['prop'];
        if (!\is_string($prop_name) || !$parent_class->hasPropertyWithName($code_base, $prop_name)) {
            return null;
        }
        return $parent_class->getPropertyByName($code_base, $prop_name);
    }
}
