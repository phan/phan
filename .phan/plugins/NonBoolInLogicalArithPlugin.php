<?php declare(strict_types=1);
# .phan/plugins/NonBoolInLogicalArithPlugin.php

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use ast\Node;

class NonBoolInLogicalArithPlugin extends PluginV2 implements AnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwareAnalysisVisitor subclass
     *
     * @override
     */
    public static function getAnalyzeNodeVisitorClassName() : string
    {
        return NonBoolInLogicalArithVisitor::class;
    }
}

class NonBoolInLogicalArithVisitor extends PluginAwareAnalysisVisitor
{

    /** define boolean operator list */
    const BINARY_BOOL_OPERATORS = [
        ast\flags\BINARY_BOOL_OR,
        ast\flags\BINARY_BOOL_AND,
        ast\flags\BINARY_BOOL_XOR,
    ];

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @override
     */
    public function visitBinaryop(Node $node) : Context
    {
        // check every boolean binary operation
        if (in_array($node->flags, self::BINARY_BOOL_OPERATORS, true)) {
            // get left node and parse it
            // (dig nodes to avoid NOT('!') operator's converting its value to boolean type)
            $left_node = $node->children['left'];
            while (isset($left_node->flags) && $left_node->flags === ast\flags\UNARY_BOOL_NOT) {
                $left_node = $left_node->children['expr'];
            }

            // get right node and parse it
            $right_node = $node->children['right'];
            while (isset($right_node->flags) && $right_node->flags === ast\flags\UNARY_BOOL_NOT) {
                $right_node = $right_node->children['expr'];
            }

            // get the type of two nodes
            $left_type = UnionType::fromNode($this->context, $this->code_base, $left_node);
            $right_type = UnionType::fromNode($this->context, $this->code_base, $right_node);

            // if left or right type is NOT boolean, emit issue
            if ($left_type->serialize() !== "bool" || $right_type->serialize() !== "bool") {
                $this->emit(
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
