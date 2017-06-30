<?php declare(strict_types=1);
# .phan/plugins/NonBoolBranchPlugin.php

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\LegacyAnalyzeNodeCapability;
use ast\Node;

class NonBoolBranchPlugin extends PluginV2 implements LegacyAnalyzeNodeCapability {

    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        (new NonBoolBranchVisitor($code_base, $context, $this))(
            $node
        );
    }

}

class NonBoolBranchVisitor extends AnalysisVisitor {

    /** @var PluginV2 */
    private $plugin;

    public function __construct(
        CodeBase $code_base,
        Context $context,
        PluginV2 $plugin
    ) {
        parent::__construct($code_base, $context);

        $this->plugin = $plugin;
    }

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
            $this->plugin->emitIssue(
                $this->code_base,
                $this->context,
                'PhanPluginNonBoolBranch',
                'Non bool value evaluated in if clause',
                []
            );
        }
        return $this->context;
    }

}

return new NonBoolBranchPlugin;
