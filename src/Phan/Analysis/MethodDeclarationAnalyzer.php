<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
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
 *
 * @internal
 */
final class MethodDeclarationAnalyzer
{
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
     */
    public function analyze(Func|Method $function_or_method, ?CloneReplaySnapshot $clone_replay_snapshot = null): void
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
        if ($this->has_function_or_method_plugins) {
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
}
