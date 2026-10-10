<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Closure;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Type;
use Phan\Language\Type\IntersectionType;
use Phan\Language\UnionType;
use Phan\Library\PhaseTimer;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Throwable;

/**
 * Decides whether Analysis::analyzeFunctions() runs the checks that only emit issues
 * about the declaration of a function or method.
 *
 * Issues located in files excluded from analysis are discarded by Issue::emitInstance().
 * For functions and methods declared in those files (vendor code, and the methods that
 * classes inherit from it), analyzeFunctions() therefore only runs the steps that modify
 * the element or other state that the rest of the analysis reads (scope initialization,
 * phpdoc narrowing and inheritance, inferred return types, inherited (at)throws, attributes,
 * duplicate definitions, plugins), and skips the checks whose only effect is to emit issues
 * about the declaration (all of them located in the excluded file).
 * The checks that look up the classes used in parameter and return types still run when
 * a type is seen for the first time, because they load internal classes (see modeForClassLookup()).
 *
 * It also decides whether analyzeFunctions() skips two walks over the methods that an inherited method overrides
 * which cannot find anything (see beginAnalyzingMethod()).
 * (CloneReplay decides whether analyzeFunctions() replays the declaration analysis of an inherited method.)
 *
 * Environment variables (read once):
 *
 * - `PHAN_ANALYZE_EXCLUDED_METHODS=1` always runs the checks (the previous behavior).
 * - `PHAN_VERIFY_SKIPPED_CHECKS=1` runs the checks for elements declared in excluded files,
 *   and verifies that each group of checks neither emitted an issue that would be reported,
 *   nor modified the element, nor threw. It also runs the skipped walks over overridden methods,
 *   and verifies that they found nothing, hydrated no class, initialized the scope of no user-defined method and did not throw,
 *   and that the overridden methods they computed are the same at the end of the method analysis phase.
 *   For the inherited methods whose declaration analysis CloneReplay would replay, it runs that analysis instead,
 *   and verifies that it checked or emitted no issue, had no side effects other than initializing the scope of the method,
 *   and produced the state that the replay would have produced.
 *   Exits with an error after the method analysis phase if any of these verifications failed.
 * - `PHAN_DISABLE_CLONE_REPLAY=1` always runs the declaration analysis of inherited methods (see CloneReplay).
 * - `PHAN_DUMP_METHOD_STATE_DIGEST=<path>` writes the state of every user-defined function and method
 *   to `<path>` once the analysis phase is about to start, to compare runs with and without
 *   `PHAN_ANALYZE_EXCLUDED_METHODS=1` (or `PHAN_DISABLE_CLONE_REPLAY=1`).
 *
 * This is not part of Phan's plugin API.
 */
final class EmitOnlyChecks
{
    /** Run the checks that only emit issues (elements declared in analyzed files) */
    public const RUN = 0;
    /** Skip the checks that only emit issues (elements declared in files excluded from analysis) */
    public const SKIP = 1;
    /** Run the checks that only emit issues, and verify that skipping them would not change anything (PHAN_VERIFY_SKIPPED_CHECKS=1) */
    public const VERIFY = 2;

    /** @var ?int the cached result of self::modeForExcludedFiles() */
    private static $mode_for_excluded_files = null;

    /** @var array<string,array{0:int,1:int}> maps the label of each group of checks that was verified to the number of times and nanoseconds it ran */
    private static $verified_checks = [];

    /** @var list<string> descriptions of the groups of checks that failed verification */
    private static $violations = [];

    /** @var array<string,array<int,Type>> the types whose classes were looked up with self::SKIP (or self::VERIFY), by kind of lookup and object id */
    private static $looked_up_types = [];

    /** @var array<string,array<int,UnionType>> the union types whose types were all looked up with self::SKIP (or self::VERIFY), by kind of lookup and object id */
    private static $looked_up_union_types = [];

    /** The walk over overridden methods in FunctionTrait::addParamToScopeOfFunctionOrMethod() (inherited `@phan-mandatory-param`) */
    public const MANDATORY_PARAM_WALK = 'overridden methods walk for (at)phan-mandatory-param';
    /** The walk over overridden methods in ThrowsTypesAnalyzer::maybeInheritPHPDocThrowsTypes() (inherited `@throws`) */
    public const THROWS_WALK = 'overridden methods walk for (at)throws';

    /**
     * @var ?Method the inherited method that Analysis::analyzeFunctions() is processing, if its walk for MANDATORY_PARAM_WALK can be skipped
     */
    public static $method_with_skippable_mandatory_param_walk = null;

    /**
     * @var ?Method the inherited method that Analysis::analyzeFunctions() is processing, if its walk for THROWS_WALK can be skipped
     */
    public static $method_with_skippable_throws_walk = null;

    /**
     * @var bool whether to run and verify the skippable walks (PHAN_VERIFY_SKIPPED_CHECKS=1)
     */
    public static $verify_skipped_ancestor_walks = false;

    /**
     * @var array<string,int> the number of skipped walks (for MANDATORY_PARAM_WALK, one per parameter), by kind of walk
     */
    public static $skipped_ancestor_walk_count = [self::MANDATORY_PARAM_WALK => 0, self::THROWS_WALK => 0];

    /** @var array<string,int> the number of methods whose walk could be skipped, by kind of walk */
    private static $skippable_ancestor_walk_method_count = [self::MANDATORY_PARAM_WALK => 0, self::THROWS_WALK => 0];

    /** @var list<Method> with PHAN_VERIFY_SKIPPED_CHECKS=1, the methods whose walks could be skipped */
    private static $methods_with_skippable_ancestor_walks = [];

    /**
     * @return int self::SKIP (the default), self::RUN (PHAN_ANALYZE_EXCLUDED_METHODS=1) or self::VERIFY (PHAN_VERIFY_SKIPPED_CHECKS=1)
     */
    public static function modeForExcludedFiles(): int
    {
        return self::$mode_for_excluded_files ??= self::computeModeForExcludedFiles();
    }

    private static function computeModeForExcludedFiles(): int
    {
        if (\getenv('PHAN_VERIFY_SKIPPED_CHECKS')) {
            return self::VERIFY;
        }
        if (\getenv('PHAN_ANALYZE_EXCLUDED_METHODS')) {
            return self::RUN;
        }
        return self::SKIP;
    }

    /**
     * Called by Analysis::analyzeFunctions() before and after analyzing the functions and methods.
     */
    public static function resetLookedUpTypes(): void
    {
        self::$looked_up_types = [];
        self::$looked_up_union_types = [];
    }

    /**
     * Called by Analysis::analyzeFunctions() before it processes the (user-defined) method $method.
     *
     * For an inherited method (a copy of the method of an ancestor class, trait or interface), this records
     * whether the walks over the methods it overrides (Method::getOverriddenMethods()) for an inherited
     * `@phan-mandatory-param` (FunctionTrait::addParamToScopeOfFunctionOrMethod()) and for inherited `@throws`
     * types (ThrowsTypesAnalyzer) can be skipped while it is processed, because they cannot find anything
     * and have no side effects:
     *
     * - The overridden methods are the methods with the same (lowercase) name in the class maps of the ancestors
     *   of its class. CodeBase::addMethod() records the names of all methods whose doc comment has a parameter marked
     *   `@phan-mandatory-param` or declares `@throws` types. If no method with this name has (at)throws types,
     *   none has inherited (at)throws types either (they are only inherited from overridden methods, which have the same name).
     * - Inherited methods are created when their class is hydrated, after it hydrated its ancestors. If the hydration of
     *   an ancestor was not finished at that point (an inheritance cycle, or a class hydrated as a side effect of hydrating
     *   its ancestor), CodeBase::sawClassHydratedBeforeAncestor() is true and nothing is skipped. Otherwise, the ancestors
     *   were hydrated, and Phan only adds methods to the class map of a class while parsing, while loading an internal class
     *   or stub, while hydrating that class, and when it creates a default constructor (`__construct` is never skipped).
     *   So every overridden method was added to the method set before $method, and Analysis::analyzeFunctions() initialized
     *   its scope before processing $method, unless it is an internal method: the walk would neither initialize the scope
     *   of a user-defined method nor hydrate a class. (It can initialize the scope of an internal method, which only sets
     *   a flag and may add `$this` to a scope that is never read: internal methods have no body to analyze.)
     * - The walk caches its result in $method. When it is skipped, it is computed when it is first needed, with the same
     *   result, because the class maps of the ancestors no longer change.
     *
     * The walks are only skipped while analyzeFunctions() processes $method: when they run earlier (e.g. when
     * a method of a subclass that is processed before $method initializes the scope of $method),
     * some overridden methods may not have been processed yet.
     */
    public static function beginAnalyzingMethod(CodeBase $code_base, Method $method): void
    {
        if ($method->getDefiningFQSEN() === $method->getFQSEN() || $code_base->sawClassHydratedBeforeAncestor()) {
            return;
        }
        $lowercase_name = \strtolower($method->getName());
        if ($lowercase_name === '__construct') {
            return;
        }
        $is_skippable = false;
        if (!$code_base->mayHaveMethodWithMandatoryPHPDocParam($lowercase_name)) {
            self::$method_with_skippable_mandatory_param_walk = $method;
            self::$skippable_ancestor_walk_method_count[self::MANDATORY_PARAM_WALK]++;
            $is_skippable = true;
        }
        if (!$code_base->mayHaveMethodWithThrows($lowercase_name)) {
            self::$method_with_skippable_throws_walk = $method;
            self::$skippable_ancestor_walk_method_count[self::THROWS_WALK]++;
            $is_skippable = true;
        }
        if ($is_skippable && self::$verify_skipped_ancestor_walks) {
            self::$methods_with_skippable_ancestor_walks[] = $method;
        }
    }

    /**
     * Called by Analysis::analyzeFunctions() before it processes the methods.
     *
     * @param bool $verify whether to run the skippable walks and verify them (PHAN_VERIFY_SKIPPED_CHECKS=1)
     */
    public static function beginAnalyzingMethods(bool $verify): void
    {
        self::$verify_skipped_ancestor_walks = $verify;
        self::$skipped_ancestor_walk_count = [self::MANDATORY_PARAM_WALK => 0, self::THROWS_WALK => 0];
        self::$skippable_ancestor_walk_method_count = [self::MANDATORY_PARAM_WALK => 0, self::THROWS_WALK => 0];
        self::$methods_with_skippable_ancestor_walks = [];
    }

    /**
     * Called by Analysis::analyzeFunctions() after it processed a method.
     */
    public static function endAnalyzingMethod(): void
    {
        self::$method_with_skippable_mandatory_param_walk = null;
        self::$method_with_skippable_throws_walk = null;
    }

    /**
     * Run $walk, a walk over the overridden methods of $method that is skipped without PHAN_VERIFY_SKIPPED_CHECKS=1,
     * and record a violation if it found something, hydrated a class, initialized the scope of a user-defined method or threw.
     *
     * @param Closure():bool $walk returns true if it found an overridden method with what it looks for
     * @return bool the result of $walk
     */
    public static function verifySkippedAncestorWalk(CodeBase $code_base, Method $method, string $kind, Closure $walk): bool
    {
        $label = "skipped $kind";
        $counts_before = self::getSideEffectCounts($code_base);
        $emit_attempt_count = Issue::$emit_attempt_count;
        $start_ns = \hrtime(true);
        try {
            $found = $walk();
        } catch (Throwable $e) {
            self::recordViolation($method, $label, 'threw ' . \get_class($e) . ': ' . $e->getMessage());
            throw $e;
        } finally {
            $stats = self::$verified_checks[$label] ?? [0, 0];
            self::$verified_checks[$label] = [$stats[0] + 1, $stats[1] + (\hrtime(true) - $start_ns)];
            // The walk is skipped without PHAN_VERIFY_SKIPPED_CHECKS=1 (see self::verify())
            Issue::$emit_attempt_count = $emit_attempt_count;
        }
        if ($found) {
            self::recordViolation($method, $label, 'found an overridden method with what it looks for');
        }
        $counts_after = self::getSideEffectCounts($code_base);
        if ($counts_after !== $counts_before) {
            self::recordViolation($method, $label, 'had side effects (hydrations, scope initializations, methods, classes): ' . \json_encode($counts_before) . ' -> ' . \json_encode($counts_after));
        }
        return $found;
    }

    /**
     * With PHAN_VERIFY_SKIPPED_CHECKS=1, verify that the overridden methods of the methods whose walks could be skipped
     * are the same at the end of the method analysis phase as when the methods were processed (or first needed them).
     */
    private static function verifyOverriddenMethodsOfSkippableWalks(CodeBase $code_base): void
    {
        $counts_before = self::getSideEffectCounts($code_base);
        foreach (self::$methods_with_skippable_ancestor_walks as $method) {
            try {
                $overridden_methods = $method->getOverriddenMethods($code_base);
            } catch (CodeBaseException) {
                $overridden_methods = null;
            }
            try {
                $recomputed_overridden_methods = $method->computeOverriddenMethods($code_base);
            } catch (CodeBaseException) {
                $recomputed_overridden_methods = null;
            }
            if ($recomputed_overridden_methods !== $overridden_methods) {
                self::recordViolation($method, 'skippable walks', 'the overridden methods changed after the method was processed');
            }
        }
        $counts_after = self::getSideEffectCounts($code_base);
        if ($counts_after !== $counts_before) {
            CLI::printToStderr('PHAN_VERIFY_SKIPPED_CHECKS: recomputing overridden methods had side effects: ' . \json_encode($counts_before) . ' -> ' . \json_encode($counts_after) . "\n");
            self::$violations[] = 'recomputing overridden methods had side effects';
        }
        self::$methods_with_skippable_ancestor_walks = [];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int} the number of class hydrations, scope initializations of user-defined methods,
     * methods and classes (including the internal classes loaded so far) so far
     */
    public static function getSideEffectCounts(CodeBase $code_base): array
    {
        return [Clazz::getHydrationCount(), Method::getScopeInitializationCount(), \count($code_base->getMethodSet()), $code_base->getLoadedClassCount()];
    }

    /**
     * Record the number of inherited methods whose walks could be skipped and the number of skipped walks (for --dump-phase-timings)
     */
    public static function recordAncestorWalkStats(): void
    {
        PhaseTimer::note('inherited_methods_skippable_mandatory_param_walk', self::$skippable_ancestor_walk_method_count[self::MANDATORY_PARAM_WALK]);
        PhaseTimer::note('inherited_methods_skippable_throws_walk', self::$skippable_ancestor_walk_method_count[self::THROWS_WALK]);
        PhaseTimer::note('skipped_mandatory_param_walks', self::$skipped_ancestor_walk_count[self::MANDATORY_PARAM_WALK]);
        PhaseTimer::note('skipped_throws_walks', self::$skipped_ancestor_walk_count[self::THROWS_WALK]);
    }

    /**
     * Returns how to run a check that looks up the classes used in $union_type (for the lookup $kind):
     * self::RUN to run it, self::SKIP to skip it, or self::VERIFY to run it and verify that skipping it changes nothing
     * (with PHAN_VERIFY_SKIPPED_CHECKS=1, when it would be skipped without it).
     *
     * Some checks look up the classes used in a parameter or return type, and suggest similar class names for
     * undeclared classes. This loads internal classes (and their ancestors when the class is hydrated), and the rest
     * of the analysis depends on which internal classes are loaded: e.g. Analysis::loadMethodPlugins() only adds the
     * analyzers of plugins with AnalyzeCallableArgumentCapability to the methods of internal classes loaded by then,
     * and the class name suggestions represent unloaded internal classes differently.
     * So with self::SKIP, these lookups still run, unless every type in $union_type was already looked up for $kind:
     * the classes a union type refers to are the union of the classes its types refer to, and looking up the same class
     * again has no effect. (Most functions and methods use types that were already seen, e.g. inherited methods.)
     *
     * @param int $emit_only_checks self::RUN, self::SKIP or self::VERIFY (see modeForExcludedFiles())
     */
    public static function modeForClassLookup(int $emit_only_checks, string $kind, UnionType $union_type): int
    {
        if ($emit_only_checks === self::RUN) {
            return self::RUN;
        }
        $union_type_id = \spl_object_id($union_type);
        if (isset(self::$looked_up_union_types[$kind][$union_type_id])) {
            return $emit_only_checks;
        }
        // Keep references to $union_type and $type, so that their object ids are not reused for other (union) types.
        self::$looked_up_union_types[$kind][$union_type_id] = $union_type;
        $has_new_type = false;
        foreach ($union_type->getTypeSet() as $type) {
            $id = \spl_object_id($type);
            if (!isset(self::$looked_up_types[$kind][$id])) {
                self::$looked_up_types[$kind][$id] = $type;
                $has_new_type = true;
            }
        }
        return $has_new_type ? self::RUN : $emit_only_checks;
    }

    /**
     * Returns true if $union_type contains an intersection type anywhere (including in generic parameters).
     *
     * The checks for impossible type combinations (Type::checkImpossibleCombination()) only do work for
     * intersection types, and that work expands the types and hydrates the referenced classes (loading the
     * methods they inherit before Analysis::loadMethodPlugins() runs), so with self::SKIP those checks still
     * run for types that contain an intersection type.
     */
    public static function containsIntersectionType(UnionType $union_type): bool
    {
        foreach ($union_type->getTypeSet() as $type) {
            foreach ($type->getTypesRecursively() as $part) {
                if ($part instanceof IntersectionType) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Run $checks, a group of checks of $element that only emit issues, and record a violation
     * if skipping them would have changed the analysis: if they emitted an issue that would be reported,
     * changed the state of $element, or threw (which would have aborted the remaining steps).
     *
     * Issues that would be reported are passed on to the issue collector, so that the output is unchanged.
     *
     * @param Closure():mixed $checks
     */
    public static function verify(FunctionInterface $element, string $label, Closure $checks): void
    {
        $state_before = self::getElementState($element);
        $issue_collector = Phan::getIssueCollector();
        $capturing_collector = new BufferingCollector();
        Phan::setIssueCollector($capturing_collector);
        $emit_attempt_count = Issue::$emit_attempt_count;
        $start_ns = \hrtime(true);
        try {
            $checks();
        } catch (Throwable $e) {
            self::recordViolation($element, $label, 'threw ' . \get_class($e) . ': ' . $e->getMessage());
            throw $e;
        } finally {
            $stats = self::$verified_checks[$label] ?? [0, 0];
            self::$verified_checks[$label] = [$stats[0] + 1, $stats[1] + (\hrtime(true) - $start_ns)];
            // These checks are skipped without PHAN_VERIFY_SKIPPED_CHECKS=1, so CloneReplay must not see the issues they checked
            // (it would decide differently). Issues they emit that would be reported are violations.
            Issue::$emit_attempt_count = $emit_attempt_count;
            Phan::setIssueCollector($issue_collector);
            foreach ($capturing_collector->getCollectedIssues() as $issue) {
                self::recordViolation($element, $label, "emitted an issue that is reported: $issue");
                $issue_collector->collectIssue($issue);
            }
        }
        $state_after = self::getElementState($element);
        if ($state_after !== $state_before) {
            self::recordViolation($element, $label, "modified the element:\n  before: $state_before\n  after:  $state_after");
        }
    }

    /**
     * Record that the verification of $label for $element failed (PHAN_VERIFY_SKIPPED_CHECKS=1)
     */
    public static function recordViolation(FunctionInterface $element, string $label, string $details): void
    {
        $message = \sprintf("%s for %s (declared in %s) %s", $label, $element->getFQSEN(), $element->getContext()->getFile(), $details);
        self::$violations[] = $message;
        CLI::printToStderr("PHAN_VERIFY_SKIPPED_CHECKS: $message\n");
    }

    /**
     * Report the result of PHAN_VERIFY_SKIPPED_CHECKS=1 once the functions and methods were analyzed,
     * and exit with an error if any group of checks failed verification.
     *
     * @param int $element_count the number of functions and methods declared in files excluded from analysis
     */
    public static function reportVerificationResults(CodeBase $code_base, int $element_count): void
    {
        if (self::modeForExcludedFiles() !== self::VERIFY) {
            return;
        }
        $skippable_method_count = \count(self::$methods_with_skippable_ancestor_walks);
        self::verifyOverriddenMethodsOfSkippableWalks($code_base);
        CLI::printToStderr(\sprintf(
            "PHAN_VERIFY_SKIPPED_CHECKS: verified the overridden methods of %d inherited methods whose walks could be skipped (%d for (at)phan-mandatory-param, %d for (at)throws)\n",
            $skippable_method_count,
            self::$skippable_ancestor_walk_method_count[self::MANDATORY_PARAM_WALK],
            self::$skippable_ancestor_walk_method_count[self::THROWS_WALK]
        ));
        $violation_count = \count(self::$violations);
        $verified_count = 0;
        foreach (self::$verified_checks as $label => [$count, $ns]) {
            $verified_count += $count;
            CLI::printToStderr(\sprintf("PHAN_VERIFY_SKIPPED_CHECKS: %s ran %d times in %.3f s\n", $label, $count, $ns / 1e9));
        }
        CLI::printToStderr(\sprintf(
            "PHAN_VERIFY_SKIPPED_CHECKS: verified %d groups of checks of %d functions and methods declared in files excluded from analysis: %d violation(s)\n",
            $verified_count,
            $element_count,
            $violation_count
        ));
        if ($violation_count > 0) {
            exit(\EXIT_FAILURE);
        }
    }

    /**
     * Write the state of every user-defined function and method to the file named by PHAN_DUMP_METHOD_STATE_DIGEST, if set.
     *
     * This is called once the analysis phase is about to start
     * (after the checks of analyzeFunctions() and the plugins' beforeAnalyzePhase()).
     * The suppression counts are only included for elements declared in analyzed files:
     * those of elements declared in excluded files change when the checks are skipped,
     * but issues about them (including PhanUnusedSuppression) are never reported.
     */
    public static function maybeDumpStateDigest(CodeBase $code_base): void
    {
        $path = \getenv('PHAN_DUMP_METHOD_STATE_DIGEST');
        if (!\is_string($path) || $path === '') {
            return;
        }
        $lines = [];
        foreach ($code_base->getFunctionMap() as $function) {
            if (!$function->isPHPInternal()) {
                $lines[] = self::getElementState($function) . self::getSuppressionState($function);
            }
        }
        // Internal methods are added to the method set when an internal class is first used,
        // so their positions show the order in which internal classes were loaded.
        $internal_method_lines = [];
        foreach ($code_base->getMethodSet() as $method) {
            if ($method->isPHPInternal()) {
                $internal_method_lines[] = 'internal ' . $method->getFQSEN();
                continue;
            }
            $lines[] = self::getElementState($method) . self::getSuppressionState($method);
        }
        foreach ($code_base->getUserDefinedClassMap() as $class) {
            $suppression_state = self::getSuppressionState($class);
            if ($suppression_state !== '') {
                $lines[] = 'class ' . $class->getFQSEN() . $suppression_state;
            }
        }
        \array_push($lines, ...$internal_method_lines);
        if (\file_put_contents($path, \implode("\n", $lines) . "\n") === false) {
            CLI::printWarningToStderr("Failed to write PHAN_DUMP_METHOD_STATE_DIGEST to $path\n");
        }
    }

    /**
     * @param FunctionInterface|\Phan\Language\Element\Clazz $element
     */
    private static function getSuppressionState(object $element): string
    {
        $suppress_issue_list = $element->getSuppressIssueList();
        if (!$suppress_issue_list || Phan::isExcludedAnalysisFile($element->getContext()->getFile())) {
            return '';
        }
        \ksort($suppress_issue_list);
        return "\tsuppress=" . \json_encode($suppress_issue_list);
    }

    /**
     * Returns a representation of the state of $element that the analysis of other elements and files reads.
     */
    private static function getElementState(FunctionInterface $element): string
    {
        $parameters = \array_map(static function (Parameter $parameter): string {
            return $parameter->getName() . ':' . $parameter->getUnionType() . ':' . $parameter->getFlags() . ':' . $parameter->getPhanFlags();
        }, $element->getParameterList());
        $phpdoc_parameters = [];
        foreach ($element->getPHPDocParameterTypeMap() as $name => $type) {
            $phpdoc_parameters[] = "$name:$type";
        }
        return \implode("\t", [
            (string)$element->getFQSEN(),
            'type=' . $element->getUnionType(),
            'real=' . $element->getRealReturnType(),
            'phpdoc=' . ($element->getPHPDocReturnType() ?? '(none)'),
            'conditional=' . ($element->getConditionalReturnType() ?? '(none)'),
            'dependent=' . ($element->hasDependentReturnType() ? '1' : '0'),
            'flags=' . ($element instanceof AddressableElement ? $element->getFlags() : ''),
            'phan_flags=' . $element->getPhanFlags(),
            'params=' . \implode(',', $parameters),
            'phpdoc_params=' . \implode(',', $phpdoc_parameters),
            'throws=' . $element->getOwnThrowsUnionType(),
            'full_throws=' . $element->getFullThrowsUnionType(),
        ]);
    }
}
