<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\PhanAnnotationAdder;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin detects variables with constant values
 */
class ConstantVariablePlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return ConstantVariableVisitor::class;
    }
}

/**
 * This plugin checks if variable uses have constant values.
 */
class ConstantVariableVisitor extends PluginAwarePostAnalysisVisitor
{

    /** @var Node[] the parent nodes of the analyzed node */
    protected $parent_node_list;

    // A plugin's visitors should not override visit() unless they need to.

    /** @override */
    public function visitVar(Node $node): void
    {
        // @phan-suppress-next-line PhanUndeclaredProperty
        if ($node->flags & PhanAnnotationAdder::FLAG_INITIALIZES || isset($node->is_reference)) {
            return;
        }
        $var_name = $node->children['name'];
        if (!is_string($var_name)) {
            return;
        }
        if ($this->context->isInLoop() || $this->context->isInGlobalScope()) {
            return;
        }
        $parent_node = end($this->parent_node_list);
        if ($parent_node instanceof Node) {
            switch ($parent_node->kind) {
                case ast\AST_IF_ELEM:
                    // Phan modifies type to match condition before plugins are called.
                    // --redundant-condition-detection would warn
                    return;
                case ast\AST_ASSIGN_OP:
                    if ($parent_node->children['var'] === $node) {
                        return;
                    }
                    break;
            }
        }
        $variable = $this->context->getScope()->getVariableByNameOrNull($var_name);
        if (!$variable) {
            return;
        }
        $type = $variable->getUnionType();
        if ($type->isPossiblyUndefined()) {
            return;
        }
        $value = $type->getRealUnionType()->asSingleScalarValueOrNullOrSelf();
        if (is_object($value)) {
            return;
        }
        // TODO: Account for methods expecting references
        if (is_bool($value)) {
            $issue_type = 'PhanPluginConstantVariableBool';
        } elseif (is_null($value)) {
            $issue_type = 'PhanPluginConstantVariableNull';
        } else {
            $issue_type = 'PhanPluginConstantVariableScalar';
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            $issue_type,
            'Variable ${VARIABLE} is probably constant with a value of {TYPE}',
            [$var_name, $type]
        );
    }
}

return new ConstantVariablePlugin();
