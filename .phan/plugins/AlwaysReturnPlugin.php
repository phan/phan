<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Type\NullType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use ast\Node;

/**
 * This file checks if a function, closure or method unconditionally returns.
 * If the function doesn't have a null, void, or nullable return type,
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
 * - Contain a class that inherits from \Phan\Plugin
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class AlwaysReturnPlugin extends PluginV2 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability {

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        $stmts_list = $this->getStatementListToAnalyze($method);
        if ($stmts_list === null) {
            // check for abstract methods, generators, etc.
            return;
        }
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            // Check if this was inherited by a descendant class.
            return;
        }

        $return_type = $method->getUnionType();

        if (self::returnTypeAllowsNull($return_type)) {
            return;
        }
        if (!BlockExitStatusChecker::willUnconditionallyThrowOrReturn($stmts_list)) {
            if (!$method->hasSuppressIssue('PhanPluginAlwaysReturnMethod')) {
                $this->emitIssue(
                    $code_base,
                    $method->getContext(),
                    'PhanPluginAlwaysReturnMethod',
                    "Method {METHOD} has a return type of {TYPE}, but may fail to return a value",
                    [(string)$method->getFQSEN(), (string)$return_type]
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
     * @return void
     *
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        $stmts_list = $this->getStatementListToAnalyze($function);
        if ($stmts_list === null) {
            // check for abstract methods, generators, etc.
            return;
        }

        $return_type = $function->getUnionType();

        if (self::returnTypeAllowsNull($return_type)) {
            return;
        }
        if (!BlockExitStatusChecker::willUnconditionallyThrowOrReturn($stmts_list)) {
            if (!$function->hasSuppressIssue('PhanPluginAlwaysReturnFunction')) {
                $this->emitIssue(
                    $code_base,
                    $function->getContext(),
                    'PhanPluginAlwaysReturnFunction',
                    "Function {FUNCTION} has a return type of {TYPE}, but may fail to return a value",
                    [(string)$function->getFQSEN(), (string)$return_type]
                );
            }
        }
    }

    /**
     * @param Func|Method $func
     * @return ?\ast\Node - returns null if there's no statement list to analyze
     */
    private function getStatementListToAnalyze($func) {
        if (!$func->hasNode()) {
            return null;
        } elseif ($func->getHasYield()) {
            // generators always return Generator.
            return null;
        }
        $node = $func->getNode();
        if (!$node) {
            return null;
        }
        assert($node->kind === \ast\AST_FUNC_DECL || $node->kind === \ast\AST_CLOSURE || $node->kind === \ast\AST_METHOD);
        return $node->children['stmts'];
    }

    /**
     * @param ?UnionType $return_type
     * @return bool - Is void(absense of a return type) an acceptable return type.
     * NOTE: projects can customize this as needed.
     */
    private function returnTypeAllowsNull($return_type) : bool
    {
        return $return_type instanceof UnionType &&
            ($return_type->isEmpty()
            || $return_type->containsNullable()
            || $return_type->hasType(VoidType::instance(false))
            || $return_type->hasType(NullType::instance(false)));
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new AlwaysReturnPlugin;
