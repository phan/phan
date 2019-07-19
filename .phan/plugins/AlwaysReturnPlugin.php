<?php declare(strict_types=1);

use ast\Node;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\CodeBase;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Type\NullType;
use Phan\Language\Type\VoidType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;

/**
 * This file checks if a function, closure or method unconditionally returns.
 * If the function doesn't have a void return type,
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
final class AlwaysReturnPlugin extends PluginV3 implements
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
    ) : void {
        $stmts_list = self::getStatementListToAnalyze($method);
        if ($stmts_list === null) {
            // check for abstract methods, generators, etc.
            return;
        }
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            // Check if this was inherited by a descendant class.
            return;
        }

        if (self::returnTypeOfFunctionLikeAllowsNull($method)) {
            return;
        }
        if (!BlockExitStatusChecker::willUnconditionallyThrowOrReturn($stmts_list)) {
            if (!$method->checkHasSuppressIssueAndIncrementCount('PhanPluginAlwaysReturnMethod')) {
                self::emitIssue(
                    $code_base,
                    $method->getContext(),
                    'PhanPluginAlwaysReturnMethod',
                    "Method {METHOD} has a return type of {TYPE}, but may fail to return a value",
                    [(string)$method->getFQSEN(), (string)$method->getUnionType()]
                );
            }
        }
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
    ) : void {
        $stmts_list = self::getStatementListToAnalyze($function);
        if ($stmts_list === null) {
            // check for abstract methods, generators, etc.
            return;
        }

        if (self::returnTypeOfFunctionLikeAllowsNull($function)) {
            return;
        }
        if (!BlockExitStatusChecker::willUnconditionallyThrowOrReturn($stmts_list)) {
            if (!$function->checkHasSuppressIssueAndIncrementCount('PhanPluginAlwaysReturnFunction')) {
                self::emitIssue(
                    $code_base,
                    $function->getContext(),
                    'PhanPluginAlwaysReturnFunction',
                    "Function {FUNCTION} has a return type of {TYPE}, but may fail to return a value",
                    [(string)$function->getFQSEN(), (string)$function->getUnionType()]
                );
            }
        }
    }

    /**
     * @param Func|Method $func
     * @return ?Node - returns null if there's no statement list to analyze
     */
    private static function getStatementListToAnalyze($func) : ?Node
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

    /**
     * @param FunctionInterface $func
     * @return bool - Is void(absence of a return type) an acceptable return type.
     * NOTE: projects can customize this as needed.
     */
    private static function returnTypeOfFunctionLikeAllowsNull(FunctionInterface $func) : bool
    {
        $real_return_type = $func->getRealReturnType();
        if (!$real_return_type->isEmpty() && !$real_return_type->isType(VoidType::instance(false))) {
            return false;
        }
        $return_type = $func->getUnionType();
        return ($return_type->isEmpty()
            || $return_type->containsNullable()
            || $return_type->hasType(VoidType::instance(false))
            || $return_type->hasType(NullType::instance(false)));
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new AlwaysReturnPlugin();
