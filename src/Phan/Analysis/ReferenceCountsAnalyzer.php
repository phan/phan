<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use Phan\CLI;
use Phan\CodeBase;
use Phan\CodeBase\ClassMap;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\ClassElement;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\EnumCase;
use Phan\Language\Element\Flags;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type\NullType;
use TypeError;

/**
 * This emits PhanUnreferenced* issues for class-likes, constants, properties, and functions/methods.
 *
 * TODO: Make references to methods of interfaces also count as references to traits which are used by classes to implement those methods.
 * (Maybe track these in addMethod when checking for inheritance?)
 */
class ReferenceCountsAnalyzer
{
    /**
     * Take a look at all globally accessible elements and see if
     * we can find any dead code that is never referenced
     */
    public static function analyzeReferenceCounts(CodeBase $code_base): void
    {
        // Check to see if dead code detection is enabled. Keep
        // in mind that the results here are just a guess and
        // we can't tell with certainty that anything is
        // definitely unreferenced.
        if (!Config::getValue('dead_code_detection')) {
            return;
        }

        // Get the count of all known elements
        $total_count = $code_base->totalElementCount();
        $i = 0;

        // Functions
        self::analyzeGlobalElementListReferenceCounts(
            $code_base,
            $code_base->getFunctionMap(),
            Issue::UnreferencedFunction,
            $total_count,
            $i
        );

        // Constants
        self::analyzeGlobalElementListReferenceCounts(
            $code_base,
            $code_base->getGlobalConstantMap(),
            Issue::UnreferencedConstant,
            $total_count,
            $i
        );

        // Classes
        self::analyzeGlobalElementListReferenceCounts(
            $code_base,
            $code_base->getUserDefinedClassMap(),
            Issue::UnreferencedClass,
            $total_count,
            $i
        );

        // A class using a trait can declare a property, constant or method with the same name as the trait's:
        // uses of the trait's element inside the trait are then also references to the class's declaration.
        // This must run before the loop below merges the references of the imported copies of other classes using the trait
        // into the trait's element, and after the classes were checked (a reference from inside the trait is not a reference to the class).
        self::copyReferencesFromSameNamedTraitElements($code_base);

        // Class Maps
        $elements_to_analyze = [];
        foreach ($code_base->getClassMapMap() as $class_map) {
            foreach (self::getElementsFromClassMapForDeferredAnalysis(
                $code_base,
                $class_map,
                $total_count,
                $i
            ) as $element) {
                $elements_to_analyze[] = $element;
            }
        }

        // The reverse: uses of the class's declaration of a property or constant are uses of the trait's.
        // This runs after the loop above, so that the references of the subclasses of the class were merged into the class's declaration.
        self::copyReferencesToSameNamedTraitElements($code_base);

        static $issue_types = [
            ClassConstant::class => Issue::UnreferencedPublicClassConstant,  // This is overridden
            EnumCase::class      => Issue::UnreferencedEnumCase,  // enum cases are always public
            Method::class        => Issue::UnreferencedPublicMethod,  // This is overridden
            Property::class      => Issue::UnreferencedPublicProperty,  // This is overridden
        ];

        foreach ($elements_to_analyze as $element) {
            $issue_type = $issue_types[\get_class($element)];
            self::analyzeElementReferenceCounts($code_base, $element, $issue_type);
        }
        CLI::progress('dead code', 1.0);
    }

    /**
     * @return \Generator|ClassElement[]
     * @phan-return \Generator<ClassElement>
     */
    private static function getElementsFromClassMapForDeferredAnalysis(
        CodeBase $code_base,
        ClassMap $class_map,
        int $total_count,
        int &$i
    ): \Generator {
        // Constants
        yield from self::getElementsFromElementListForDeferredAnalysis(
            $code_base,
            $class_map->getClassConstantMap(),
            $total_count,
            $i
        );

        // Properties
        yield from self::getElementsFromElementListForDeferredAnalysis(
            $code_base,
            $class_map->getPropertyMap(),
            $total_count,
            $i
        );

        // Methods
        yield from self::getElementsFromElementListForDeferredAnalysis(
            $code_base,
            $class_map->getMethodMap(),
            $total_count,
            $i
        );
    }

    /**
     * @param CodeBase $code_base
     * @param iterable<AddressableElement> $element_list
     * @param string $issue_type
     * @param int $total_count
     * @param int $i
     */
    private static function analyzeGlobalElementListReferenceCounts(
        CodeBase $code_base,
        iterable $element_list,
        string $issue_type,
        int $total_count,
        int &$i
    ): void {
        foreach ($element_list as $element) {
            CLI::progress('dead code', (++$i) / $total_count, $element);
            // Don't worry about internal elements
            if ($element->isPHPInternal() || $element->getContext()->isPHPInternal()) {
                // The extra check of the context is necessary for code in internal_stubs
                // which aren't exactly internal to PHP.
                continue;
            }
            self::analyzeElementReferenceCounts($code_base, $element, $issue_type);
        }
    }

    /**
     * @param CodeBase $code_base
     * @param iterable<ClassElement> $element_list
     * @param int $total_count
     * @param int $i
     *
     * @return \Generator|ClassElement[]
     * @phan-return \Generator<ClassElement>
     */
    private static function getElementsFromElementListForDeferredAnalysis(
        CodeBase $code_base,
        iterable $element_list,
        int $total_count,
        int &$i
    ): \Generator {
        foreach ($element_list as $element) {
            CLI::progress('dead code', (++$i) / $total_count, $element);
            // Don't worry about internal elements
            if ($element->isPHPInternal() || $element->getContext()->isPHPInternal()) {
                // The extra check of the context is necessary for code in internal_stubs
                // which aren't exactly internal to PHP.
                continue;
            }

            // Currently, deferred analysis is only needed for class elements, which can be inherited
            // (And we may track the references to the inherited version of the original)
            if (!$element instanceof ClassElement) {
                throw new TypeError("Expected an iterable of ClassElement values");
            }
            // should not warn about self::class
            if ($element instanceof ClassConstant && \strcasecmp($element->getName(), 'class') === 0) {
                continue;
            }
            $fqsen = $element->getFQSEN();
            // For the private/protected copies of methods, properties and constants made for a class using a trait,
            // this is the trait's element (getDefiningFQSEN() is the using class, for access checks).
            $defining_fqsen = $element->getRealDefiningFQSEN();
            if ($element instanceof Property && $element->getPhanFlagsHasState(Flags::IS_ENUM_PROPERTY)) {
                // Don't warn about immutable enum properties automatically created by php itself.
                continue;
            }

            // copy references to methods, properties, and constants into the defining trait or class.
            if ($fqsen !== $defining_fqsen) {
                $has_references = $element->getReferenceCount($code_base) > 0;
                if ($has_references || ($element instanceof Method && ($element->isOverride() && !$element->isPrivate()))) {
                    $defining_element = null;
                    if ($defining_fqsen instanceof FullyQualifiedMethodName) {
                        if ($code_base->hasMethodWithFQSEN($defining_fqsen)) {
                            $defining_element = $code_base->getMethodByFQSEN($defining_fqsen);
                        }
                    } elseif ($defining_fqsen instanceof FullyQualifiedPropertyName) {
                        if ($code_base->hasPropertyWithFQSEN($defining_fqsen)) {
                            $defining_element = $code_base->getPropertyByFQSEN($defining_fqsen);
                        }
                    } elseif ($defining_fqsen instanceof FullyQualifiedClassConstantName) {
                        if ($code_base->hasClassConstantWithFQSEN($defining_fqsen)) {
                            $defining_element = $code_base->getClassConstantByFQSEN($defining_fqsen);
                        }
                    }
                    if ($defining_element !== null) {
                        if ($has_references) {
                            $defining_element->copyReferencesFrom($element);
                        } elseif ($element instanceof Method) {
                            foreach ($element->getOverriddenMethods($code_base) as $overridden_element) {
                                $defining_element->copyReferencesFrom($overridden_element);
                            }
                        }
                    }
                }
                continue;
            }

            // Don't analyze elements defined in a parent class.
            // We copy references to methods, properties, and constants into the defining trait or class before this.
            if ($element->isOverride() && !$element->isPrivate()) {
                continue;
            }

            $defining_class =
                $element->getClass($code_base);

            if ($element instanceof Method) {
                // Ignore magic methods
                if ($element->isMagic()) {
                    continue;
                }
                // Don't analyze abstract methods, as they're uncallable.
                // (Every method on an interface is abstract)
                if ($element->isAbstract() || $defining_class->isInterface()) {
                    continue;
                }
            } elseif ($element instanceof Property) {
                // Skip properties on classes that were derived from (at)property annotations on classes
                // or were automatically generated for classes with __get or __set methods
                // (or undeclared properties that were automatically added depending on configs)
                if ($element->isDynamicProperty()) {
                    continue;
                }
                // TODO: may want to continue to skip `if ($defining_class->hasGetOrSetMethod($code_base)) {`
                // E.g. a __get() method that is implemented as `return $this->"_$name"`.
                // (at)phan-file-suppress is an easy enough workaround, though
            }
            yield $element;
        }
    }

    /**
     * A class using a trait can declare a property, a class constant (PHP 8.2+) or a method with the same name
     * as an element of the trait. The class's declaration is then not an imported copy of the trait's element
     * (see ClassElement::getRealDefiningFQSEN()), but `$this->prop`, `self::X` or `$this->method()` in the methods
     * of the trait refer to the trait's element, so the class's declaration would seem to be unreferenced.
     * (Issues #5391, #5630)
     *
     * This copies the references of the trait's element to the class's declaration:
     *
     * - The class's method replaces the trait's method (abstract or not, or declared with `@method`) in that class,
     *   so the trait's calls of its own method are calls of the class's method.
     *   The reverse does not hold: the trait's method is not called for instances of that class.
     * - PHP requires the declarations of a property (or of a constant) in the trait and in the class to be compatible,
     *   and in that class they are the same property (constant). The references of the trait's declaration
     *   (a magic `@property` included) are copied here, and the references of the class's declaration are copied
     *   to the trait's declaration in copyReferencesToSameNamedTraitElements().
     *
     * This runs before getElementsFromElementListForDeferredAnalysis() merges the references of the imported copies
     * of other classes using the trait into the trait's element, so that only the uses inside the trait are copied:
     * a use of the trait's element by another class is not a use of this class's declaration.
     */
    private static function copyReferencesFromSameNamedTraitElements(CodeBase $code_base): void
    {
        foreach (self::getSameNamedTraitElements($code_base) as [$element, $trait_element]) {
            if ($element instanceof Method && $element->isFromPHPDoc()) {
                // A magic `@method` of the class does not replace a method of the trait.
                continue;
            }
            if ($trait_element->getReferenceCount($code_base) > 0) {
                $element->copyReferencesFrom($trait_element);
            }
        }
    }

    /**
     * Copies the references of a property or constant that a class declares with the same name as a property or constant
     * of a trait it uses to the trait's declaration, as they are the same property or constant in that class.
     * A class declaring the property the trait declares with `@property` does not reference that annotation.
     *
     * This runs after getElementsFromElementListForDeferredAnalysis() merged the references of the copies
     * inherited by subclasses into the class's declaration.
     * @see copyReferencesFromSameNamedTraitElements()
     */
    private static function copyReferencesToSameNamedTraitElements(CodeBase $code_base): void
    {
        foreach (self::getSameNamedTraitElements($code_base) as [$element, $trait_element]) {
            if ($element instanceof Method) {
                continue;
            }
            if ($trait_element instanceof Property && $trait_element->isFromPHPDoc()) {
                continue;
            }
            if ($element->getReferenceCount($code_base) > 0) {
                $trait_element->copyReferencesFrom($element);
            }
        }
    }

    /**
     * @return \Generator<array{0:ClassElement,1:ClassElement}>
     * Pairs of a property, constant or method that a user-defined class (or trait) declares itself
     * and the element of the same kind and name of a trait the class uses (directly or through other traits).
     * A magic `@property` or dynamic property of the class is not merged with a property of a trait.
     */
    private static function getSameNamedTraitElements(CodeBase $code_base): \Generator
    {
        foreach ($code_base->getUserDefinedClassMap() as $class) {
            if (!$class->getTraitFQSENList()) {
                continue;
            }
            $trait_fqsen_list = self::getTraitFQSENListRecursively($code_base, $class);
            if (!$trait_fqsen_list) {
                continue;
            }
            foreach ($class->getPropertyMap($code_base) as $property) {
                if ($property->getFQSEN() !== $property->getRealDefiningFQSEN() || $property->isDynamicOrFromPHPDoc()) {
                    continue;
                }
                foreach ($trait_fqsen_list as $trait_fqsen) {
                    $trait_property_fqsen = FullyQualifiedPropertyName::make($trait_fqsen, $property->getName());
                    if ($code_base->hasPropertyWithFQSEN($trait_property_fqsen)) {
                        yield [$property, $code_base->getPropertyByFQSEN($trait_property_fqsen)];
                    }
                }
            }
            foreach ($class->getConstantMap($code_base) as $constant) {
                if ($constant->getFQSEN() !== $constant->getRealDefiningFQSEN()) {
                    continue;
                }
                foreach ($trait_fqsen_list as $trait_fqsen) {
                    $trait_constant_fqsen = FullyQualifiedClassConstantName::make($trait_fqsen, $constant->getName());
                    if ($code_base->hasClassConstantWithFQSEN($trait_constant_fqsen)) {
                        yield [$constant, $code_base->getClassConstantByFQSEN($trait_constant_fqsen)];
                    }
                }
            }
            foreach ($class->getMethodMap($code_base) as $method) {
                if ($method->getFQSEN() !== $method->getRealDefiningFQSEN()) {
                    continue;
                }
                foreach ($trait_fqsen_list as $trait_fqsen) {
                    $trait_method_fqsen = FullyQualifiedMethodName::make($trait_fqsen, $method->getName());
                    if ($code_base->hasMethodWithFQSEN($trait_method_fqsen)) {
                        yield [$method, $code_base->getMethodByFQSEN($trait_method_fqsen)];
                    }
                }
            }
        }
    }

    /**
     * @return list<FullyQualifiedClassName> the traits used by $class, directly or through other traits
     */
    private static function getTraitFQSENListRecursively(CodeBase $code_base, Clazz $class): array
    {
        $result = [];
        $pending = $class->getTraitFQSENList();
        while ($pending) {
            $trait_fqsen = \array_pop($pending);
            $key = \strtolower($trait_fqsen->__toString());
            if (isset($result[$key]) || !$code_base->hasClassWithFQSEN($trait_fqsen)) {
                continue;
            }
            $result[$key] = $trait_fqsen;
            foreach ($code_base->getClassByFQSEN($trait_fqsen)->getTraitFQSENList() as $nested_trait_fqsen) {
                $pending[] = $nested_trait_fqsen;
            }
        }
        return \array_values($result);
    }

    /**
     * Check to see if the given AddressableElement is a duplicate
     */
    private static function analyzeElementReferenceCounts(
        CodeBase $code_base,
        AddressableElement $element,
        string $issue_type
    ): void {
        /*
        print "digraph G {\n";
        foreach ($element->getReferenceList() as $file_ref) {
            print "\t\"{$file_ref->getFile()}\" -> \"{$element->getFileRef()->getFile()}\";\n";
        }
        print "}\n";
        */

        // Make issue types granular so that these can be fixed in smaller steps.
        // E.g. composer libraries may have unreferenced but used public methods, properties, and class constants,
        // and those would have higher false positives than private/protected elements.
        //
        // Make $issue_type specific **first**, so that issue suppressions are checked against the proper issue type
        if ($element instanceof ClassElement) {
            if ($element instanceof Method) {
                if ($element->isPrivate()) {
                    $issue_type = self::isDeclaredInTrait($code_base, $element) ? Issue::UnreferencedPrivateTraitMethod : Issue::UnreferencedPrivateMethod;
                } elseif ($element->isProtected()) {
                    $issue_type = Issue::UnreferencedProtectedMethod;
                } else {
                    $issue_type = Issue::UnreferencedPublicMethod;
                }
            } elseif ($element instanceof Property) {
                if ($element->isFromPHPDoc()) {
                    $issue_type = Issue::UnreferencedPHPDocProperty;
                } elseif ($element->isPrivate()) {
                    $issue_type = self::isDeclaredInTrait($code_base, $element) ? Issue::UnreferencedPrivateTraitProperty : Issue::UnreferencedPrivateProperty;
                } elseif ($element->isProtected()) {
                    $issue_type = Issue::UnreferencedProtectedProperty;
                } else {
                    $issue_type = Issue::UnreferencedPublicProperty;
                }
            } elseif ($element instanceof ClassConstant) {
                if ($element->isPrivate()) {
                    $issue_type = self::isDeclaredInTrait($code_base, $element) ? Issue::UnreferencedPrivateTraitClassConstant : Issue::UnreferencedPrivateClassConstant;
                } elseif ($element->isProtected()) {
                    $issue_type = Issue::UnreferencedProtectedClassConstant;
                } else {
                    $issue_type = Issue::UnreferencedPublicClassConstant;
                }
            }
        } elseif ($element instanceof Func) {
            if (\strcasecmp($element->getName(), "__autoload") === 0) {
                return;
            }
            if ($element->getFQSEN()->isClosure()) {
                if (self::hasSuppressionForUnreferencedClosure($code_base, $element)) {
                    // $element->getContext() is the context within the closure - We also want to check the context of the function-like outside of the closure(s).
                    return;
                }
                $issue_type = Issue::UnreferencedClosure;
            }
        } elseif ($element instanceof Clazz) {
            if ($element->isAnonymous()) {
                // This can't be referenced by name in type signatures, etc.
                return;
            }
        }


        // If we're suppressing this element type being unreferenced, then exit early.
        if (self::checkHasSuppressIssueOrNonTraitVariant($element, $issue_type)) {
            return;
        }

        if ($element->getReferenceCount($code_base) >= 1) {
            if ($element instanceof Property) {
                if (!$element->hasReadReference()) {
                    self::maybeWarnWriteOnlyProperty($code_base, $element);
                } elseif (!$element->hasWriteReference()) {
                    self::maybeWarnReadOnlyProperty($code_base, $element);
                }
            }
            return;
        }
        // getReferenceCount === 0

        $element_alt = self::findAlternateReferencedElementDeclaration($code_base, $element);
        if (!\is_null($element_alt)) {
            if ($element_alt->getReferenceCount($code_base) >= 1) {
                if ($element_alt instanceof Property) {
                    if (!$element_alt->hasReadReference()) {
                        self::maybeWarnWriteOnlyProperty($code_base, $element_alt);
                    } elseif (!($element_alt->hasWriteReference())) {
                        self::maybeWarnReadOnlyProperty($code_base, $element_alt);
                    }
                }
                // If there is a reference to the "canonical" declaration (the one which was parsed first),
                // then also treat it as a reference to the duplicate.
                return;
            }
            if ($element_alt->isPHPInternal()) {
                // For efficiency, Phan doesn't track references to internal classes.
                // Phan already emitted a warning about duplicating an internal class.
                return;
            }
        }
        // If there are duplicate declarations, display issues for unreferenced elements on each declaration.
        if ($issue_type === Issue::UnreferencedClass) {
            if (!$element instanceof Clazz) {
                throw new AssertionError('Expected ' . Clazz::class . ' instance but got ' . \get_class($element));
            }
            Issue::maybeEmit(
                $code_base,
                $element->getContext(),
                $issue_type,
                $element->getFileRef()->getLineNumberStart(),
                $element->getClasslikeType(),
                $element->getRepresentationForIssue()
            );
            return;
        }
        self::emitUnlessSuppressed($code_base, $element, $issue_type);
    }

    private static function hasSuppressionForUnreferencedClosure(CodeBase $code_base, Func $func): bool
    {
        $context = $func->getContext();
        return $context->withScope($context->getScope()->getParentScope())->hasSuppressIssue($code_base, Issue::UnreferencedClosure);
    }

    /**
     * Private elements of a trait get their own issue types (#5526): they can be used by classes using the trait,
     * which a library providing the trait does not necessarily contain.
     */
    private static function isDeclaredInTrait(CodeBase $code_base, ClassElement $element): bool
    {
        $class_fqsen = $element->getClassFQSEN();
        return $code_base->hasClassWithFQSEN($class_fqsen) && $code_base->getClassByFQSEN($class_fqsen)->isTrait();
    }

    /**
     * The private trait variants were split from the private issue types,
     * so a suppression of the private issue type (in the config, in a file, on a line or on the element)
     * keeps suppressing the trait variant.
     */
    private const NON_TRAIT_VARIANT = [
        Issue::UnreferencedPrivateTraitMethod => Issue::UnreferencedPrivateMethod,
        Issue::UnreferencedPrivateTraitProperty => Issue::UnreferencedPrivateProperty,
        Issue::UnreferencedPrivateTraitClassConstant => Issue::UnreferencedPrivateClassConstant,
        Issue::ReadOnlyPrivateTraitProperty => Issue::ReadOnlyPrivateProperty,
        Issue::WriteOnlyPrivateTraitProperty => Issue::WriteOnlyPrivateProperty,
    ];

    private static function checkHasSuppressIssueOrNonTraitVariant(AddressableElement $element, string $issue_type): bool
    {
        if ($element->checkHasSuppressIssueAndIncrementCount($issue_type)) {
            return true;
        }
        $non_trait_variant = self::NON_TRAIT_VARIANT[$issue_type] ?? null;
        return $non_trait_variant !== null && $element->checkHasSuppressIssueAndIncrementCount($non_trait_variant);
    }

    private static function emitUnlessSuppressed(CodeBase $code_base, AddressableElement $element, string $issue_type): void
    {
        $context = $element->getContext();
        $lineno = $element->getFileRef()->getLineNumberStart();
        $representation = $element->getRepresentationForIssue();
        $non_trait_variant = self::NON_TRAIT_VARIANT[$issue_type] ?? null;
        if ($non_trait_variant !== null && Issue::shouldSuppressIssue($code_base, $context, $non_trait_variant, $lineno, [$representation])) {
            return;
        }
        Issue::maybeEmit($code_base, $context, $issue_type, $lineno, $representation);
    }

    private static function maybeWarnWriteOnlyProperty(CodeBase $code_base, Property $property): void
    {
        if ($property->isWriteOnly()) {
            // Handle annotations such as property-write and phan-write-only
            return;
        }
        if ($property->isFromPHPDoc()) {
            $issue_type = Issue::WriteOnlyPHPDocProperty;
        } elseif ($property->isPrivate()) {
            $issue_type = self::isDeclaredInTrait($code_base, $property) ? Issue::WriteOnlyPrivateTraitProperty : Issue::WriteOnlyPrivateProperty;
        } elseif ($property->isProtected()) {
            $issue_type = Issue::WriteOnlyProtectedProperty;
        } else {
            $issue_type = Issue::WriteOnlyPublicProperty;
        }
        if (self::checkHasSuppressIssueOrNonTraitVariant($property, $issue_type)) {
            return;
        }
        $property_alt = self::findAlternateReferencedElementDeclaration($code_base, $property);
        if ($property_alt instanceof Property) {
            if ($property_alt->hasReadReference()) {
                return;
            }
        }
        self::emitUnlessSuppressed($code_base, $property, $issue_type);
    }

    private static function maybeWarnReadOnlyProperty(CodeBase $code_base, Property $property): void
    {
        if ($property->isReadOnly()) {
            // Handle annotations such as property-read and phan-read-only.
            return;
        }
        // When dead_code_detection_prefer_false_negative is true (default),
        // skip warnings for initialized properties in traits when targeting PHP < 8.2.
        // Traits cannot have constants before PHP 8.2, so using initialized properties
        // as pseudo-constants is a common pattern (fixes #5390).
        if (Config::getValue('dead_code_detection_prefer_false_negative')) {
            if (Config::get_closest_target_php_version_id() < 80200) {
                $default_type = $property->getDefaultType();
                // Check that the property has an actual initializer value, not just a declaration.
                // Properties without initializers have a default_type of NullType with real type set
                // (see ParseVisitor line 894). Properties initialized to literal `null` also have
                // NullType but WITHOUT real type set (erased in resolveDefaultPropertyNode).
                $is_uninitialized = $default_type === null ||
                    ($default_type->isType(NullType::instance(false)) && $default_type->hasRealTypeSet());
                if (!$is_uninitialized) {
                    $class_fqsen = $property->getClassFQSEN();
                    if ($code_base->hasClassWithFQSEN($class_fqsen)) {
                        $class = $code_base->getClassByFQSEN($class_fqsen);
                        if ($class->isTrait()) {
                            return;
                        }
                    }
                }
            }
        }
        if ($property->isFromPHPDoc()) {
            $issue_type = Issue::ReadOnlyPHPDocProperty;
        } elseif ($property->isPrivate()) {
            $issue_type = self::isDeclaredInTrait($code_base, $property) ? Issue::ReadOnlyPrivateTraitProperty : Issue::ReadOnlyPrivateProperty;
        } elseif ($property->isProtected()) {
            $issue_type = Issue::ReadOnlyProtectedProperty;
        } else {
            $issue_type = Issue::ReadOnlyPublicProperty;
        }
        if (self::checkHasSuppressIssueOrNonTraitVariant($property, $issue_type)) {
            return;
        }
        $property_alt = self::findAlternateReferencedElementDeclaration($code_base, $property);
        if ($property_alt instanceof Property) {
            if ($property_alt->hasWriteReference()) {
                return;
            }
        }
        // echo "known references to $property: " . implode(array_map('strval', $property->getReferenceList())) . "\n";
        self::emitUnlessSuppressed($code_base, $property, $issue_type);
    }

    /**
     * Find Elements with FQSENs that are the same as $element's FQSEN,
     * apart from the alternate id.
     * (i.e. duplicate declarations)
     */
    public static function findAlternateReferencedElementDeclaration(
        CodeBase $code_base,
        AddressableElement $element
    ): ?AddressableElement {
        $old_fqsen = $element->getFQSEN();
        if ($old_fqsen instanceof FullyQualifiedGlobalStructuralElement) {
            $fqsen = $old_fqsen->getCanonicalFQSEN();
            if ($fqsen === $old_fqsen) {
                return null;  // $old_fqsen was not an alternative
            }
            if ($fqsen instanceof FullyQualifiedFunctionName) {
                if ($code_base->hasFunctionWithFQSEN($fqsen)) {
                    return $code_base->getFunctionByFQSEN($fqsen);
                }
                return null;
            } elseif ($fqsen instanceof FullyQualifiedClassName) {
                if ($code_base->hasClassWithFQSEN($fqsen)) {
                    return $code_base->getClassByFQSEN($fqsen);
                }
                return null;
            } elseif ($fqsen instanceof FullyQualifiedGlobalConstantName) {
                if ($code_base->hasGlobalConstantWithFQSEN($fqsen)) {
                    return $code_base->getGlobalConstantByFQSEN($fqsen);
                }
                return null;
            }
        } elseif ($old_fqsen instanceof FullyQualifiedClassElement) {
            // If this needed to be more thorough,
            // the code adding references could treat uses from within the classes differently from outside.
            $fqsen = $old_fqsen->getCanonicalFQSEN();
            if ($fqsen === $old_fqsen) {
                return null;  // $old_fqsen was not an alternative
            }

            if ($fqsen instanceof FullyQualifiedMethodName) {
                if ($code_base->hasMethodWithFQSEN($fqsen)) {
                    return $code_base->getMethodByFQSEN($fqsen);
                }
                return null;
            } elseif ($fqsen instanceof FullyQualifiedPropertyName) {
                if ($code_base->hasPropertyWithFQSEN($fqsen)) {
                    return $code_base->getPropertyByFQSEN($fqsen);
                }
                return null;
            } elseif ($fqsen instanceof FullyQualifiedClassConstantName) {
                if ($code_base->hasClassConstantWithFQSEN($fqsen)) {
                    return $code_base->getClassConstantByFQSEN($fqsen);
                }
                return null;
            }
        }
        return null;
    }
}
