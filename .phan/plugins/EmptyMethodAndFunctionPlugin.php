<?php declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;

/**
 * Checks for empty methods/functions
 */
final class EmptyMethodAndFunctionPlugin extends PluginV3 implements AnalyzeMethodCapability, AnalyzeFunctionCapability
{

    /**
     * @param CodeBase $code_base
     * @param Method $method
     */
    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
        if ($method->getNode()
            && $method->getNode()->children['stmts'] instanceof Node
            && !$method->getNode()->children['stmts']->children
            && !$method->isOverriddenByAnother()
            && !$method->isOverride()
            && !$method->isDeprecated()
        ) {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $this->getIssueTypeForEmptyMethodOrFunction($method),
                $method->getNode()->lineno,
                $method->getName()
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * @param Func $function
     */
    public function analyzeFunction(CodeBase $code_base, Func $function): void
    {
        if ($function->getNode()
            && $function->getNode()->children['stmts'] instanceof Node
            && !$function->getNode()->children['stmts']->children
            && !$function->isDeprecated()
        ) {
            Issue::maybeEmit(
                $code_base,
                $function->getContext(),
                Issue::EmptyFunction,
                $function->getNode()->lineno,
                $function->getName()
            );
        }
    }

    /**
     * @param Method $method
     * @return string
     */
    private function getIssueTypeForEmptyMethodOrFunction(Method $method) : string
    {
        if ($method->isPrivate()) {
            return Issue::EmptyPrivateMethod;
        }

        if ($method->isProtected()) {
            return Issue::EmptyProtectedMethod;
        }

        return Issue::EmptyPublicMethod;
    }
}

return new EmptyMethodAndFunctionPlugin;