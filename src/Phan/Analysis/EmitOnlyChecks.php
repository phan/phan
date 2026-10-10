<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Closure;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\Language\Type;
use Phan\Language\Type\IntersectionType;
use Phan\Language\UnionType;
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
 * a type is seen for the first time, because they load internal classes (see shouldLookUpClassesOfType()).
 *
 * Environment variables (read once):
 *
 * - `PHAN_ANALYZE_EXCLUDED_METHODS=1` always runs the checks (the previous behavior).
 * - `PHAN_VERIFY_SKIPPED_CHECKS=1` runs the checks for elements declared in excluded files,
 *   and verifies that each group of checks neither emitted an issue that would be reported,
 *   nor modified the element, nor threw. Exits with an error after the method analysis phase
 *   if any group of checks failed this verification.
 * - `PHAN_DUMP_METHOD_STATE_DIGEST=<path>` writes the state of every user-defined function and method
 *   to `<path>` once the analysis phase is about to start, to compare runs with and without
 *   `PHAN_ANALYZE_EXCLUDED_METHODS=1`.
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

    /** @var array<string,array<int,Type>> the types whose classes were looked up with self::SKIP, by kind of lookup and object id */
    private static $looked_up_types = [];

    /** @var array<string,array<int,UnionType>> the union types whose types were all looked up with self::SKIP, by kind of lookup and object id */
    private static $looked_up_union_types = [];

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
     * Returns whether to look up the classes used in $union_type (for the lookup $kind).
     *
     * Some checks look up the classes used in a parameter or return type, and suggest similar class names for
     * undeclared classes. This loads internal classes (and their ancestors when the class is hydrated), and the rest
     * of the analysis depends on which internal classes are loaded: e.g. Analysis::loadMethodPlugins() only adds the
     * analyzers of plugins with AnalyzeCallableArgumentCapability to the methods of internal classes loaded by then,
     * and the class name suggestions represent unloaded internal classes differently.
     * So with self::SKIP, these lookups still run, unless every type in $union_type was already looked up for $kind:
     * the classes a union type refers to are the union of the classes its types refer to, and looking up the same class
     * again has no effect. (Most functions and methods use types that were already seen, e.g. inherited methods.)
     */
    public static function shouldLookUpClassesOfType(int $emit_only_checks, string $kind, UnionType $union_type): bool
    {
        if ($emit_only_checks !== self::SKIP) {
            return true;
        }
        $union_type_id = \spl_object_id($union_type);
        if (isset(self::$looked_up_union_types[$kind][$union_type_id])) {
            return false;
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
        return $has_new_type;
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
        $start_ns = \hrtime(true);
        try {
            $checks();
        } catch (Throwable $e) {
            self::recordViolation($element, $label, 'threw ' . \get_class($e) . ': ' . $e->getMessage());
            throw $e;
        } finally {
            $stats = self::$verified_checks[$label] ?? [0, 0];
            self::$verified_checks[$label] = [$stats[0] + 1, $stats[1] + (\hrtime(true) - $start_ns)];
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

    private static function recordViolation(FunctionInterface $element, string $label, string $details): void
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
    public static function reportVerificationResults(int $element_count): void
    {
        if (self::modeForExcludedFiles() !== self::VERIFY) {
            return;
        }
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
