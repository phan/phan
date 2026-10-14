<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;

/**
 * Runs the declaration analysis of one user-defined function or method for Analysis::analyzeFunctions():
 * scope initialization (or the replay of the declaration analysis of its clone source, see CloneReplay),
 * the checks of its signature and of the methods it overrides, attributes, return type inference, (at)throws
 * and the analyzeFunction/analyzeMethod plugins.
 *
 * Analysis::analyzeFunctions() creates one instance per call and calls analyze() for each function and method.
 * With lazy_inherited_methods, the copies of inherited methods created later get the same analysis
 * (analyzeNested() during the method loop, analyzeMethodDeclaration() after it, see LazyInheritedMethods).
 *
 * @internal
 */
final class MethodDeclarationAnalyzer
{
    /** @var ?self the analyzer of the method loop of Analysis::analyzeFunctions() while it runs */
    public static $current = null;

    /** @var CodeBase the code base being analyzed */
    private $code_base;

    /** @var ConfigPluginSet the plugin set */
    private $plugin_set;

    /** @var bool whether any plugin analyzes functions or methods */
    private $has_function_or_method_plugins;

    /** @var ?array<string,mixed> if non-null, limit analysis to functions and methods declared in this array */
    private $file_filter;

    /** @var int the EmitOnlyChecks mode of the checks of functions and methods declared in files excluded from analysis */
    private $excluded_file_checks;

    /** @var array<string,bool> cache of Phan::isExcludedAnalysisFile() */
    public $is_excluded_file = [];

    /**
     * @var array<int,int> the number of user-defined functions and methods for which the emit-only checks
     * were run/skipped or verified (keys EmitOnlyChecks::RUN, SKIP, VERIFY)
     */
    public $checks_count = [EmitOnlyChecks::RUN => 0, EmitOnlyChecks::SKIP => 0, EmitOnlyChecks::VERIFY => 0];

    /**
     * @param ?array<string,mixed> $file_filter if non-null, limit analysis to functions and methods declared in this array
     * @param int $excluded_file_checks EmitOnlyChecks::modeForExcludedFiles()
     */
    public function __construct(
        CodeBase $code_base,
        ConfigPluginSet $plugin_set,
        bool $has_function_or_method_plugins,
        ?array $file_filter,
        int $excluded_file_checks
    ) {
        $this->code_base = $code_base;
        $this->plugin_set = $plugin_set;
        $this->has_function_or_method_plugins = $has_function_or_method_plugins;
        $this->file_filter = $file_filter;
        $this->excluded_file_checks = $excluded_file_checks;
    }

    /**
     * Analyze the declaration of a function or method.
     *
     * @param ?CloneReplaySnapshot $clone_replay_snapshot for an inherited method,
     * the results of the declaration analysis of the method it was cloned from, to replay (see CloneReplay)
     * @param bool $run_plugins false to skip the analyzeFunction/analyzeMethod plugins
     * (for lazily created copies of inherited methods first needed after finalizeProcess() started)
     */
    public function analyze(Func|Method $function_or_method, ?CloneReplaySnapshot $clone_replay_snapshot = null, bool $run_plugins = true): void
    {
        $code_base = $this->code_base;
        if ($function_or_method->isPHPInternal()) {
            return;
        }
        if ($clone_replay_snapshot !== null && $function_or_method instanceof Method) {
            // An inherited method whose declaration analysis (the parameters part of ensureScopeInitialized() and
            // ParameterTypesAnalyzer::analyzeParameterTypesOfDeclaration()) would compute the same as that of the method
            // it was cloned from, and check no issue: make the same changes to it instead (see CloneReplay).
            $function_or_method->replayDeclarationAnalysis($code_base, $clone_replay_snapshot);
        } else {
            // Phan always has to call this, to add default values to types of parameters.
            $function_or_method->ensureScopeInitialized($code_base);
        }

        $file = $function_or_method->getContext()->getFile();
        // If there is an array limiting the set of files, skip this file if it's not in the list.
        $file_filter = $this->file_filter;
        if (\is_array($file_filter) && !isset($file_filter[$file])) {
            return;
        }
        // The steps below that modify $function_or_method (or other state read by the analysis) always run.
        // The checks that only emit issues about $function_or_method are skipped
        // if it is declared in a file excluded from analysis (Issue::emitInstance() discards those issues).
        $checks = EmitOnlyChecks::RUN;
        $excluded_file_checks = $this->excluded_file_checks;
        if ($excluded_file_checks !== EmitOnlyChecks::RUN && ($this->is_excluded_file[$file] ??= Phan::isExcludedAnalysisFile($file))) {
            $checks = $excluded_file_checks;
        }
        $this->checks_count[$checks]++;

        // This emits issues at the location of the original definition, which may be in a file that is analyzed.
        DuplicateFunctionAnalyzer::analyzeDuplicateFunction(
            $code_base,
            $function_or_method
        );

        // This is the most time consuming step.
        // Can probably apply this to other functions, but this was the slowest.
        // (Its first part is replayed with the rest of the declaration analysis, see above)
        if ($clone_replay_snapshot === null) {
            $is_declaration_analyzed = ParameterTypesAnalyzer::analyzeParameterTypesOfDeclaration(
                $code_base,
                $function_or_method,
                $checks
            );
            if (CloneReplay::$has_pending_method && $function_or_method instanceof Method) {
                // Record the results of the declaration analysis of a method that other methods were cloned from
                // (or verify those of a clone that could be replayed, for PHAN_VERIFY_SKIPPED_CHECKS=1)
                CloneReplay::endDeclarationAnalysis($code_base, $function_or_method, $is_declaration_analyzed);
            }
        } else {
            $is_declaration_analyzed = true;
        }
        if ($is_declaration_analyzed) {
            ParameterTypesAnalyzer::analyzeParameterTypesOfOverrides(
                $code_base,
                $function_or_method,
                $checks
            );
        }

        // This always runs: it adds references to attribute classes, may create their default constructor,
        // and analyzes the arguments of the attributes.
        AttributeAnalyzer::analyzeAttributesOfFunctionInterface(
            $code_base,
            $function_or_method
        );

        // Infer more accurate return types
        // For daemon mode/the language server, we also call this whenever we use the return type of a function/method.
        $function_or_method->analyzeReturnTypes($code_base, $checks);

        // This always runs: it inherits (at)throws types, and its checks look up the classes used in them.
        ThrowsTypesAnalyzer::analyzeThrowsTypes(
            $code_base,
            $function_or_method
        );
        // Let any plugins analyze the methods or functions
        // XXX: Add a way to run plugins on all functions/methods, this was limited for speed.
        // Assumes that the given plugins will emit an issue in the same file as the function/method,
        // which isn't necessarily the case.
        // (Plugins always run, even for elements declared in excluded files: they may record state.)
        // 0.06
        if ($this->has_function_or_method_plugins && $run_plugins) {
            if ($function_or_method instanceof Func) {
                $this->plugin_set->analyzeFunction(
                    $code_base,
                    $function_or_method
                );
            } else {
                $this->plugin_set->analyzeMethod(
                    $code_base,
                    $function_or_method
                );
            }
        }
    }

    /**
     * Returns an analyzer with the inputs that Analysis::analyzeFunctions() uses for a full analysis,
     * for the copies of inherited methods created lazily after it (see LazyInheritedMethods).
     */
    public static function forLateUse(CodeBase $code_base): self
    {
        $plugin_set = ConfigPluginSet::instance();
        return new self(
            $code_base,
            $plugin_set,
            $plugin_set->hasAnalyzeFunctionPlugins() || $plugin_set->hasAnalyzeMethodPlugins(),
            null,
            EmitOnlyChecks::modeForExcludedFiles()
        );
    }

    /**
     * Run the declaration analysis of $method, a copy of an inherited method that LazyInheritedMethods created after
     * the method loop of Analysis::analyzeFunctions() (during the analysis phase, or in plugins), which an eager
     * import would have created and analyzed in that loop.
     *
     * The records of CloneReplay are gone by then, so this runs the full declaration analysis, which computes
     * the same state as a replay. The plugins' analyzeMethod() is skipped once finalizeProcess() has started.
     */
    public static function analyzeMethodDeclaration(CodeBase $code_base, Method $method): void
    {
        $lazy = $code_base->getLazyInherited();
        if ($lazy === null || $method->isPHPInternal()) {
            return;
        }
        $analyzer = $lazy->analyzer ??= self::forLateUse($code_base);
        $start_ns = \hrtime(true);
        $loaded_class_count = $code_base->getLoadedClassCount();
        $counts_before = EmitOnlyChecks::getSideEffectCounts($code_base);
        $side_effect_check = $lazy->verify ? $lazy->beginSideEffectCheck() : null;
        $saved_walks = [EmitOnlyChecks::$method_with_skippable_mandatory_param_walk, EmitOnlyChecks::$method_with_skippable_throws_walk];
        EmitOnlyChecks::beginAnalyzingMethod($code_base, $method);
        $run_plugins = !$lazy->finalize_started;
        try {
            $analyzer->analyze($method, null, $run_plugins);
        } finally {
            [EmitOnlyChecks::$method_with_skippable_mandatory_param_walk, EmitOnlyChecks::$method_with_skippable_throws_walk] = $saved_walks;
            if ($side_effect_check !== null) {
                $lazy->endSideEffectCheck($method, $side_effect_check, 'late pipeline');
            }
            $lazy->addNestedEffects(self::subtractCounts(EmitOnlyChecks::getSideEffectCounts($code_base), $counts_before));
            $loaded_class_delta = $code_base->getLoadedClassCount() - $loaded_class_count;
            if ($loaded_class_delta > 0) {
                $lazy->countLateInternalLoads($method, $loaded_class_delta);
            }
            $lazy->addToCounter('late_pipelines');
            if (!$run_plugins) {
                $lazy->addToCounter('post_finalize_materializations');
            }
            $lazy->addToCounter('late_pipeline_s', (\hrtime(true) - $start_ns) / 1e9);
        }
    }

    /**
     * Run the declaration analysis of $method, a copy of an inherited method that LazyInheritedMethods created
     * during the method loop, while another method is being processed, when an eager import would have created it
     * earlier and the loop would already have processed it (it comes before the method being processed in eager order).
     *
     * This saves and restores the state of the method being processed, and excludes the side effects of this
     * analysis from the checks of that method (Issue::$emit_attempt_count, EmitOnlyChecks::getSideEffectCounts()),
     * so that they see what they would have seen if $method had been processed before.
     */
    public static function analyzeNested(CodeBase $code_base, Method $method): void
    {
        $lazy = $code_base->getLazyInherited();
        if ($lazy === null) {
            return;
        }
        if ($method->isPHPInternal()) {
            $lazy->processCopy($method);
            return;
        }
        $saved_walks = [EmitOnlyChecks::$method_with_skippable_mandatory_param_walk, EmitOnlyChecks::$method_with_skippable_throws_walk];
        $saved_clone_replay = CloneReplay::suspendPending();
        $emit_attempt_count = Issue::$emit_attempt_count;
        $counts_before = EmitOnlyChecks::getSideEffectCounts($code_base);
        $previous_ordinal = $lazy->current_ordinal;
        // @phan-suppress-next-line PhanAccessMethodInternal
        $lazy->current_ordinal = $method->getEagerOrdinal();
        $side_effect_check = $lazy->verify ? $lazy->beginSideEffectCheck() : null;
        try {
            $lazy->processCopy($method);
        } finally {
            $lazy->current_ordinal = $previous_ordinal;
            if ($side_effect_check !== null) {
                $lazy->endSideEffectCheck($method, $side_effect_check, 'nested pipeline');
            }
            $lazy->addNestedEffects(self::subtractCounts(EmitOnlyChecks::getSideEffectCounts($code_base), $counts_before));
            // The issues that this checked would have been checked before the method being processed
            Issue::$emit_attempt_count = $emit_attempt_count;
            CloneReplay::resumePending($saved_clone_replay);
            [EmitOnlyChecks::$method_with_skippable_mandatory_param_walk, EmitOnlyChecks::$method_with_skippable_throws_walk] = $saved_walks;
            $lazy->addToCounter('nested_processed');
        }
    }

    /**
     * @param array{0:int,1:int,2:int,3:int} $after
     * @param array{0:int,1:int,2:int,3:int} $before
     * @return array{0:int,1:int,2:int,3:int}
     */
    private static function subtractCounts(array $after, array $before): array
    {
        return [$after[0] - $before[0], $after[1] - $before[1], $after[2] - $before[2], $after[3] - $before[3]];
    }
}
