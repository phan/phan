<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CLI;
use Phan\CodeBase;
use Phan\CodeBase\ClassMap;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\ClassElement;
use Phan\Language\Element\Clazz;
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

        static $issue_types = [
            ClassConstant::class => Issue::UnreferencedPublicClassConstant,  // This is overridden
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
     * @param CodeBase $code_base
     * @param ClassMap $class_map
     * @param int $total_count
     * @param int $i
     *
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
            if ($element->isPHPInternal()) {
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
            if ($element instanceof Method || $element instanceof Property) {
                $defining_fqsen = $element->getRealDefiningFQSEN();
            } else {
                $defining_fqsen = $element->getDefiningFQSEN();
            }

            // copy references to methods, properties, and constants into the defining trait or class.
            if ($fqsen !== $defining_fqsen) {
                $has_references = $element->getReferenceCount($code_base) > 0;
                if ($has_references || ($element instanceof Method && $element->isOverride())) {
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
            if ($element->isOverride()) {
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
                    $issue_type = Issue::UnreferencedPrivateMethod;
                } elseif ($element->isProtected()) {
                    $issue_type = Issue::UnreferencedProtectedMethod;
                } else {
                    $issue_type = Issue::UnreferencedPublicMethod;
                }
            } elseif ($element instanceof Property) {
                if ($element->isFromPHPDoc()) {
                    $issue_type = Issue::UnreferencedPHPDocProperty;
                } elseif ($element->isPrivate()) {
                    $issue_type = Issue::UnreferencedPrivateProperty;
                } elseif ($element->isProtected()) {
                    $issue_type = Issue::UnreferencedProtectedProperty;
                } else {
                    $issue_type = Issue::UnreferencedPublicProperty;
                }
            } elseif ($element instanceof ClassConstant) {
                if ($element->isPrivate()) {
                    $issue_type = Issue::UnreferencedPrivateClassConstant;
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
        if ($element->checkHasSuppressIssueAndIncrementCount($issue_type)) {
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
        Issue::maybeEmit(
            $code_base,
            $element->getContext(),
            $issue_type,
            $element->getFileRef()->getLineNumberStart(),
            $element->getRepresentationForIssue()
        );
    }

    private static function hasSuppressionForUnreferencedClosure(CodeBase $code_base, Func $func): bool
    {
        $context = $func->getContext();
        return $context->withScope($context->getScope()->getParentScope())->hasSuppressIssue($code_base, Issue::UnreferencedClosure);
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
            $issue_type = Issue::WriteOnlyPrivateProperty;
        } elseif ($property->isProtected()) {
            $issue_type = Issue::WriteOnlyProtectedProperty;
        } else {
            $issue_type = Issue::WriteOnlyPublicProperty;
        }
        if ($property->checkHasSuppressIssueAndIncrementCount($issue_type)) {
            return;
        }
        $property_alt = self::findAlternateReferencedElementDeclaration($code_base, $property);
        if ($property_alt instanceof Property) {
            if ($property_alt->hasReadReference()) {
                return;
            }
        }
        Issue::maybeEmit(
            $code_base,
            $property->getContext(),
            $issue_type,
            $property->getFileRef()->getLineNumberStart(),
            $property->getRepresentationForIssue()
        );
    }

    private static function maybeWarnReadOnlyProperty(CodeBase $code_base, Property $property): void
    {
        if ($property->isReadOnly()) {
            // Handle annotations such as property-read and phan-read-only.
            return;
        }
        if ($property->isFromPHPDoc()) {
            $issue_type = Issue::ReadOnlyPHPDocProperty;
        } elseif ($property->isPrivate()) {
            $issue_type = Issue::ReadOnlyPrivateProperty;
        } elseif ($property->isProtected()) {
            $issue_type = Issue::ReadOnlyProtectedProperty;
        } else {
            $issue_type = Issue::ReadOnlyPublicProperty;
        }
        if ($property->checkHasSuppressIssueAndIncrementCount($issue_type)) {
            return;
        }
        $property_alt = self::findAlternateReferencedElementDeclaration($code_base, $property);
        if ($property_alt instanceof Property) {
            if ($property_alt->hasWriteReference()) {
                return;
            }
        }
        // echo "known references to $property: " . implode(array_map('strval', $property->getReferenceList())) . "\n";
        Issue::maybeEmit(
            $code_base,
            $property->getContext(),
            $issue_type,
            $property->getFileRef()->getLineNumberStart(),
            $property->getRepresentationForIssue()
        );
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
