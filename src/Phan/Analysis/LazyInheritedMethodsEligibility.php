<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Library\PhaseTimer;
use Phan\Phan;

/**
 * Decides whether the copy of a method that a class inherits from its parent class could be created lazily,
 * i.e. on the first lookup of the method by name instead of when the class is hydrated.
 *
 * Lazy copies are not implemented yet: for now, Clazz::importAncestorClass() only counts how many copies would be lazy
 * (and why the others would not), as lazy_would_be_* notes of --dump-phase-timings/--phase-timings-json
 * (countParentImportEntry()). The counting costs a few lookups per inherited copy, so it is off unless the
 * environment variable PHAN_COUNT_LAZY_INHERITED_METHODS is set to 1 (isCountingEnabled()).
 *
 * The checks have no side effects: they do not hydrate classes, load internal classes, initialize scopes or fill caches.
 *
 * @internal
 */
final class LazyInheritedMethodsEligibility
{
    /** Prefix of the PhaseTimer notes recorded by countParentImportEntry() */
    public const NOTE_PREFIX = 'lazy_would_be_';

    /** @var array<string,int> the counters, by PhaseTimer note name */
    private static $counts = [];

    /**
     * @var array<int,Method> the roots of the copies that would be lazy, by spl_object_id()
     * (the objects are kept so that their ids are not reused)
     */
    private static $template_roots = [];

    /**
     * @var array<int,list<int>> for each root in $template_roots, the state versions recorded at its first
     * would-be lazy import, or at the last one where they had changed (see getStateVersionsOfRoot())
     */
    private static $template_root_versions = [];

    /**
     * @var array<int,true> the roots in $template_roots whose state versions changed at least once
     */
    private static $mutated_roots = [];

    /**
     * @var bool whether the class phase is running (from the start of hydration until the end of Analysis::analyzeClasses()):
     * lazy copies are only created then.
     */
    private static $is_creation_window_open = false;

    /** @var ?bool whether the counting is enabled (PHAN_COUNT_LAZY_INHERITED_METHODS=1 and phase timings on); null = not decided yet */
    private static $is_counting_enabled = null;

    /**
     * Returns whether Clazz::importAncestorClass() should count the copies that could be lazy (countParentImportEntry()):
     * only with PHAN_COUNT_LAZY_INHERITED_METHODS=1 and --dump-phase-timings/--phase-timings-json.
     */
    public static function isCountingEnabled(): bool
    {
        // (Tests enable it with setCountingEnabledForTests(); resetCounts() clears the decision)
        return self::$is_counting_enabled ??= (PhaseTimer::$enabled && \getenv('PHAN_COUNT_LAZY_INHERITED_METHODS') === '1');
    }

    /**
     * Enable or disable the counting regardless of the environment (for unit tests; null = decide from the environment).
     */
    public static function setCountingEnabledForTests(?bool $is_enabled): void
    {
        self::$is_counting_enabled = $is_enabled;
    }

    /**
     * Called when hydration starts (Phan::finishAnalyzingRemainingStatements()) and when Analysis::analyzeClasses() is done.
     */
    public static function setCreationWindowOpen(bool $is_open): void
    {
        self::$is_creation_window_open = $is_open;
    }

    /**
     * The per-import part of the eligibility of the methods that $class inherits from its parent class $parent.
     *
     * @return ?string null if the methods of this import may be lazy, otherwise the reason why none is
     */
    public static function getReasonForImport(CodeBase $code_base, Clazz $class, Clazz $parent): ?string
    {
        // Step 1 also checks that lazy inheritance is enabled; no option exists yet, so it is treated as enabled.
        if (!self::$is_creation_window_open) {
            return 'window';
        }
        if (!$class->isClass()) {
            return 'not_class';
        }
        if ($class->isPHPInternal()) {
            return 'internal_class';
        }
        if (!$parent->isClass()) {
            return 'not_class';
        }
        if ($parent->isHydrating()) {
            // An inheritance cycle, or the hydration of $class was started from the hydration of $parent.
            return 'hydrating';
        }
        if ($code_base->sawClassHydratedBeforeAncestor()) {
            return 'hydrating_fallback';
        }
        if ($parent->getTemplateTypeMap() !== []) {
            // A generic parent: the copies are specialized by the template parameter type map.
            return 'template';
        }
        if ($code_base->mayHaveAlternatesOfClass($parent->getFQSEN())) {
            return 'class_alternates';
        }
        return null;
    }

    /**
     * The per-entry part of the eligibility of the method that $class inherits from its parent class $parent
     * under the lowercase name $lowercase_name (the key of the parent's class map).
     *
     * This must be called before the copy is added to the class map of $class.
     *
     * @param Method $entry the entry of the parent's class map for $lowercase_name
     * @return ?string null if the copy may be lazy, otherwise the reason why not
     */
    public static function getReasonForEntry(
        CodeBase $code_base,
        Clazz $class,
        Clazz $parent,
        string $lowercase_name,
        Method $entry
    ): ?string {
        if ($lowercase_name === '__construct') {
            return 'construct';
        }
        if (\str_contains($lowercase_name, ',') ||
            // @phan-suppress-next-line PhanAccessMethodInternal
            $code_base->getMethodEntryByClassAndLowercaseName($parent->getFQSEN(), "$lowercase_name,1") !== null) {
            return 'alternate';
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        if ($code_base->getMethodEntryByClassAndLowercaseName($class->getFQSEN(), $lowercase_name) !== null) {
            // A declared method, (at)method, or a copy from an interface, a trait or a trait alias.
            return 'multi_provider';
        }
        // Every entry is a Method for now, so the entry is the root of the copy.
        $root = $entry;
        if (!$root->isPHPInternal() && !Phan::isExcludedAnalysisFile($root->getContext()->getFile())) {
            return 'project_root';
        }
        return self::getReasonForReturnTypeLink($code_base, $root);
    }

    /**
     * Whether the return type link of a copy of $root (Method::ensureClonesReturnType()) allows the copy to be lazy.
     *
     * The link target's return type can change when it is analyzed, which would change the decision taken by a copy
     * created later. A copy may be lazy if it does not link, or if the target cannot have a template type in its
     * inferred type and the method has no yield.
     *
     * @return ?string null if the copy may be lazy, otherwise the reason why not
     */
    public static function getReasonForReturnTypeLink(CodeBase $code_base, Method $root): ?string
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $link_target = $root->getReturnTypeLinkOfClone();
        if ($link_target === null) {
            return null;
        }
        $link_target_class_fqsen = $link_target->getClassFQSEN();
        if (!$code_base->getUserDefinedClassMap()->offsetExists($link_target_class_fqsen)) {
            return 'linked_return';
        }
        if ($code_base->getClassByFQSENWithoutHydrating($link_target_class_fqsen)->getTemplateTypeMap() !== [] ||
            $link_target->getComment()?->getTemplateTypeList()) {
            return 'linked_template';
        }
        if ($root->hasYield()) {
            return 'linked_yield';
        }
        return null;
    }

    /**
     * Count whether the copy of the method that $class inherits from its parent $parent
     * under the lowercase name $lowercase_name would be lazy (only called if PhaseTimer::$enabled).
     *
     * @param ?string $import_reason the result of getReasonForImport() for this import
     * @param Method $entry the entry of the parent's class map for $lowercase_name
     */
    public static function countParentImportEntry(
        CodeBase $code_base,
        Clazz $class,
        Clazz $parent,
        ?string $import_reason,
        string $lowercase_name,
        Method $entry
    ): void {
        self::increment('copies_parent');
        $reason = $import_reason ?? self::getReasonForEntry($code_base, $class, $parent, $lowercase_name, $entry);
        if ($reason !== null) {
            self::increment('ineligible_' . $reason);
            return;
        }
        self::increment('eligible');
        // Every entry is a Method for now, so the entry is the root of the copy.
        // @phan-suppress-next-line PhanAccessMethodInternal
        if ($entry->getReturnTypeLinkOfClone() !== null) {
            self::increment('eligible_relaxed_link');
        }
        $id = \spl_object_id($entry);
        $versions = self::getStateVersionsOfRoot($entry);
        if (!isset(self::$template_roots[$id])) {
            self::$template_roots[$id] = $entry;
            self::$template_root_versions[$id] = $versions;
            self::increment('templates');
            return;
        }
        if (self::$template_root_versions[$id] !== $versions) {
            // A lazy copy created from a remembered clone of the root would have to remember a new clone here.
            self::$template_root_versions[$id] = $versions;
            self::increment('template_recaptures');
            if (!isset(self::$mutated_roots[$id])) {
                self::$mutated_roots[$id] = true;
                self::increment('roots_mutated');
            }
        }
    }

    /**
     * Returns the state versions of $root, its internal scope (plus the sum of the versions of the variables in it)
     * and its parameters: if any of them changes, the state that a copy of $root would be cloned from has changed.
     *
     * @return list<int>
     */
    private static function getStateVersionsOfRoot(Method $root): array
    {
        $scope = $root->getInternalScope();
        $variable_versions = 0;
        foreach ($scope->getVariableMap() as $variable) {
            $variable_versions += $variable->getStateVersion();
        }
        $versions = [$root->getStateVersion(), $scope->getStateVersion(), $variable_versions];
        foreach ($root->getParameterList() as $parameter) {
            $versions[] = $parameter->getStateVersion();
        }
        return $versions;
    }

    private static function increment(string $name): void
    {
        $key = self::NOTE_PREFIX . $name;
        $count = (self::$counts[$key] ?? 0) + 1;
        self::$counts[$key] = $count;
        PhaseTimer::note($key, $count);
    }

    /**
     * @return array<string,int> the counters recorded by countParentImportEntry(), by PhaseTimer note name
     */
    public static function getCounts(): array
    {
        return self::$counts;
    }

    /**
     * Discard the counters (for unit tests).
     */
    public static function resetCounts(): void
    {
        self::$counts = [];
        self::$template_roots = [];
        self::$is_counting_enabled = null;
        self::$template_root_versions = [];
        self::$mutated_roots = [];
    }
}
