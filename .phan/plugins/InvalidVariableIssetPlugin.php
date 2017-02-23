<?php declare(strict_types=1);
# .phan/plugins/InvalidVariableIssetPlugin.php

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Plugin;
use Phan\Plugin\PluginImplementation;
use ast\Node;

class InvalidVariableIssetPlugin extends PluginImplementation {

    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        (new InvalidVariableIssetVisitor($code_base, $context, $this))(
            $node
        );
    }
}

class InvalidVariableIssetVisitor extends AnalysisVisitor {

    /** @var Plugin */
    private $plugin;

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

    public function visitIsset(Node $node) : Context {
        $argument = $node->children['var'];
        // get variable name from argument
        $variable = ($argument->kind === ast\AST_DIM || $argument->kind === ast\AST_PROP) ?
            $argument->children['expr'] : $argument;
        $name = $variable->children['name'];

        // emit issue if name is not declared
        if(!$this->context->getScope()->hasVariableWithName($name)){
            $this->plugin->emitIssue(
                $this->code_base,
                $this->context,
                'PhanUndeclaredVariable',
                "undeclared variables in isset()"
            );
        }
        // emit issue if argument is not array access
        elseif($argument->kind !== ast\AST_DIM){
            $this->plugin->emitIssue(
                $this->code_base,
                $this->context,
                'PhanPluginInvalidVariableIsset',
                "non array access in isset()"
            );
        }
        return $this->context;
    }

}

return new InvalidVariableIssetPlugin;
