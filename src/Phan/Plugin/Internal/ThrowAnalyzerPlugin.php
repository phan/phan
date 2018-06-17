<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\PluginV2;
use Phan\PluginV2\PostAnalyzeNodeCapability;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use ast\Node;
use ast;

// ThrowAnalyzerPlugin analyzes throw statements and
// compares them against the phpdoc (at)throws annotations

class ThrowAnalyzerPlugin extends PluginV2 implements PostAnalyzeNodeCapability
{
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return ThrowVisitor::class;
    }
}

class ThrowVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @var Node[] Dynamic
     * @suppress PhanReadOnlyProtectedProperty set by the framework
     */
    protected $parent_node_list;

    public function visitThrow(Node $node)
    {
        $context = $this->context;
        if (!$context->isInFunctionLikeScope()) {
            return;
        }
        $code_base = $this->code_base;

        // TODO: Does phan warn about invalid throw statement types in visitThrow already?
        $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node->children['expr']);
        if ($union_type->isEmpty()) {
            // Give up if we don't know
            // TODO: Infer throwable, if there are no try/catch blocks
            return;
        }
        $function = $context->getFunctionLikeInScope($code_base);

        foreach ($this->parent_node_list as $parent) {
            if ($parent->kind !== ast\AST_TRY) {
                continue;
            }
            foreach ($parent->children['catches']->children as $catch_node) {
                $caught_union_type = UnionTypeVisitor::unionTypeFromClassNode($code_base, $context, $catch_node->children['class']);
                foreach ($union_type->getTypeSet() as $type) {
                    if (!$type->asExpandedTypes($code_base)->canCastToUnionType($caught_union_type)) {
                        $union_type = $union_type->withoutType($type);
                        if ($union_type->isEmpty()) {
                            return;
                        }
                    }
                }
            }
        }
        $throws_union_type = $function->getThrowsUnionType();
        foreach ($union_type->getTypeSet() as $type) {
            if ($throws_union_type->isEmpty()) {
                $this->emitIssue(
                    Issue::ThrowTypeAbsent,
                    $node->lineno,
                    (string)$function->getFQSEN(),
                    (string)$union_type
                );
                continue;
            }
            if (!$type->asExpandedTypes($code_base)->canCastToUnionType($throws_union_type)) {
                $this->emitIssue(
                    Issue::ThrowTypeMismatch,
                    $node->lineno,
                    (string)$function->getFQSEN(),
                    (string)$union_type,
                    $throws_union_type
                );
            }
        }
    }
}
