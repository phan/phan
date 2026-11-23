<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Flags;
use Phan\Language\Element\Property;

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
        if ($class->isPHPInternal() || $class->isTrait()) {
            // The properties of a trait are checked for the classes that use it.
            return;
        }
        $class->hydrate($code_base);
        $parent_class_list = self::getParentClassList($code_base, $class);
        $interface_list = [];
        foreach ($class->getAncestorClassListRecursive($code_base) as $ancestor) {
            if ($ancestor->isInterface()) {
                $interface_list[] = $ancestor;
            }
        }
        $must_implement_hooks = !$class->isAbstract() && !$class->isInterface();

        foreach ($class->getPropertyMap($code_base) as $name => $_) {
            $name = (string)$name;
            $property = self::getDeclaration($code_base, $class, $name);
            // The declarations of the parent classes that $class inherits or overrides, starting with the closest one.
            $parent_property_list = [];
            foreach ($parent_class_list as $parent_class) {
                $parent_property = self::getDeclaration($code_base, $parent_class, $name);
                if (!$parent_property) {
                    continue;
                }
                if ($parent_property->isPrivate()) {
                    // Not inherited, and it hides the properties of the classes above it.
                    break;
                }
                $parent_property_list[] = $parent_property;
            }
            $interface_property_list = [];
            foreach ($interface_list as $interface) {
                $interface_property = self::getDeclaration($code_base, $interface, $name);
                if ($interface_property) {
                    $interface_property_list[] = $interface_property;
                }
            }
            if ($property) {
                if ($parent_property_list) {
                    self::checkFinal($code_base, $class, $property, $parent_property_list);
                }
                self::checkOverrideAttributes($code_base, $class, $property, $parent_property_list, $interface_property_list);
            }
            if ($must_implement_hooks) {
                $declaration_list = $property ? [$property, ...$parent_property_list] : $parent_property_list;
                self::checkAbstractHooks($code_base, $class, $declaration_list, $interface_property_list);
            }
        }
    }

    /**
     * @return list<Clazz> the parent class, its parent class, etc.
     */
    private static function getParentClassList(CodeBase $code_base, Clazz $class): array
    {
        $result = [];
        $seen = [$class->getFQSEN()->__toString() => true];
        while ($class->hasParentType()) {
            $parent_fqsen = $class->getParentClassFQSEN();
            if (isset($seen[$parent_fqsen->__toString()]) || !$code_base->hasClassWithFQSEN($parent_fqsen)) {
                break;
            }
            $seen[$parent_fqsen->__toString()] = true;
            $class = $code_base->getClassByFQSEN($parent_fqsen);
            $result[] = $class;
        }
        return $result;
    }

    /**
     * The entry of the property map of a class is not used for this:
     * for an inherited property, it is the declaration of only one of the ancestors.
     *
     * @param int $depth guards against traits that use each other
     * @return ?Property the property $name that $class declares itself, or that one of the traits that it uses declares.
     */
    private static function getDeclaration(CodeBase $code_base, Clazz $class, string $name, int $depth = 0): ?Property
    {
        $property = $class->getPropertyMap($code_base)[$name] ?? null;
        if ($property && $property->getRealDefiningFQSEN()->getFullyQualifiedClassName() === $class->getFQSEN()) {
            if ($property->isDynamicOrFromPHPDoc() || $property->getPhanFlags() & (Flags::IS_ENUM_PROPERTY | Flags::IS_FROM_REQUIRE_ANNOTATION)) {
                return null;
            }
            return $property;
        }
        if ($depth > 10) {
            return null;
        }
        foreach ($class->getTraitFQSENList() as $trait_fqsen) {
            if ($code_base->hasClassWithFQSEN($trait_fqsen)) {
                $property = self::getDeclaration($code_base, $code_base->getClassByFQSEN($trait_fqsen), $name, $depth + 1);
                if ($property) {
                    return $property;
                }
            }
        }
        return null;
    }

    /**
     * Emits an issue for the declaration $property of $class (which may be from a trait),
     * unless the issue type is suppressed on the property.
     *
     * @param int $lineno the line to use if $class itself declares the property. An issue for the property of a trait is reported for the class that uses it.
     * @param int|string ...$args
     */
    private static function emitIssue(CodeBase $code_base, Clazz $class, Property $property, string $issue_type, int $lineno, int|string ...$args): void
    {
        if ($property->checkHasSuppressIssueAndIncrementCount($issue_type)) {
            return;
        }
        if ($property->getRealDefiningFQSEN()->getFullyQualifiedClassName() !== $class->getFQSEN()) {
            $lineno = $class->getFileRef()->getLineNumberStart();
        }
        Issue::maybeEmit($code_base, $class->getInternalContext(), $issue_type, $lineno, ...$args);
    }

    /**
     * Emits issues if $property overrides a final property, or a final hook.
     *
     * @param non-empty-list<Property> $parent_property_list the declarations of the parent classes, starting with the closest one
     */
    private static function checkFinal(CodeBase $code_base, Clazz $class, Property $property, array $parent_property_list): void
    {
        $hooks = \array_filter(['get' => $property->getGetHook(), 'set' => $property->getSetHook()]);
        foreach ($parent_property_list as $parent_property) {
            // A property that is private(set) is implicitly final.
            if ($parent_property->getFlags() & (ast\flags\MODIFIER_FINAL | ast\flags\MODIFIER_PRIVATE_SET)) {
                self::emitIssue(
                    $code_base,
                    $class,
                    $property,
                    Issue::AccessOverridesFinalProperty,
                    $property->getFileRef()->getLineNumberStart(),
                    $class->getFQSEN() . '->' . $property->getName(),
                    $parent_property->getRealDefiningFQSEN()->getFullyQualifiedClassName() . '->' . $parent_property->getName(),
                    $parent_property->getFileRef()->getFile(),
                    $parent_property->getFileRef()->getLineNumberStart()
                );
                return;
            }
            // A hook overrides the hook of the closest parent class that declares it.
            foreach ($hooks as $hook_name => $hook) {
                $parent_hook = $hook_name === 'get' ? $parent_property->getGetHook() : $parent_property->getSetHook();
                if (!$parent_hook) {
                    continue;
                }
                unset($hooks[$hook_name]);
                if ($parent_hook->isFinal()) {
                    self::emitIssue(
                        $code_base,
                        $class,
                        $property,
                        Issue::PropertyHookFinalOverride,
                        $hook->getContext()->getLineNumberStart(),
                        $parent_property->getRealDefiningFQSEN()->getFullyQualifiedClassName() . '->' . $parent_property->getName(),
                        $hook_name,
                        $parent_hook->getContext()->getFile(),
                        $parent_hook->getContext()->getLineNumberStart()
                    );
                }
            }
        }
    }

    /**
     * Emits issues for the hooks of $property with #[\Override] that do not override anything.
     *
     * @param list<Property> $parent_property_list
     * @param list<Property> $interface_property_list
     */
    private static function checkOverrideAttributes(CodeBase $code_base, Clazz $class, Property $property, array $parent_property_list, array $interface_property_list): void
    {
        foreach ([$property->getGetHook(), $property->getSetHook()] as $hook) {
            if (!$hook || !$hook->hasOverrideAttribute()) {
                continue;
            }
            foreach ([...$parent_property_list, ...$interface_property_list] as $inherited_property) {
                $inherited_hook = $hook->getName() === 'get' ? $inherited_property->getGetHook() : $inherited_property->getSetHook();
                // A backed property can be read and written without a hook, so a hook of either kind overrides something.
                if ($inherited_hook || (!$inherited_property->isVirtual() && !self::isAbstractDeclaration($code_base, $inherited_property))) {
                    continue 2;
                }
            }
            self::emitIssue(
                $code_base,
                $class,
                $property,
                Issue::CommentOverrideOnNonOverrideMethod,
                $hook->getContext()->getLineNumberStart(),
                self::hookName($class, $property, $hook->getName())
            );
        }
    }

    /**
     * Emits issues for the abstract hooks that the non-abstract $class does not implement.
     *
     * @param list<Property> $declaration_list the declarations of $class and its parent classes, starting with $class
     * @param list<Property> $interface_property_list
     */
    private static function checkAbstractHooks(CodeBase $code_base, Clazz $class, array $declaration_list, array $interface_property_list): void
    {
        foreach (['get', 'set'] as $hook_name) {
            // Find the closest declaration that decides whether the hook is implemented.
            $abstract_property = null;
            $is_implemented = false;
            foreach ($declaration_list as $property) {
                $hook = $hook_name === 'get' ? $property->getGetHook() : $property->getSetHook();
                if ($hook) {
                    // A hook without a body in a property that is not abstract is reported when the property is parsed.
                    if ($hook->hasBody() || !self::isAbstractDeclaration($code_base, $property)) {
                        $is_implemented = true;
                    } else {
                        $abstract_property = $property;
                    }
                    break;
                }
                if (!$property->isVirtual() && !self::isAbstractDeclaration($code_base, $property)) {
                    // A backed property can be read and written without the hook.
                    $is_implemented = true;
                    break;
                }
            }
            if ($is_implemented) {
                continue;
            }
            if (!$abstract_property) {
                foreach ($interface_property_list as $interface_property) {
                    if ($hook_name === 'get' ? $interface_property->getGetHook() : $interface_property->getSetHook()) {
                        $abstract_property = $interface_property;
                        break;
                    }
                }
                if (!$abstract_property) {
                    continue;
                }
            }
            Issue::maybeEmit(
                $code_base,
                $class->getContext(),
                Issue::ClassContainsAbstractMethod,
                $class->getFileRef()->getLineNumberStart(),
                (string)$class->getFQSEN(),
                self::hookName(null, $abstract_property, $hook_name),
                $abstract_property->getFileRef()->getFile(),
                $abstract_property->getFileRef()->getLineNumberStart()
            );
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
     * @param ?Clazz $class the class that uses the property, if the property might be from a trait
     * @return string e.g. `\NS\MyClass::$prop::get`
     */
    private static function hookName(?Clazz $class, Property $property, string $hook_name): string
    {
        $class_fqsen = $class ? $class->getFQSEN() : $property->getRealDefiningFQSEN()->getFullyQualifiedClassName();
        return $class_fqsen . '::$' . $property->getName() . '::' . $hook_name;
    }
}
