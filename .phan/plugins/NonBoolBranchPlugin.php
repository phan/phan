<?php declare(strict_types=1);
# .phan/plugins/NonBoolBranchPlugin.php

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use ast\Node;

class NonBoolBranchPlugin extends PluginV2 implements AnalyzeNodeCapability
{
    /**
     * @return string - name of PluginAwareAnalysisVisitor subclass
     *
     * @override
     */
    public static function getAnalyzeNodeVisitorClassName() : string
    {
        return NonBoolBranchVisitor::class;
    }
}

class NonBoolBranchVisitor extends PluginAwareAnalysisVisitor
{
    // A plugin's visitors should not override visit() unless they need to.

    /** @override */
    public function visitIfelem(Node $node) : Context
    {
        $condition = $node->children['cond'];

        // dig nodes to avoid NOT('!') operator's converting its value to boolean type
        while (isset($condition->flags) && $condition->flags === ast\flags\UNARY_BOOL_NOT) {
            $condition = $condition->children['expr'];
        }

        // evaluate the type of conditional expression
        $union_type = UnionType::fromNode($this->context, $this->code_base, $condition);
        // $condition === null will be appeared in else-clause, then avoid them
        if (($union_type->serialize() !== "bool") && $condition !== null) {
            $this->emit(
                'PhanPluginNonBoolBranch',
                'Non bool value evaluated in if clause',
                []
            );
        }
        return $this->context;
    }
}

return new NonBoolBranchPlugin;
