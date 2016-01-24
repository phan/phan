<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassElement;
use Phan\Language\FQSEN\FullyQualifiedMethodName;

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
        if (!Config::get()->dead_code_detection) {
            return;
        }

        // Get the count of all known elements
        $total_count = (
            count($code_base->getMethodMap(), COUNT_RECURSIVE)
            + count($code_base->getPropertyMap(), COUNT_RECURSIVE)
            + count($code_base->getConstantMap(), COUNT_RECURSIVE)
            + count($code_base->getClassMap(), COUNT_RECURSIVE)
        );

        $i = 0;
        $analyze_list = function ($list, string $issue_type) use ($code_base, &$i, $total_count) {
            foreach ($list as $name => $element) {
                CLI::progress('dead code', (++$i)/$total_count);
                self::analyzeElementReferenceCounts($code_base, $element, $issue_type);
            }
        };

        $analyze_map = function ($map, string $issue_type) use ($code_base, &$i, $total_count) {
            foreach ($map as $fqsen_string => $list) {
                foreach ($list as $name => $element) {
                    CLI::progress('dead code', (++$i)/$total_count);

                    // Don't worry about internal elements
                    if ($element->getContext()->isInternal()) {
                        continue;
                    }

                    $element_fqsen = $element->getFQSEN();

                    if (0 !== strpos((string)$element_fqsen, $fqsen_string)) {
                        continue;
                    }

                    // Skip methods that are overrides of other methods
                    if ($element_fqsen instanceof FullyQualifiedMethodName) {
                        if ($element->getIsOverride()) {
                            continue;
                        }
                    }

                    // Skip properties on classes that have a magic
                    // __get or __set method given that we can't track
                    // their access
                    if ($element instanceof Property) {
                        $defining_class = $element->getDefiningClass($code_base);

                        if ($defining_class->hasMethodWithName($code_base, '__set')
                            || $defining_class->hasMethodWithName($code_base, '__get')
                        ) {
                            continue;
                        }
                    }

                    if ($element_fqsen instanceof FullyQualifiedClassElement) {
                        $class_fqsen = $element->getDefiningClassFQSEN();

                        // Don't analyze elements defined in a parent
                        // class
                        if ((string)$class_fqsen !== $fqsen_string) {
                            continue;
                        }

                        $defining_class =
                            $element->getDefiningClass($code_base);

                        // Don't analyze elements on interfaces or on
                        // abstract classes, as they're uncallable.
                        if ($defining_class->isInterface()
                            || $defining_class->isAbstract()
                            || $defining_class->isTrait()
                        ) {
                            continue;
                        }

                        // Ignore magic methods
                        if ($element instanceof Method) {
                            // Doubly nested so that `$element` shows
                            // up as Method in Phan.
                            if ($element->getIsMagic()) {
                                continue;
                            }
                        }

                    }

                    self::analyzeElementReferenceCounts($code_base, $element, $issue_type);
                }
            }
        };

        $analyze_map($code_base->getMethodMap(), Issue::UnreferencedMethod);
        $analyze_map($code_base->getPropertyMap(), Issue::UnreferencedProperty);
        $analyze_map($code_base->getConstantMap(), Issue::UnreferencedConstant);
        $analyze_list($code_base->getClassMap(), Issue::UnreferencedClass);
    }

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeElementReferenceCounts(
        CodeBase $code_base,
        AddressableElement $element,
        string $issue_type
    ) {

        // Don't worry about internal elements
        if ($element->getContext()->isInternal()) {
            return;
        }

        /*
        print "digraph G {\n";
        foreach ($element->getReferenceList() as $file_ref) {
            print "\t\"{$file_ref->getFile()}\" -> \"{$element->getContext()->getFile()}\";\n";
        }
        print "}\n";
        */

        if ($element->getReferenceCount($code_base) < 1) {
            if ($element->hasSuppressIssue($issue_type)) {
                return;
            }

            if ($element instanceof AddressableElement) {
                Issue::emit(
                    $issue_type,
                    $element->getContext()->getFile(),
                    $element->getContext()->getLineNumberStart(),
                    (string)$element->getFQSEN()
                );
            } else {
                Issue::emit(
                    $issue_type,
                    $element->getContext()->getFile(),
                    $element->getContext()->getLineNumberStart(),
                    (string)$element
                );
            }
        }
    }
}
