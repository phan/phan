<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\Issue;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * Checks for potentially misusing static variables
 */
final class StaticVariableMisusePlugin extends PluginV3 implements
    PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return StaticVariableMisuseVisitor::class;
    }
}

/**
 * Checks node kinds that can be used to access the inherited class
 * for conflicts with uses of static variables.
 */
final class StaticVariableMisuseVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     */
    public function visitVar(Node $node): void
    {
        $name = $node->children['name'];
        if ($name !== 'this') {
            return;
        }
        $this->analyzeStaticAccessCommon($node);
    }

    /**
     * @override
     */
    public function visitName(Node $node): void
    {
        $context = $this->context;
        if (!$context->isInClassScope() || !$context->isInFunctionLikeScope()) {
            return;
        }
        $name = $node->children['name'];
        if (!is_string($name)) {
            return;
        }
        if (strcasecmp($name, 'static') !== 0) {
            return;
        }
        $this->analyzeStaticAccessCommon($node);
    }

    private function analyzeStaticAccessCommon(Node $node): void
    {
        $context = $this->context;
        if (!$context->isInClassScope() || !$context->isInFunctionLikeScope()) {
            return;
        }
        $function = $context->getFunctionLikeInScope($this->code_base);
        if (!$function->hasStaticVariable()) {
            return;
        }
        $class = $context->getClassInScope($this->code_base);
        if ($class->isFinal()) {
            return;
        }
        $this->emitIssue(
            Issue::StaticClassAccessWithStaticVariable,
            $node->lineno,
            ASTReverter::toShortString($node)
        );
    }
}
return new StaticVariableMisusePlugin();
