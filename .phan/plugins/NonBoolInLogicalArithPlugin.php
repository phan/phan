<?php declare(strict_types=1);
# .phan/plugins/NonBoolInLogicalArithPlugin.php

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\Plugin;
use Phan\Plugin\PluginImplementation;
use ast\Node;

class NonBoolInLogicalArithPlugin extends PluginImplementation {

    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        (new NonBoolInLogicalArithVisitor($code_base, $context, $this))(
            $node
        );
    }

}

class NonBoolInLogicalArithVisitor extends AnalysisVisitor {

    /** @var Plugin */
    private $plugin;

    /** define boolean operator list */
    const BINARY_BOOL_OPERATORS = [
        ast\flags\BINARY_BOOL_OR,
        ast\flags\BINARY_BOOL_AND,
        ast\flags\BINARY_BOOL_XOR,
    ];

    public function __construct(
        CodeBase $code_base,
        Context $context,
        Plugin $plugin
    ) {
        parent::__construct($code_base, $context);

        $this->plugin = $plugin;
    }

    public function visit(Node $node){
    }

    public function visitBinaryop(Node $node) : Context{
        // check every boolean binary operation
        if(in_array($node->flags, self::BINARY_BOOL_OPERATORS)){
            // get left node and parse it
            // (dig nodes to avoid NOT('!') operator's converting its value to boolean type)
            $left_node = $node->children['left'];
            while(isset($left_node->flags) && $left_node->flags === ast\flags\UNARY_BOOL_NOT){
                $left_node = $left_node->children['expr'];
            }

            // get right node and parse it
            $right_node = $node->children['right'];
            while(isset($right_node->flags) && $right_node->flags === ast\flags\UNARY_BOOL_NOT){
                $right_node = $right_node->children['expr'];
            }

            // get the type of two nodes
            $left_type = UnionType::fromNode($this->context, $this->code_base, $left_node);
            $right_type = UnionType::fromNode($this->context, $this->code_base, $right_node);

            // if left or right type is NOT boolean, emit issue
            if($left_type->serialize() !== "bool" || $right_type->serialize() !== "bool"){
                $this->plugin->emitIssue(
                    $this->code_base,
                    $this->context,
                    'PhanPluginNonBoolInLogicalArith',
                    'Non bool value in logical arithmetic',
                    []
                );
            }
        }
        return $this->context;
    }

}

return new NonBoolInLogicalArithPlugin;
