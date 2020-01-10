<?php

declare(strict_types=1);

use ast\Node;
use Phan\Issue;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * Plugin which looks for empty methods/functions
 *
 * This Plugin hooks into one event;
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
 *   file being analyzed
 */
final class EmptyMethodAndFunctionPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return EmptyMethodAndFunctionVisitor::class;
    }
}

/**
 * Visit method/function/closure
 */
final class EmptyMethodAndFunctionVisitor extends PluginAwarePostAnalysisVisitor
{

    public function visitMethod(Node $node): void
    {
        $stmts_node = $node->children['stmts'] ?? null;

        if ($stmts_node && !$stmts_node->children) {
            $method = $this->context->getFunctionLikeInScope($this->code_base);
            if (!($method instanceof Method)) {
                throw new AssertionError("Expected $method to be a method");
            }

            if (!$method->isOverriddenByAnother()
                && !$method->isOverride()
                && !$method->isDeprecated()
            ) {
                $this->emitIssue(
                    self::getIssueTypeForEmptyMethod($method),
                    $node->lineno,
                    $method->getName()
                );
            }
        }
    }

    public function visitFuncDecl(Node $node): void
    {
        $this->analyzeFunction($node);
    }

    public function visitClosure(Node $node): void
    {
        $this->analyzeFunction($node);
    }

    // No need for visitArrowFunc.
    // By design, `fn($args) => expr` can't have an empty statement list because it must have an expression.
    // It's always equivalent to `return expr;`

    private function analyzeFunction(Node $node): void
    {
        $stmts_node = $node->children['stmts'] ?? null;

        if ($stmts_node && !$stmts_node->children) {
            $function = $this->context->getFunctionLikeInScope($this->code_base);
            if (!($function instanceof Func)) {
                throw new AssertionError("Expected $function to be Func\n");
            }

            if (! $function->isDeprecated()) {
                if (!$function->isClosure()) {
                    $this->emitIssue(
                        Issue::EmptyFunction,
                        $node->lineno,
                        $function->getName()
                    );
                } else {
                    $this->emitIssue(
                        Issue::EmptyClosure,
                        $node->lineno
                    );
                }
            }
        }
    }

    private static function getIssueTypeForEmptyMethod(FunctionInterface $method): string
    {
        if (!$method instanceof Method) {
            throw new \InvalidArgumentException("\$method is not an instance of Method");
        }

        if ($method->isPrivate()) {
            return Issue::EmptyPrivateMethod;
        }

        if ($method->isProtected()) {
            return Issue::EmptyProtectedMethod;
        }

        return Issue::EmptyPublicMethod;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new EmptyMethodAndFunctionPlugin();
