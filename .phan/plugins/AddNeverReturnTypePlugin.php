<?php

declare(strict_types=1);

use ast\Node;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\CodeBase;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Type\NeverType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;

/**
 * This plugin checks if a function or method will not return (and has no overrides).
 * If the function doesn't have a return type of never.
 * then this plugin will emit an issue.
 *
 * It hooks into two events:
 *
 * - analyzeMethod
 *   Once all methods are parsed, this method will be called
 *   on every method in the code base
 *
 * - analyzeFunction
 *   Once all functions have been parsed, this method will
 *   be called on every function in the code base.
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class NeverReturnPlugin extends PluginV3 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability
{

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ): void {
        $stmts_list = self::getStatementListToAnalyze($method);
        if ($stmts_list === null) {
            // check for abstract methods, generators, etc.
            return;
        }
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            // Check if this was inherited by a descendant class.
            return;
        }

        if ($method->getUnionType()->hasType(NeverType::instance(false))) {
            return;
        }
        if ($method->isOverriddenByAnother()) {
            return;
        }

        // This modifies the nodes in place, check this last
        if (!BlockExitStatusChecker::willUnconditionallyNeverReturn($stmts_list)) {
            return;
        }
        self::emitIssue(
            $code_base,
            $method->getContext(),
            'PhanPluginNeverReturnMethod',
            "Method {METHOD} never returns and has a return type of {TYPE}, but phpdoc type {TYPE} could be used instead",
            [$method->getRepresentationForIssue(), $method->getUnionType(), 'never']
        );
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function or closure being analyzed
     *
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ): void {
        $stmts_list = self::getStatementListToAnalyze($function);
        if ($stmts_list === null) {
            // check for abstract methods, generators, etc.
            return;
        }

        if ($function->getUnionType()->hasType(NeverType::instance(false))) {
            return;
        }
        // This modifies the nodes in place, check this last
        if (!BlockExitStatusChecker::willUnconditionallyNeverReturn($stmts_list)) {
            return;
        }
        self::emitIssue(
            $code_base,
            $function->getContext(),
            'PhanPluginNeverReturnFunction',
            "Function {FUNCTION} never returns and has a return type of {TYPE}, but phpdoc type {TYPE} could be used instead",
            [$function->getRepresentationForIssue(), $function->getUnionType(), 'never']
        );
    }

    /**
     * @param Func|Method $func
     * @return ?Node - returns null if there's no statement list to analyze
     */
    private static function getStatementListToAnalyze($func): ?Node
    {
        if (!$func->hasNode()) {
            return null;
        } elseif ($func->hasYield()) {
            // generators always return Generator.
            return null;
        }
        $node = $func->getNode();
        if (!$node) {
            return null;
        }
        return $node->children['stmts'];
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new NeverReturnPlugin();
