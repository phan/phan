<?php declare(strict_types=1);
# .phan/plugins/NonBoolBranchPlugin.php

use Phan\Analysis\PostOrderAnalyzer;
use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\Plugin;
use Phan\PluginIssue;
use ast\Node;

class NonBoolBranchPlugin extends AnalysisVisitor implements PostOrderAnalyzer {
    use PluginIssue;

    public function visit(Node $node){
    }

    public function visitIfelem(Node $node) : Context {
        $condition = $node->children['cond'];

        // dig nodes to avoid NOT('!') operator's converting its value to boolean type
        while(isset($condition->flags) && $condition->flags === ast\flags\UNARY_BOOL_NOT){
            $condition = $condition->children['expr'];
        }

        // evaluate the type of conditional expression
        $union_type = UnionType::fromNode($this->context, $this->code_base, $condition);
        // $condition === null will be appeared in else-clause, then avoid them
        if(($union_type->serialize() !== "bool") && $condition !== null){
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginNonBoolBranch',
                'Non bool value evaluated in if clause'
            );
        }
        return $this->context;
    }

}

return NonBoolBranchPlugin::class;
