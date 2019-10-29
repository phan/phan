<?php declare(strict_types=1);

use ast\Node;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Plugin\Internal\RedundantConditionLoopCheck;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin detects reuse of loop variables
 */
class LoopVariableReusePlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return LoopVariableReuseVisitor::class;
    }
}

/**
 * This visitor implements the checks for reuse of loop variables.
 */
class LoopVariableReuseVisitor extends PluginAwarePostAnalysisVisitor
{
    // A plugin's visitors should not override visit() unless they need to.

    /**
     * @var list<Node> set by plugin framework
     * @suppress PhanReadOnlyProtectedProperty
     */
    protected $parent_node_list;

    /**
     * @override checks for reuse of variables in a node of kind ast\AST_FOREACH
     */
    public function visitForeach(Node $node) : Context
    {
        $this->findVariableReuse($this->extractLoopVariablesOfForeach($node));
        return $this->context;
    }

    /**
     * @return array<string|int,Node>
     */
    private function extractLoopVariablesOfForeach(Node $node) : array
    {
        return $this->extractVariables($node->children['key']) + $this->extractVariables($node->children['value']);
    }

    /**
     * @override checks for reuse of variables in a node of kind ast\AST_FOR
     */
    public function visitFor(Node $node) : Context
    {
        $this->findVariableReuse($this->extractLoopVariablesOfFor($node));
        return $this->context;
    }

    /**
     * @param Node $node a node of kind ast\AST_FOR
     * @return array<string|int,Node>
     * @suppress PhanAccessMethodInternal
     */
    private function extractLoopVariablesOfFor(Node $node) : array
    {
        $directions = RedundantConditionLoopCheck::extractComparisonDirections($node->children['cond']) +
            RedundantConditionLoopCheck::extractIncrementDirections($this->code_base, $this->context, $node->children['loop']);
        if (!$directions) {
            return [];
        }
        $variables = self::extractVariables($node->children['cond']) + self::extractVariables($node->children['loop']);
        return array_intersect_key($variables, $directions);
    }

    /**
     * @override checks for reuse of variables in a node of kind ast\AST_WHILE
     */
    public function visitWhile(Node $node) : Context
    {
        $this->findVariableReuse($this->extractLoopVariablesOfWhile($node));
        return $this->context;
    }

    /**
     * @param Node $node a node of kind ast\AST_WHILE
     * @return array<string|int,Node>
     * @suppress PhanAccessMethodInternal
     */
    private function extractLoopVariablesOfWhile(Node $node) : array
    {
        $directions = RedundantConditionLoopCheck::extractComparisonDirections($node->children['cond']);
        if (!$directions) {
            return [];
        }
        return array_intersect_key(self::extractVariables($node->children['cond']), $directions);
    }

    /**
     * @param array<string|int,Node> $variables
     */
    private function findVariableReuse(array $variables) : void
    {
        if (!$variables) {
            return;
        }
        for ($i = count($this->parent_node_list) - 1; $i >= 0; $i--) {
            $parent_node = $this->parent_node_list[$i];
            $outer_variables = [];
            switch ($parent_node->kind) {
                case ast\AST_FOREACH:
                    $outer_variables = $this->extractLoopVariablesOfForeach($parent_node);
                    break;
                case ast\AST_FOR:
                    $outer_variables = $this->extractLoopVariablesOfFor($parent_node);
                    break;
                case ast\AST_WHILE:
                    $outer_variables = $this->extractLoopVariablesOfWhile($parent_node);
                    break;

                case ast\AST_FUNC_DECL:
                case ast\AST_CLOSURE:
                case ast\AST_ARROW_FUNC:
                case ast\AST_METHOD:
                case ast\AST_CLASS:
                    return;
                default:
                    continue 2;
            }
            $common_outer_variables = array_intersect_key($outer_variables, $variables);
            if ($common_outer_variables) {
                $this->warnCommonOuterVariables($variables, $common_outer_variables);
                return;
            }
        }
    }

    /**
     * @param array<string|int,Node> $variables
     * @param array<string|int,Node> $common_outer_variables
     */
    private function warnCommonOuterVariables(array $variables, array $common_outer_variables) : void
    {
        foreach ($common_outer_variables as $variable_name => $node) {
            $inner_node = $variables[$variable_name];
            $this->emitPluginIssue(
                $this->code_base,
                (clone($this->context))->withLineNumberStart($inner_node->lineno),
                'PhanPluginLoopVariableReuse',
                'Variable ${VARIABLE} used in loop was also used in an outer loop on line {LINE}',
                [$variable_name, $node->lineno]
            );
        }
    }

    /**
     * @param Node|string|int|float|null $node
     * @return array<int|string,Node> a list of all variable nodes in this foreach
     */
    private function extractVariables($node) : array
    {
        if (!$node instanceof Node) {
            return [];
        }
        switch ($node->kind) {
            case ast\AST_VAR:
                if ($node->kind === ast\AST_VAR) {
                    $var_name = $node->children['name'];
                    if (is_string($var_name)) {
                        if (in_array($var_name, ['this', '_'], true) || Variable::isHardcodedVariableInScopeWithName($var_name, $this->context->isInGlobalScope())) {
                            return [];
                        }
                        return [$var_name => $node];
                    }
                }
                break;
                // Kinds of nodes we don't bother checking
            case ast\AST_STATIC_PROP:
            case ast\AST_PROP:
                // Kinds of declarations creating a new scope.
            case ast\AST_FUNC_DECL:
            case ast\AST_CLOSURE:
            case ast\AST_ARROW_FUNC:
            case ast\AST_METHOD:
            case ast\AST_CLASS:
                // FUNC_DECL and METHOD are probably unreachable.
                return [];
        }
        $result = [];
        foreach ($node->children as $child_node) {
            $result += self::extractVariables($child_node);
        }
        return $result;
    }
}

return new LoopVariableReusePlugin();
