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
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Library\Map;

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
        $total_count = $code_base->totalElementCount();
        $i = 0;

        // Functions
        self::analyzeElementListReferenceCounts(
            $code_base,
            $code_base->getFunctionMap(),
            Issue::UnreferencedMethod,
            $total_count,
            $i
        );

        // Constants
        self::analyzeElementListReferenceCounts(
            $code_base,
            $code_base->getGlobalConstantMap(),
            Issue::UnreferencedConstant,
            $total_count,
            $i
        );

        // Classes
        self::analyzeElementListReferenceCounts(
            $code_base,
            $code_base->getClassMap(),
            Issue::UnreferencedClass,
            $total_count,
            $i
        );

        // Class Maps
        foreach ($code_base->getClassMapMap() as $class_map) {
            self::analyzeClassMapReferenceCounts(
                $code_base,
                $class_map,
                $total_count,
                $i
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * @param ClassMap $class_map
     * @param int $total_count
     * @param int $i
     *
     * @return void
     */
    private static function analyzeClassMapReferenceCounts(
        CodeBase $code_base,
        ClassMap $class_map,
        int $total_count,
        int &$i
    ) {
        // Constants
        self::analyzeElementListReferenceCounts(
            $code_base,
            $class_map->getClassConstantMap(),
            Issue::UnreferencedConstant,
            $total_count,
            $i
        );

        // Properties
        self::analyzeElementListReferenceCounts(
            $code_base,
            $class_map->getPropertyMap(),
            Issue::UnreferencedProperty,
            $total_count,
            $i
        );

        // Methods
        self::analyzeElementListReferenceCounts(
            $code_base,
            $class_map->getMethodMap(),
            Issue::UnreferencedMethod,
            $total_count,
            $i
        );
    }

    /**
     * @param CodeBase $code_base
     * @param Map|array $element_list
     * @param string $issue_type
     * @param int $total_count
     * @param int $i
     *
     * @return void
     */
    private static function analyzeElementListReferenceCounts(
        CodeBase $code_base,
        $element_list,
        string $issue_type,
        int $total_count,
        int &$i
    ) {
        foreach ($element_list as $element) {
            CLI::progress('dead code', (++$i)/$total_count);
            self::analyzeElementReferenceCounts(
                $code_base, $element, $issue_type
            );
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

        // Don't worry about internal elements
        if ($element->isPHPInternal()) {
            return;
        }

        // Skip methods that are overrides of other methods
        if ($element instanceof ClassElement) {
            if ($element instanceof ClassConstant) {
                // should not warn about self::class
                if (strcasecmp($element->getName(), 'class') === 0) {
                    return;
                }
            }
            if ($element->getIsOverride()) {
                return;
            }

            $class_fqsen = $element->getClassFQSEN();

            // Don't analyze elements defined in a parent
            // class
            try {
                if ($class_fqsen != $element->getDefiningClassFQSEN()) {
                    return;
                }
            } catch (CodeBaseException $e) {
                // No defining class for property/constant/etc.
            }

            $defining_class =
                $element->getClass($code_base);

            // Don't analyze elements on interfaces or on
            // abstract classes, as they're uncallable.
            if ($defining_class->isInterface()
                || $defining_class->isAbstract()
                || $defining_class->isTrait()
            ) {
                return;
            }

            // Ignore magic methods
            if ($element instanceof Method) {
                // Doubly nested so that `$element` shows
                // up as Method in Phan.
                if ($element->getIsMagic()) {
                    return;
                }
            }
        }

        // Skip properties on classes that have a magic
        // __get or __set method given that we can't track
        // their access
        if ($element instanceof Property) {
            $defining_class = $element->getClass($code_base);

            if ($defining_class->hasGetOrSetMethod($code_base)) {
                return;
            }
        }

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
}
