<?php declare(strict_types=1);
# .phan/plugins/InvalidVariableIssetPlugin.php

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use ast\Node;

class InvalidVariableIssetPlugin extends PluginV2 implements AnalyzeNodeCapability {

    /**
     * @return string - name of PluginAwareAnalysisVisitor subclass
     *
     * @override
     */
    public static function getAnalyzeNodeVisitorClassName() : string {
        return InvalidVariableIssetVisitor::class;
    }
}

class InvalidVariableIssetVisitor extends PluginAwareAnalysisVisitor {

    /** define classes to parse */
    const CLASSES = [
        ast\AST_STATIC_CALL,
        ast\AST_STATIC_PROP,
    ];

    /** define expression to parse */
    const EXPRESSIONS = [
        ast\AST_CALL,
        ast\AST_DIM,
        ast\AST_INSTANCEOF,
        ast\AST_METHOD_CALL,
        ast\AST_PROP,
    ];

    // A plugin's visitors should not override visit() unless they need to.

    /** @override */
    public function visitIsset(Node $node) : Context {
        $argument = $node->children['var'];
        $variable = $argument;

        // get variable name from argument
        while (!isset($variable->children['name'])){
            if (in_array($variable->kind, self::EXPRESSIONS)){
                $variable = $variable->children['expr'];
            } elseif (in_array($variable->kind, self::CLASSES)){
                $variable = $variable->children['class'];
            }
        }
        $name = $variable->children['name'];

        // emit issue if name is not declared
        // Check for edge cases such as isset($$var)
        if (is_string($name) && !$this->context->getScope()->hasVariableWithName($name)){
            $this->emit(
                'PhanUndeclaredVariable',
                "undeclared variables in isset()",
                []
            );
        } elseif ($argument->kind !== ast\AST_DIM){
            // emit issue if argument is not array access
            $this->emit(
                'PhanPluginInvalidVariableIsset',
                "non array access in isset()",
                []
            );
        }
        return $this->context;
    }
}

return new InvalidVariableIssetPlugin;
