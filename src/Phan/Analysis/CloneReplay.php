<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CLI;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\UnionType;
use Phan\Library\PhaseTimer;
use Throwable;

/**
 * Replays the declaration analysis of inherited methods in Analysis::analyzeFunctions().
 *
 * Clazz::addMethod() creates the methods that a class inherits as clones of the methods of its ancestors,
 * and records the method each one was cloned from (its source) in the clone. Analysis::analyzeFunctions()
 * processes each source before its clones. The "declaration analysis" of a method is the part of that processing
 * which only depends on the method's own declaration: the parameters part of ensureScopeInitialized()
 * (phpdoc and default types of the parameters, the phpdoc parameter type map, (at)phan-mandatory-param offsets,
 * whether it has template types) and ParameterTypesAnalyzer::analyzeParameterTypesOfDeclaration()
 * (narrowing the parameter types to the phpdoc types, and the checks of the parameters).
 *
 * For a source, this records everything that its declaration analysis reads from the method before it
 * (Method::getCloneReplayFingerprint()) and everything that it writes after it (Method::getCloneReplaySnapshot()),
 * if it neither checked nor emitted an issue (Issue::$emit_attempt_count), and found no template type.
 * When a clone of it has the same fingerprint, its declaration analysis would compute the same results from the same
 * inputs and check no issue either, so Analysis::analyzeFunctions() replays the snapshot instead of running it
 * (Method::replayDeclarationAnalysis()), and runs all other steps (signature checks against the methods it overrides,
 * attributes, return types, (at)throws, plugins) as before. The declaration analysis also reads state outside of the method:
 * - The configuration, and the existence of classes (fixed once parsing is done).
 * - Classes are hydrated and internal classes are loaded the first time they are needed. The source's identical
 *   declaration analysis already did that, so the clone's would do nothing more.
 * - EmitOnlyChecks::modeForClassLookup(): its result only depends on which types were already looked up,
 *   and the source's declaration analysis looked up the same types.
 * - The walk over the methods that the method overrides for an inherited (at)phan-mandatory-param:
 *   this only replays clones for which that walk is skipped (see EmitOnlyChecks::beginAnalyzingMethod()).
 *
 * A replayed clone that other methods were cloned from gets the record of its source, since it has the same state.
 * (The source is only used to find a record: the fingerprint alone decides whether replaying it is identical.)
 *
 * With PHAN_VERIFY_SKIPPED_CHECKS=1, the declaration analysis of the clones that could be replayed runs instead,
 * and this verifies that it checked or emitted no issue, had no side effects other than initializing the scope,
 * and produced the state that the replay would have produced. Then it sets the state of the clone to the objects
 * of the snapshot (instead of the equal objects that the declaration analysis created), so that the following
 * decisions (which compare objects by identity) are the same as without PHAN_VERIFY_SKIPPED_CHECKS=1.
 * PHAN_DISABLE_CLONE_REPLAY=1 disables this.
 *
 * This is not part of Phan's plugin API.
 */
final class CloneReplay
{
    /** @var bool true while Analysis::analyzeFunctions() is analyzing methods with clone replay enabled */
    private static $enabled = false;

    /** @var bool true to verify instead of replaying (PHAN_VERIFY_SKIPPED_CHECKS=1) */
    private static $verify = false;

    /** @var bool true to collect statistics for --dump-phase-timings */
    private static $collect_stats = false;

    /**
     * @var bool true if endDeclarationAnalysis() has to be called for the method that is being processed
     * (it is recording a source, or verifying a clone)
     */
    public static $has_pending_method = false;

    /** @var ?Method the method that is being recorded or verified */
    private static $pending_method = null;

    /** @var ?list<mixed> the fingerprint of the source that is being recorded */
    private static $pending_fingerprint = null;

    /** @var ?array{0:list<mixed>,1:CloneReplaySnapshot} the record that the clone that is being verified would replay */
    private static $pending_record = null;

    /** @var int Issue::$emit_attempt_count before the declaration analysis of the pending method */
    private static $pending_emit_attempt_count = 0;

    /** @var array{0:int,1:int,2:int,3:int} EmitOnlyChecks::getSideEffectCounts() before the declaration analysis of the clone that is being verified */
    private static $pending_side_effect_counts = [0, 0, 0, 0];

    /** @var int|float hrtime() before the declaration analysis of the clone that is being verified */
    private static $pending_start_ns = 0;

    /** @var array<int,true> the object ids of the sources of the methods in the method set */
    private static $source_ids = [];

    /**
     * @var array<int,array{0:list<mixed>,1:CloneReplaySnapshot}|string> maps the object id of each processed source
     * to its record (fingerprint and snapshot), or to the reason why it has none
     */
    private static $records = [];

    /** @var int the number of clones that were replayed (or verified) */
    private static $replayed_count = 0;

    /** @var array<string,int> other statistics for --dump-phase-timings */
    private static $counts = [];

    /**
     * @var array<int,int> the number of clones that were replayed (odd keys) and not replayed (even keys),
     * by twice the object id of the FQSEN of the class of their source (to classify them by origin at the end)
     */
    private static $counts_by_source_class = [];

    /** @var array<int,FullyQualifiedClassName> the FQSENs of the classes in self::$counts_by_source_class, by object id */
    private static $source_class_fqsens = [];

    /** @var int|float nanoseconds spent in the declaration analysis of verified clones */
    private static $verified_ns = 0;

    /**
     * Called by Analysis::analyzeFunctions() before it processes the methods.
     *
     * @param bool $is_enabled false if PHAN_DISABLE_CLONE_REPLAY=1 or if only some files are analyzed (daemon mode)
     * @param bool $verify true for PHAN_VERIFY_SKIPPED_CHECKS=1
     * @param bool $collect_stats true for --dump-phase-timings
     * @return bool whether clone replay is enabled
     */
    public static function beginAnalyzingMethods(CodeBase $code_base, bool $is_enabled, bool $verify, bool $collect_stats): bool
    {
        self::reset();
        self::$enabled = $is_enabled;
        self::$verify = $verify;
        self::$collect_stats = $collect_stats;
        if (!$is_enabled) {
            return false;
        }
        foreach ($code_base->getMethodSet() as $method) {
            $source = $method->getCloneSource();
            if ($source !== null) {
                self::$source_ids[\spl_object_id($source)] = true;
            }
        }
        return true;
    }

    /**
     * Called by Analysis::analyzeFunctions() after it processed the methods.
     */
    public static function endAnalyzingMethods(CodeBase $code_base): void
    {
        if (self::$enabled && self::$collect_stats) {
            self::recordStats($code_base);
        }
        if (self::$enabled && self::$verify) {
            CLI::printToStderr(\sprintf(
                "PHAN_VERIFY_SKIPPED_CHECKS: verified that replaying the declaration analysis of %d inherited methods would be identical (%.3f s)\n",
                self::$replayed_count,
                self::$verified_ns / 1e9
            ));
        }
        self::reset();
    }

    private static function reset(): void
    {
        self::$enabled = false;
        self::endAnalyzingMethod();
        self::$source_ids = [];
        self::$records = [];
        self::$replayed_count = 0;
        self::$counts = [];
        self::$counts_by_source_class = [];
        self::$source_class_fqsens = [];
        self::$verified_ns = 0;
    }

    /**
     * Called by Analysis::analyzeFunctions() before it processes the user-defined method $method
     * (after EmitOnlyChecks::beginAnalyzingMethod()).
     *
     * @return ?CloneReplaySnapshot the snapshot to replay instead of the declaration analysis of $method,
     * if it is a clone that can be replayed
     */
    public static function beginAnalyzingMethod(CodeBase $code_base, Method $method): ?CloneReplaySnapshot
    {
        $id = \spl_object_id($method);
        $source = $method->getCloneSource();
        if ($source !== null) {
            $record = self::findRecordToReplay($code_base, $method, $source);
            if (\is_array($record)) {
                if (isset(self::$source_ids[$id])) {
                    // A replayed clone has the same state as its source: its own clones can replay the same record.
                    self::$records[$id] = $record;
                }
                self::$replayed_count++;
                if (self::$collect_stats) {
                    self::countClone($source, true);
                }
                if (self::$verify) {
                    self::$has_pending_method = true;
                    self::$pending_method = $method;
                    self::$pending_record = $record;
                    self::$pending_emit_attempt_count = Issue::$emit_attempt_count;
                    self::$pending_side_effect_counts = EmitOnlyChecks::getSideEffectCounts($code_base);
                    self::$pending_start_ns = \hrtime(true);
                    return null;
                }
                return $record[1];
            }
            if (self::$collect_stats) {
                self::increment("ineligible_$record");
                if ($source->isFromPHPDoc()) {
                    self::increment('ineligible_phpdoc');
                } elseif ($method->getComment() !== $source->getComment()) {
                    self::increment('ineligible_template_mapped');
                } else {
                    self::countClone($source, false);
                }
            }
        } elseif (self::$collect_stats && $method->getRealDefiningFQSEN() !== $method->getFQSEN()) {
            // e.g. methods imported from traits or mixins
            self::increment('inherited_without_source');
        }
        if (isset(self::$source_ids[$id])) {
            self::startRecording($method, $id);
        }
        return null;
    }

    /**
     * @return array{0:list<mixed>,1:CloneReplaySnapshot}|string the record to replay for $method, or the reason why it can't be replayed
     */
    private static function findRecordToReplay(CodeBase $code_base, Method $method, Method $source): array|string
    {
        if ($method->isScopeInitialized()) {
            return 'initialized';
        }
        if ($method->isNewConstructor()) {
            return 'construct';
        }
        if ($method->getFQSEN()->isAlternate()) {
            return 'alternate';
        }
        if ($code_base->sawMandatoryParamAnnotation() && EmitOnlyChecks::$method_with_skippable_mandatory_param_walk !== $method) {
            // The walk for an inherited (at)phan-mandatory-param is the only input of the declaration analysis
            // that differs between a clone and its source.
            return 'mandatory_param_walk';
        }
        $record = self::$records[\spl_object_id($source)] ?? 'not_recorded';
        if (!\is_array($record)) {
            return "source_$record";
        }
        if (!$method->matchesCloneReplayFingerprint($record[0])) {
            return self::$collect_stats ? 'fingerprint_' . self::describeFingerprintDifference($method->getCloneReplayFingerprint(), $record[0]) : 'fingerprint';
        }
        return $record;
    }

    private static function startRecording(Method $method, int $id): void
    {
        if ($method->isScopeInitialized()) {
            // The scope was initialized before Analysis::analyzeFunctions() processed it (e.g. as an overridden method)
            self::$records[$id] = 'initialized';
            return;
        }
        if ($method->getComment() === null) {
            self::$records[$id] = 'no_comment';
            return;
        }
        if ($method->isNewConstructor()) {
            self::$records[$id] = 'construct';
            return;
        }
        if ($method->getFQSEN()->isAlternate()) {
            self::$records[$id] = 'alternate';
            return;
        }
        self::$has_pending_method = true;
        self::$pending_method = $method;
        self::$pending_fingerprint = $method->getCloneReplayFingerprint();
        self::$pending_emit_attempt_count = Issue::$emit_attempt_count;
    }

    /**
     * Called by Analysis::analyzeFunctions() after the declaration analysis of $method if self::$has_pending_method is true:
     * records the result for a source, or verifies the result for a clone (PHAN_VERIFY_SKIPPED_CHECKS=1).
     *
     * @param bool $completed false if the declaration analysis was aborted
     */
    public static function endDeclarationAnalysis(CodeBase $code_base, Method $method, bool $completed): void
    {
        if ($method !== self::$pending_method) {
            self::endAnalyzingMethod();
            return;
        }
        $had_issue_attempts = Issue::$emit_attempt_count !== self::$pending_emit_attempt_count;
        $record = self::$pending_record;
        $fingerprint = self::$pending_fingerprint;
        self::endAnalyzingMethod();
        if ($record !== null) {
            self::$verified_ns += \hrtime(true) - self::$pending_start_ns;
            self::verifyReplay($code_base, $method, $record, $completed, $had_issue_attempts);
            // Continue with the same objects as the replay (the declaration analysis created equal ones),
            // so that the decisions for the following clones are the same as without PHAN_VERIFY_SKIPPED_CHECKS=1.
            $method->applyCloneReplaySnapshot($record[1]);
            return;
        }
        if ($fingerprint === null) {
            return;
        }
        if (!$completed) {
            $reason = 'aborted';
        } elseif ($had_issue_attempts) {
            $reason = 'issues';
        } elseif ($method->hasTemplateType()) {
            $reason = 'template';
        } else {
            self::$records[\spl_object_id($method)] = [$fingerprint, $method->getCloneReplaySnapshot($code_base)];
            if (self::$collect_stats) {
                self::increment('recorded');
            }
            return;
        }
        self::$records[\spl_object_id($method)] = $reason;
        if (self::$collect_stats) {
            self::increment("not_recorded_$reason");
        }
    }

    /**
     * Called by Analysis::analyzeFunctions() after processing a method for which self::$has_pending_method is still true
     * (e.g. if processing it threw)
     */
    public static function endAnalyzingMethod(): void
    {
        self::$has_pending_method = false;
        self::$pending_method = null;
        self::$pending_fingerprint = null;
        self::$pending_record = null;
    }

    /**
     * Verify that replaying $record for the clone $method instead of its declaration analysis would have been identical.
     *
     * @param array{0:list<mixed>,1:CloneReplaySnapshot} $record
     */
    private static function verifyReplay(CodeBase $code_base, Method $method, array $record, bool $completed, bool $had_issue_attempts): void
    {
        $label = 'replayable declaration analysis';
        if (!$completed) {
            EmitOnlyChecks::recordViolation($method, $label, 'was aborted');
        }
        if ($had_issue_attempts) {
            EmitOnlyChecks::recordViolation($method, $label, 'checked or emitted an issue');
        }
        $side_effect_counts = EmitOnlyChecks::getSideEffectCounts($code_base);
        $expected_side_effect_counts = self::$pending_side_effect_counts;
        // The declaration analysis initializes the scope of $method (as does the replay)
        $expected_side_effect_counts[1]++;
        if ($side_effect_counts !== $expected_side_effect_counts) {
            EmitOnlyChecks::recordViolation($method, $label, 'had side effects (hydrations, scope initializations, methods, classes): ' . \json_encode($expected_side_effect_counts) . ' -> ' . \json_encode($side_effect_counts));
        }
        $expected = $record[1];
        $actual = $method->getCloneReplaySnapshot($code_base);
        if (!self::isSameSnapshot($expected, $actual)) {
            EmitOnlyChecks::recordViolation($method, $label, "produced a different state:\n  replay: " . self::describeSnapshot($expected) . "\n  actual: " . self::describeSnapshot($actual));
        }
        if ($method->hasTemplateType() !== $method->hasTemplateTypeAfterCloneReplay($expected)) {
            EmitOnlyChecks::recordViolation($method, $label, 'found template types where the replay would not, or the reverse');
        }
    }

    private static function isSameSnapshot(CloneReplaySnapshot $expected, CloneReplaySnapshot $actual): bool
    {
        if ($expected->last_mandatory_phpdoc_param_offset !== $actual->last_mandatory_phpdoc_param_offset ||
            $expected->adds_this_variable !== $actual->adds_this_variable ||
            \array_keys($expected->phpdoc_parameter_type_map) !== \array_keys($actual->phpdoc_parameter_type_map) ||
            \count($expected->parameter_states) !== \count($actual->parameter_states)) {
            return false;
        }
        foreach ($expected->phpdoc_parameter_type_map as $name => $type) {
            if (!self::isSameUnionType($type, $actual->phpdoc_parameter_type_map[$name])) {
                return false;
            }
        }
        foreach ($expected->parameter_states as $i => [$type, $phan_flags, $default_type, $default_literal_type, $default_future_type]) {
            [$actual_type, $actual_phan_flags, $actual_default_type, $actual_default_literal_type, $actual_default_future_type] = $actual->parameter_states[$i];
            if ($phan_flags !== $actual_phan_flags || $default_future_type !== $actual_default_future_type || !self::isSameUnionType($type, $actual_type)) {
                return false;
            }
            if (!self::isSameOptionalUnionType($default_type, $actual_default_type) || !self::isSameOptionalUnionType($default_literal_type, $actual_default_literal_type)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if $a and $b have the same types and real types, in the same order (Type instances are unique)
     */
    private static function isSameUnionType(UnionType $a, UnionType $b): bool
    {
        return $a === $b || ($a->getTypeSet() === $b->getTypeSet() && $a->getRealTypeSet() === $b->getRealTypeSet());
    }

    private static function isSameOptionalUnionType(?UnionType $a, ?UnionType $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        return self::isSameUnionType($a, $b);
    }

    private static function describeSnapshot(CloneReplaySnapshot $snapshot): string
    {
        $describe_type = static function (?UnionType $type): string {
            if ($type === null) {
                return '(none)';
            }
            return $type . ' (real ' . $type->getRealUnionType() . ')';
        };
        $parts = [];
        foreach ($snapshot->parameter_states as $i => [$type, $phan_flags, $default_type, $default_literal_type, $default_future_type]) {
            $parts[] = "#$i " . $describe_type($type) . " phan_flags=$phan_flags default=" . $describe_type($default_type) . ' literal=' . $describe_type($default_literal_type) . ' future=' . ($default_future_type ? 'yes' : 'no');
        }
        foreach ($snapshot->phpdoc_parameter_type_map as $name => $type) {
            $parts[] = "phpdoc \$$name " . $describe_type($type);
        }
        $parts[] = 'mandatory=' . \var_export($snapshot->last_mandatory_phpdoc_param_offset, true);
        $parts[] = 'this=' . ($snapshot->adds_this_variable ? 'yes' : 'no');
        return \implode('; ', $parts);
    }

    /**
     * @param list<mixed> $fingerprint
     * @param list<mixed> $source_fingerprint
     * @return string the first part of $fingerprint that differs from $source_fingerprint (for statistics)
     */
    private static function describeFingerprintDifference(array $fingerprint, array $source_fingerprint): string
    {
        static $method_parts = ['comment', 'context', 'name', 'flags', 'phan_flags', 'mandatory_offset', 'conditional_return_type', 'real_parameters'];
        static $parameter_parts = ['class', 'name', 'flags', 'type', 'phan_flags', 'default', 'default_type', 'default_literal_type', 'default_future_type'];
        if (\count($fingerprint) !== \count($source_fingerprint)) {
            return 'parameter_count';
        }
        foreach ($fingerprint as $i => $part) {
            if ($part === $source_fingerprint[$i]) {
                continue;
            }
            if (isset($method_parts[$i])) {
                return $method_parts[$i];
            }
            if (\is_array($part) && \is_array($source_fingerprint[$i])) {
                foreach ($part as $j => $parameter_part) {
                    if ($parameter_part !== ($source_fingerprint[$i][$j] ?? null)) {
                        return 'parameter_' . ($parameter_parts[$j] ?? 'unknown');
                    }
                }
            }
            return 'unknown';
        }
        return 'unknown';
    }

    private static function increment(string $key): void
    {
        self::$counts[$key] = (self::$counts[$key] ?? 0) + 1;
    }

    /**
     * Count a clone of $source that was replayed or not, by the class of $source (classified once all methods were processed)
     */
    private static function countClone(Method $source, bool $is_eligible): void
    {
        $class_fqsen = $source->getClassFQSEN();
        $key = \spl_object_id($class_fqsen) * 2 + ($is_eligible ? 1 : 0);
        if (isset(self::$counts_by_source_class[$key])) {
            self::$counts_by_source_class[$key]++;
        } else {
            self::$counts_by_source_class[$key] = 1;
            self::$source_class_fqsens[$key >> 1] = $class_fqsen;
        }
    }

    private static function recordStats(CodeBase $code_base): void
    {
        $counts = self::$counts;
        foreach (self::$counts_by_source_class as $key => $count) {
            try {
                $class = $code_base->getClassByFQSENWithoutHydrating(self::$source_class_fqsens[$key >> 1]);
                $origin = $class->isInterface() ? 'interface' : ($class->isTrait() ? 'trait' : 'parent');
            } catch (Throwable) {
                $origin = 'unknown';
            }
            $origin_key = (($key & 1) ? 'eligible_' : 'ineligible_') . $origin;
            $counts[$origin_key] = ($counts[$origin_key] ?? 0) + $count;
        }
        $counts['eligible'] = self::$replayed_count;
        \ksort($counts);
        PhaseTimer::note('clone_replay_sources', \count(self::$source_ids));
        foreach ($counts as $key => $count) {
            PhaseTimer::note("clone_replay_$key", $count);
        }
        if (self::$verify) {
            PhaseTimer::note('clone_replay_verified_s', \round(self::$verified_ns / 1e9, 6));
        }
    }
}
