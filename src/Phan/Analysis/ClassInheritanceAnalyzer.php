<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * A checker for whether the given Clazz(class/trait/interface) properly inherits
 * from its classes, traits, and/or interfaces.
 */
class ClassInheritanceAnalyzer
{
    /**
     * Checks if the given Clazz(class/trait/interface) properly inherits
     * from its classes, traits, and/or interfaces
     */
    public static function analyzeClassInheritance(
        CodeBase $code_base,
        Clazz $clazz
    ): void {

        // Don't worry about internal classes
        if ($clazz->isPHPInternal()) {
            return;
        }

        if ($clazz->hasParentType()) {
            $class_exists = self::fqsenExistsForClass(
                $clazz->getParentClassFQSEN(),
                $code_base,
                $clazz,
                Issue::UndeclaredExtendedClass
            );

            if ($class_exists) {
                self::testClassAccess(
                    $clazz,
                    $clazz->getParentClass($code_base),
                    $code_base
                );
            }
        }

        foreach ($clazz->getInterfaceFQSENList() as $fqsen) {
            $class_exists = self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                Issue::UndeclaredInterface
            );

            if ($class_exists) {
                self::testClassAccess(
                    $clazz,
                    $code_base->getClassByFQSEN($fqsen),
                    $code_base
                );
            }
        }

        $visited_trait_requirements = [];
        foreach ($clazz->getTraitFQSENList() as $fqsen) {
            $class_exists = self::fqsenExistsForClass(
                $fqsen,
                $code_base,
                $clazz,
                Issue::UndeclaredTrait
            );
            if ($class_exists) {
                $trait = $code_base->getClassByFQSEN($fqsen);
                self::testClassAccess(
                    $clazz,
                    $trait,
                    $code_base
                );
                if (!$clazz->isTrait()) {
                    self::enforceTraitRequirements(
                        $code_base,
                        $clazz,
                        $trait,
                        $clazz->getLinenoOfAncestorReference($fqsen),
                        $visited_trait_requirements
                    );
                }
            }
        }

        foreach ($clazz->getRequiredExtendsFQSENList() as $fqsen) {
            self::analyzeRequiredAncestor($code_base, $clazz, $fqsen, true);
        }
        foreach ($clazz->getRequiredImplementsFQSENList() as $fqsen) {
            self::analyzeRequiredAncestor($code_base, $clazz, $fqsen, false);
        }
    }

    /**
     * Checks that the target of `@require-extends` is a declared class, and that the target of `@require-implements` is a declared interface.
     *
     * @param Clazz $clazz the class-like with the annotation (usually a trait)
     * @param FullyQualifiedClassName $fqsen the target of the annotation
     * @param bool $is_extends true for `@require-extends`, false for `@require-implements`
     */
    private static function analyzeRequiredAncestor(
        CodeBase $code_base,
        Clazz $clazz,
        FullyQualifiedClassName $fqsen,
        bool $is_extends
    ): void {
        if (!$code_base->hasClassWithFQSEN($fqsen)) {
            $context = $clazz->getInternalContext();
            Issue::maybeEmitWithParameters(
                $code_base,
                $context,
                Issue::UndeclaredClassReference,
                $context->getLineNumberStart(),
                [(string)$fqsen],
                IssueFixSuggester::suggestSimilarClass(
                    $code_base,
                    $context,
                    $fqsen,
                    IssueFixSuggester::createFQSENFilterForClasslikeCategories($code_base, $is_extends, false, !$is_extends)
                )
            );
            return;
        }
        $required_class = $code_base->getClassByFQSENWithoutHydrating($fqsen);
        if ($is_extends ? !$required_class->isClass() : !$required_class->isInterface()) {
            $clazz->emitWrongInheritanceCategoryWarning($code_base, $required_class, $is_extends ? 'Class' : 'Interface', 0);
        }
    }

    /**
     * @return bool
     * True if the FQSEN exists. If not, a log line is emitted
     */
    private static function fqsenExistsForClass(
        FullyQualifiedClassName $fqsen,
        CodeBase $code_base,
        Clazz $clazz,
        string $issue_type
    ): bool {
        if (!$code_base->hasClassWithFQSEN($fqsen)) {
            $filter = null;
            switch ($issue_type) {
                case Issue::UndeclaredExtendedClass:
                    $filter = IssueFixSuggester::createFQSENFilterForClasslikeCategories($code_base, true, false, false);
                    break;
                case Issue::UndeclaredTrait:
                    $filter = IssueFixSuggester::createFQSENFilterForClasslikeCategories($code_base, false, true, false);
                    break;
                case Issue::UndeclaredInterface:
                    $filter = IssueFixSuggester::createFQSENFilterForClasslikeCategories($code_base, false, false, true);
                    break;
            }
            $suggestion = IssueFixSuggester::suggestSimilarClass($code_base, $clazz->getContext(), $fqsen, $filter);

            Issue::maybeEmitWithParameters(
                $code_base,
                $clazz->getContext(),
                $issue_type,
                $clazz->getLinenoOfAncestorReference($fqsen),
                [(string)$fqsen],
                $suggestion
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string,bool> $visited
     */
    private static function enforceTraitRequirements(
        CodeBase $code_base,
        Clazz $using_class,
        Clazz $trait,
        int $lineno,
        array &$visited
    ): void {
        $trait_key = (string)$trait->getFQSEN();
        if (isset($visited[$trait_key])) {
            return;
        }
        $visited[$trait_key] = true;
        $required_extends = $trait->getRequiredExtendsFQSENList();
        $required_implements = $trait->getRequiredImplementsFQSENList();
        $ancestor_set = ($required_extends || $required_implements) ? self::getClassAndAncestorSet($code_base, $using_class) : [];
        foreach ($required_extends as $required_fqsen) {
            if (isset($ancestor_set[\strtolower((string)$required_fqsen)])) {
                continue;
            }
            Issue::maybeEmit(
                $code_base,
                $using_class->getInternalContext(),
                Issue::TraitRequireExtendsMissing,
                $lineno,
                (string)$trait->getFQSEN(),
                (string)$using_class->getFQSEN(),
                (string)$required_fqsen
            );
        }
        foreach ($required_implements as $required_fqsen) {
            if (isset($ancestor_set[\strtolower((string)$required_fqsen)])) {
                continue;
            }
            Issue::maybeEmit(
                $code_base,
                $using_class->getInternalContext(),
                Issue::TraitRequireImplementsMissing,
                $lineno,
                (string)$trait->getFQSEN(),
                (string)$using_class->getFQSEN(),
                (string)$required_fqsen
            );
        }
        foreach ($trait->getTraitFQSENList() as $nested_fqsen) {
            if (!$code_base->hasClassWithFQSEN($nested_fqsen)) {
                continue;
            }
            self::enforceTraitRequirements(
                $code_base,
                $using_class,
                $code_base->getClassByFQSEN($nested_fqsen),
                $lineno,
                $visited
            );
        }
    }

    /**
     * Returns the lowercase FQSENs of $class, its parent classes and its interfaces (and their aliases).
     *
     * Unlike Type::asExpandedTypes(), this does not include the classes and interfaces that the traits used by
     * these classes require, which would make every class using a trait meet its requirements.
     *
     * @return array<string,true>
     */
    private static function getClassAndAncestorSet(CodeBase $code_base, Clazz $class): array
    {
        $result = [];
        $queue = [$class->getFQSEN()];
        while ($queue) {
            $fqsen = \array_pop($queue);
            $key = \strtolower((string)$fqsen);
            if (isset($result[$key]) || !$code_base->hasClassWithFQSEN($fqsen)) {
                continue;
            }
            $result[$key] = true;
            foreach ($code_base->getClassAliasesByFQSEN($fqsen) as $alias_record) {
                $result[\strtolower((string)$alias_record->alias_fqsen)] = true;
            }
            $current = $code_base->getClassByFQSENWithoutHydrating($fqsen);
            foreach ($current->getInterfaceFQSENList() as $interface_fqsen) {
                $queue[] = $interface_fqsen;
            }
            if ($current->hasParentType()) {
                $queue[] = $current->getParentClassFQSEN();
            }
        }
        return $result;
    }

    /**
     * @param Clazz $source_class
     * The class accessing the $target_class
     *
     * @param Clazz $target_class
     * The class being accessed from the $source_class
     *
     * @param CodeBase $code_base
     * The code base in which both classes exist
     */
    private static function testClassAccess(
        Clazz $source_class,
        Clazz $target_class,
        CodeBase $code_base
    ): void {
        if ($target_class->isNSInternal($code_base)
            && !$target_class->isNSInternalAccessFromContext(
                $code_base,
                $source_class->getContext()
            )
        ) {
            Issue::maybeEmit(
                $code_base,
                $source_class->getInternalContext(),
                Issue::AccessClassInternal,
                $source_class->getFileRef()->getLineNumberStart(),
                (string)$target_class,
                $target_class->getFileRef()->getFile(),
                (string)$target_class->getFileRef()->getLineNumberStart()
            );
        }
        $target_class_fqsen = $target_class->getFQSEN();
        if ($target_class->isDeprecated()) {
            if ($target_class->isTrait()) {
                $issue_type = Issue::DeprecatedTrait;
            } elseif ($target_class->isInterface()) {
                $issue_type = Issue::DeprecatedInterface;
            } else {
                $issue_type = Issue::DeprecatedClass;
            }
            Issue::maybeEmit(
                $code_base,
                $source_class->getInternalContext(),
                $issue_type,
                $source_class->getFileRef()->getLineNumberStart(),
                $target_class_fqsen,
                $target_class->getContext()->getFile(),
                $target_class->getContext()->getLineNumberStart(),
                $target_class->getDeprecationReason()
            );
        }
        // TODO: Make this also work for classes implementing an interface that extends Serializable.
        if (!$source_class->isInterface() &&
            $target_class_fqsen->getName() === 'Serializable' && $target_class_fqsen->getNamespace() === '\\') {
            // Must define both __serialize and __unserialize to suppress php 8.1's warning.
            if (!$source_class->hasMethodWithName($code_base, '__serialize', true) || !$source_class->hasMethodWithName($code_base, '__unserialize', true)) {
                Issue::maybeEmit(
                    $code_base,
                    $source_class->getInternalContext(),
                    Issue::CompatibleSerializeInterfaceDeprecated,
                    $source_class->getFileRef()->getLineNumberStart(),
                    $source_class->getFQSEN()
                );
            }
        }
        if ($target_class->isInterface() && !$source_class->isEnum() && !$source_class->isInterface()) {
            if (\in_array(\strtolower($target_class->getFQSEN()->__toString()), ['\unitenum', '\backedenum'], true)) {
                Issue::maybeEmit(
                    $code_base,
                    $source_class->getInternalContext(),
                    Issue::EnumCannotImplement,
                    $source_class->getFileRef()->getLineNumberStart(),
                    $source_class->getFQSEN(),
                    $target_class->getFQSEN()
                );
            }
        }
    }
}
