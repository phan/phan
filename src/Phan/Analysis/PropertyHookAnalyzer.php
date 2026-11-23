<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Flags;
use Phan\Language\Element\Property;
use Phan\Language\Element\PropertyHook;

/**
 * Checks the properties of a class against the properties of the same name of its ancestors, for the rules of
 * final properties and property hooks (PHP 8.4+):
 *
 * - A final property cannot be overridden.
 * - A final hook cannot be overridden.
 * - A non-abstract class must implement the abstract hooks of the abstract properties of its ancestors and the properties of its interfaces.
 * - A hook with #[\Override] must override a hook, or a backed property.
 *
 * NOTE: This must be run after the properties of the ancestors were added to the class.
 */
class PropertyHookAnalyzer
{
    /**
     * Check the properties of $class against the properties of the same name of its ancestors.
     */
    public static function analyzePropertyHooks(CodeBase $code_base, Clazz $class): void
    {
        if ($class->isPHPInternal()) {
            return;
        }
        $class->hydrate($code_base);
        $inherited_class_list = $class->getAncestorClassList($code_base);
        $must_implement_hooks = !$class->isAbstract() && !$class->isTrait() && !$class->isInterface();

        foreach ($class->getPropertyMap($code_base) as $property) {
            if ($property->isDynamicOrFromPHPDoc() || $property->getPhanFlags() & (Flags::IS_ENUM_PROPERTY | Flags::IS_FROM_REQUIRE_ANNOTATION)) {
                continue;
            }
            $is_declared_by_class = $property->getRealDefiningFQSEN() === $property->getFQSEN();

            // The abstract hooks that are not implemented, by hook name
            $abstract_hooks = [];
            if (self::isAbstractDeclaration($code_base, $property)) {
                foreach ([$property->getGetHook(), $property->getSetHook()] as $hook) {
                    if ($hook && !$hook->hasBody()) {
                        $abstract_hooks[$hook->getName()] = $property;
                    }
                }
            }
            // The hooks with #[\Override] of the property, by hook name, until the overridden hook is found.
            $override_hooks = [];
            if ($is_declared_by_class) {
                foreach ([$property->getGetHook(), $property->getSetHook()] as $hook) {
                    if ($hook && $hook->hasOverrideAttribute()) {
                        $override_hooks[$hook->getName()] = $hook;
                    }
                }
            }

            foreach ($inherited_class_list as $inherited_class) {
                $inherited_class->hydrate($code_base);
                $inherited_property = $inherited_class->getPropertyMap($code_base)[$property->getName()] ?? null;
                if (!$inherited_property
                    || $inherited_property->isPrivate()
                    || $inherited_property->isDynamicOrFromPHPDoc()
                    || $inherited_property->getPhanFlagsHasState(Flags::IS_FROM_REQUIRE_ANNOTATION)
                ) {
                    continue;
                }
                foreach (['get' => $inherited_property->getGetHook(), 'set' => $inherited_property->getSetHook()] as $hook_name => $inherited_hook) {
                    // A backed property can be read and written without a hook, so a hook of either kind overrides something.
                    if ($inherited_hook || !$inherited_property->isVirtual()) {
                        unset($override_hooks[$hook_name]);
                    }
                }
                if ($inherited_property->getRealDefiningFQSEN() === $property->getRealDefiningFQSEN()) {
                    continue;
                }
                if (!$is_declared_by_class || $inherited_class->isTrait()) {
                    // Checked for the class that declares the property.
                    // (The properties of a used trait are part of the class itself)
                    continue;
                }
                self::checkFinal($code_base, $property, $inherited_property);
                if (self::isAbstractDeclaration($code_base, $inherited_property)) {
                    foreach ([$inherited_property->getGetHook(), $inherited_property->getSetHook()] as $inherited_hook) {
                        if ($inherited_hook && !$inherited_hook->hasBody() && !self::implementsHook($property, $inherited_hook->getName())) {
                            $abstract_hooks[$inherited_hook->getName()] ??= $inherited_property;
                        }
                    }
                }
            }

            if ($must_implement_hooks) {
                foreach ($abstract_hooks as $hook_name => $abstract_property) {
                    Issue::maybeEmit(
                        $code_base,
                        $class->getContext(),
                        Issue::ClassContainsAbstractMethod,
                        $class->getFileRef()->getLineNumberStart(),
                        (string)$class->getFQSEN(),
                        self::hookName($abstract_property, $hook_name),
                        $abstract_property->getFileRef()->getFile(),
                        $abstract_property->getFileRef()->getLineNumberStart()
                    );
                }
            }
            foreach ($override_hooks as $hook) {
                Issue::maybeEmit(
                    $code_base,
                    $hook->getContext(),
                    Issue::CommentOverrideOnNonOverrideMethod,
                    $hook->getContext()->getLineNumberStart(),
                    self::hookName($property, $hook->getName())
                );
            }
        }
    }

    /**
     * Emits issues if $property overrides the final property $inherited_property, or one of its final hooks.
     */
    private static function checkFinal(CodeBase $code_base, Property $property, Property $inherited_property): void
    {
        if ($inherited_property->getFlags() & ast\flags\MODIFIER_FINAL) {
            Issue::maybeEmit(
                $code_base,
                $property->getContext(),
                Issue::AccessOverridesFinalProperty,
                $property->getFileRef()->getLineNumberStart(),
                $property->asPropertyFQSENString(),
                self::realDeclaration($code_base, $inherited_property)->asPropertyFQSENString(),
                $inherited_property->getFileRef()->getFile(),
                $inherited_property->getFileRef()->getLineNumberStart()
            );
            return;
        }
        foreach (['get' => $property->getGetHook(), 'set' => $property->getSetHook()] as $hook_name => $hook) {
            $inherited_hook = $hook_name === 'get' ? $inherited_property->getGetHook() : $inherited_property->getSetHook();
            if ($hook instanceof PropertyHook && $inherited_hook && $inherited_hook->isFinal()) {
                Issue::maybeEmit(
                    $code_base,
                    $hook->getContext(),
                    Issue::PropertyHookFinalOverride,
                    $hook->getContext()->getLineNumberStart(),
                    self::realDeclaration($code_base, $inherited_property)->asPropertyFQSENString(),
                    $hook_name,
                    $inherited_hook->getContext()->getFile(),
                    $inherited_hook->getContext()->getLineNumberStart()
                );
            }
        }
    }

    /**
     * @return bool whether $property is the declaration of an abstract property, or of a property of an interface
     */
    private static function isAbstractDeclaration(CodeBase $code_base, Property $property): bool
    {
        if ($property->getFlags() & ast\flags\MODIFIER_ABSTRACT) {
            return true;
        }
        $class_fqsen = $property->getRealDefiningFQSEN()->getFullyQualifiedClassName();
        return $code_base->hasClassWithFQSEN($class_fqsen) && $code_base->getClassByFQSEN($class_fqsen)->isInterface();
    }

    /**
     * @return bool whether the non-abstract $property provides the hook $hook_name:
     * it declares the hook, or it is backed, so that it can be read and written without the hook.
     */
    private static function implementsHook(Property $property, string $hook_name): bool
    {
        $hook = $hook_name === 'get' ? $property->getGetHook() : $property->getSetHook();
        if ($hook) {
            return $hook->hasBody();
        }
        return !$property->isVirtual();
    }

    private static function realDeclaration(CodeBase $code_base, Property $property): Property
    {
        $fqsen = $property->getRealDefiningFQSEN();
        return $code_base->hasPropertyWithFQSEN($fqsen) ? $code_base->getPropertyByFQSEN($fqsen) : $property;
    }

    /**
     * @return string e.g. `\NS\MyClass::$prop::get`
     */
    private static function hookName(Property $property, string $hook_name): string
    {
        $fqsen = $property->getRealDefiningFQSEN();
        return $fqsen->getFullyQualifiedClassName() . '::$' . $fqsen->getName() . '::' . $hook_name;
    }
}
