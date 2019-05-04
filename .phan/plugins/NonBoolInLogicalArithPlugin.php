<?php declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Context;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for non-booleans in either side of logical arithmetic operators
 * (e.g. &&, ||, xor)
 */
class NonBoolInLogicalArithPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return NonBoolInLogicalArithVisitor::class;
    }
}

/**
 * This visitor checks boolean logical arithmetic operations for non-boolean expressions on either side.
 */
class NonBoolInLogicalArithVisitor extends PluginAwarePostAnalysisVisitor
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
    public function visitBinaryOp(Node $node) : Context
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
            $left_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $left_node);
            $right_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $right_node);

            // if left or right type is NOT boolean, emit issue
            if (!$left_type->isExclusivelyBoolTypes()) {
                if ($left_node instanceof Node) {
                    $this->context = $this->context->withLineNumberStart($left_node->lineno);
                }
                $this->emit(
                    'PhanPluginNonBoolInLogicalArith',
                    'Non bool value of type {TYPE} in logical arithmetic',
                    [(string)$left_type]
                );
            }
            if (!$right_type->isExclusivelyBoolTypes()) {
                if ($right_node instanceof Node) {
                    $this->context = $this->context->withLineNumberStart($right_node->lineno);
                }
                $this->emit(
                    'PhanPluginNonBoolInLogicalArith',
                    'Non bool value of type {TYPE} in logical arithmetic',
                    [(string)$right_type]
                );
            }
        }
        return $this->context;
    }
}

return new NonBoolInLogicalArithPlugin();
