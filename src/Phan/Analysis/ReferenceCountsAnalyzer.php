<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CLI;
use Phan\CodeBase;
use Phan\CodeBase\ClassMap;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\ClassElement;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Library\Map;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;

class ReferenceCountsAnalyzer
{
    /**
     * Take a look at all globally accessible elements and see if
     * we can find any dead code that is never referenced
     *
     * @return void
     */
    public static function analyzeReferenceCounts(CodeBase $code_base)
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
    }

    /**
     * @param CodeBase $code_base
     * @param ClassMap $class_map
     * @param int $total_count
     * @param int $i
     *
     * @return \Generator|ClassElement[]
     */
    private static function getElementsFromClassMapForDeferredAnalysis(
        CodeBase $code_base,
        ClassMap $class_map,
        int $total_count,
        int &$i
    ) {
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
     * @param Map|AddressableElement[] $element_list
     * @param string $issue_type
     * @param int $total_count
     * @param int $i
     *
     * @return void
     */
    private static function analyzeGlobalElementListReferenceCounts(
        CodeBase $code_base,
        $element_list,
        string $issue_type,
        int $total_count,
        int &$i
    ) {
        $filtered_element_list = [];
        foreach ($element_list as $element) {
            CLI::progress('dead code', (++$i)/$total_count);
            // Don't worry about internal elements
            if ($element->isPHPInternal()) {
                continue;
            }
            self::analyzeElementReferenceCounts($code_base, $element, $issue_type);
        }
    }

    /**
     * @param CodeBase $code_base
     * @param Map|ClassElement[] $element_list
     * @param int $total_count
     * @param int $i
     *
     * @return \Generator|ClassElement[]
     */
    private static function getElementsFromElementListForDeferredAnalysis(
        CodeBase $code_base,
        $element_list,
        int $total_count,
        int &$i
    ) {
        $filtered_element_list = [];
        foreach ($element_list as $element) {
            CLI::progress('dead code', (++$i)/$total_count);
            // Don't worry about internal elements
            if ($element->isPHPInternal()) {
                continue;
            }
            // Currently, deferred analysis is only needed for class elements, which can be inherited
            // (And we may track the references to the inherited version of the original)
            assert($element instanceof ClassElement);
            if ($element instanceof ClassConstant) {
                // should not warn about self::class
                if (strcasecmp($element->getName(), 'class') === 0) {
                    continue;
                }
            }
            if ($element->getIsOverride()) {
                continue;
            }

            $fqsen = $element->getFQSEN();
            if ($element instanceof Method) {
                $defining_fqsen = $element->getRealDefiningFQSEN();
            } else {
                $defining_fqsen = $element->getDefiningFQSEN();
            }

            // Don't analyze elements defined in a parent
            // class
            if ($fqsen != $defining_fqsen) {
                if ($element->getReferenceCount($code_base) > 0) {
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
                        $defining_element->copyReferencesFrom($element);
                    }
                }
                continue;
            }
            $defining_class =
                $element->getClass($code_base);

            if ($element instanceof Method) {
                // Ignore magic methods
                if ($element->getIsMagic()) {
                    continue;
                }
                // Don't analyze abstract methods, as they're uncallable.
                // (Every method on an interface is abstract)
                if ($element->isAbstract() || $defining_class->isInterface()) {
                    continue;
                }
            } elseif ($element instanceof Property) {
                // Skip properties on classes that have a magic
                // __get or __set method given that we can't track
                // their access
                $defining_class = $element->getClass($code_base);

                if ($defining_class->hasGetOrSetMethod($code_base)) {
                    continue;
                }
            }
            yield $element;
        }
    }

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return void
     */
    private static function analyzeElementReferenceCounts(
        CodeBase $code_base,
        AddressableElement $element,
        string $issue_type
    ) {
        /*
        print "digraph G {\n";
        foreach ($element->getReferenceList() as $file_ref) {
            print "\t\"{$file_ref->getFile()}\" -> \"{$element->getFileRef()->getFile()}\";\n";
        }
        print "}\n";
        */

        if ($element->getReferenceCount($code_base) < 1) {
            if ($element->hasSuppressIssue($issue_type)) {
                return;
            }

            if ($element instanceof AddressableElement) {
                $element_alt = self::findAlternateReferencedElementDeclaration($code_base, $element);
                if (!\is_null($element_alt)) {
                    if ($element_alt->getReferenceCount($code_base) >= 1) {
                        // If there is a reference to the "canonical" declaration (the one which was parsed first),
                        // then also treat it as a reference to the duplicate.
                        return;
                    }
                }
                // Make issue types granular so that these can be fixed in smaller steps.
                // E.g. composer libraries may have unreferenced but used public methods, properties, and class constants.
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
                        if ($element->isPrivate()) {
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
                        $issue_type = Issue::UnreferencedClosure;
                    }
                }

                // If there are duplicate declarations, display issues for unreferenced elements on each declaration.
                Issue::maybeEmit(
                    $code_base,
                    $element->getContext(),
                    $issue_type,
                    $element->getFileRef()->getLineNumberStart(),
                    (string)$element->getFQSEN()
                );
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $element->getContext(),
                    $issue_type,
                    $element->getFileRef()->getLineNumberStart(),
                    (string)$element
                );
            }
        }
    }

    /**
     * @return ?AddressableElement
     */
    public static function findAlternateReferencedElementDeclaration(
        CodeBase $code_base,
        AddressableElement $element
    ) {
        $old_fqsen = $element->getFQSEN();
        if ($old_fqsen instanceof FullyQualifiedGlobalStructuralElement) {
            $fqsen = $old_fqsen->getCanonicalFQSEN();
            if ($fqsen === $old_fqsen) {
                return null;  // $old_fqsen was not an alternaive
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
