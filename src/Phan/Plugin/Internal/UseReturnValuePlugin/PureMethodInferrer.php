<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\UseReturnValuePlugin;

use ast;
use Phan\AST\InferPureVisitor;
use Phan\CodeBase;
use Phan\Exception\NodeException;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Phan;
use Phan\Plugin\Internal\UseReturnValuePlugin;
use Phan\PluginV3\IssueEmitter;

/**
 * Class containing functionality to recursively infer which methods are pure.
 */
class PureMethodInferrer
{
    use IssueEmitter;

    /**
     * Identify which methods are pure, recursively.
     */
    public static function identifyPureMethods(CodeBase $code_base) : void
    {
        $graph = new PureMethodGraph($code_base);
        // $start = microtime(true);
        // Mark methods as pure.
        foreach ($code_base->getMethodSet() as $method) {
            self::checkIsReadOnlyMethod($code_base, $method, $graph);
        }
        // Mark functions and closures as pure.
        foreach ($code_base->getFunctionMap() as $func) {
            self::checkIsReadOnlyFunction($code_base, $func, $graph);
        }
        $graph->recursivelyMarkNodesAsPure();
        // This takes around 0.12 seconds for Phan itself.
        // $end = microtime(true);
        // printf("Marking functions as pure took %.f seconds\n", $end - $start);
    }

    private static function checkIsReadOnlyMethod(CodeBase $code_base, Method $method, PureMethodGraph $graph) : void
    {
        if ($method->isPHPInternal() || $method->isAbstract()) {
            return;
        }
        if ($method->isOverriddenByAnother() || $method->isOverride()) {
            return;
        }
        if ($method->getDefiningFQSEN() !== $method->getFQSEN()) {
            return;
        }
        if (Phan::isExcludedAnalysisFile($method->getContext()->getFile())) {
            // For functions that aren't analyzed, we won't necessarily have enough information to know if they're overridden,
            // because the files they're related to might not be parsed.
            return;
        }
        $class_fqsen = $method->getFQSEN()->getFullyQualifiedClassName();
        // Hydrate the class if it wasn't already hydrated, so that Phan can accurately tell if this method is an override.
        if ($code_base->hasClassWithFQSEN($class_fqsen)) {
            $class = $code_base->getClassByFQSEN($class_fqsen);
            // @phan-suppress-next-line PhanAccessMethodInternal
            if ($class->hydrateIndicatingFirstTime($code_base)) {
                if ($method->isOverriddenByAnother() || $method->isOverride()) {
                    return;
                }
            }
        }
        self::checkIsReadOnlyFunctionCommon($code_base, $method, $graph);
    }

    private static function checkIsReadOnlyFunction(CodeBase $code_base, Func $func, PureMethodGraph $graph) : void
    {
        if ($func->isPHPInternal()) {
            return;
        }
        foreach ($func->getNode()->children['uses']->children ?? [] as $use) {
            if (($use->flags ?? 0) & ast\flags\CLOSURE_USE_REF) {
                // Assume that closures that have use by reference will have side effects.
                return;
            }
        }
        self::checkIsReadOnlyFunctionCommon($code_base, $func, $graph);
    }

    /**
     * @param Func|Method $method
     */
    private static function checkIsReadOnlyFunctionCommon(CodeBase $code_base, FunctionInterface $method, PureMethodGraph $graph) : void
    {
        if ($method->getFlags() & ast\flags\FUNC_RETURNS_REF) {
            return;
        }
        foreach ($method->getParameterList() as $param) {
            if ($param->isPassByReference()) {
                return;
            }
        }
        $node = $method->getNode()->children['stmts'] ?? null;
        if (!$node) {
            return;
        }
        $visitor = InferPureVisitor::fromFunction($code_base, $method, $graph);
        try {
            ($visitor)($node);
        } catch (NodeException $_) {
            // $context = $method->getContext();
            // echo "Skipping due to {$method->getFQSEN()} {$context->getFile()}:{$_->getNode()->lineno}: {$_->getFile()}:{$_->getLine()}: {$_->getMessage()}\n";
            // \Phan\Debug::printNode($_->getNode());
            return;
        }
        $graph->recordPotentialPureFunction($visitor->getLabel(), $method, $visitor->getUnresolvedStatusDependencies());
    }

    /**
     * Emit PhanUseReturnValueNoopVoid for regular function/methods
     * @internal
     */
    public static function warnNoopVoid(CodeBase $code_base, FunctionInterface $method) : void
    {
        if ($method instanceof Method) {
            if ($method->isMagic()) {
                return;
            }
        } elseif ($method instanceof Func && $method->isClosure()) {
            // no-op closures are usually normal
            return;
        }
        if ($method->isPHPInternal()) {
            // Don't emit this for internal stubs
            return;
        }
        // Don't warn about the **caller** of void methods that do nothing.
        // Instead, warn about the implementation of void methods.
        self::emitPluginIssue(
            $code_base,
            $method->getContext(),
            UseReturnValuePlugin::UseReturnValueNoopVoid,
            'The function/method {FUNCTION} is declared to return {TYPE} and it has no side effects',
            [$method->getRepresentationForIssue(), $method->getUnionType()]
        );
    }
}
