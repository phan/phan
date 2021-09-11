<?php

declare(strict_types=1);

use ast\Node;
use Phan\Analysis\ConditionVisitor;
use Phan\AST\ASTReverter;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for uses of getters that can be avoided inside of a class.
 *
 * - E.g. `$this->getProperty()` when the property is accessible, and the getter is not overridden.
 */
class AvoidableGetterPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability
{

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return AvoidableGetterVisitor::class;
    }
}

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in post-order.
 */
class AvoidableGetterVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @var array<string,string> maps getter method names to property names.
     */
    private $getter_to_property_map = [];

    public function visitClass(Node $node): void
    {
        if (!$this->context->isInClassScope()) {
            // should be impossible
            return;
        }
        $code_base = $this->code_base;
        $class = $this->context->getClassInScope($code_base);
        $getters = $class->getGettersMap($code_base);
        if (!$getters) {
            return;
        }
        $getter_to_property_map = [];
        foreach ($getters as $prop_name => $methods) {
            $prop_name = (string)$prop_name;
            if (!$class->hasPropertyWithName($code_base, $prop_name)) {
                continue;
            }
            if (!$class->getPropertyByName($code_base, $prop_name)->isAccessibleFromClass($code_base, $class->getFQSEN())) {
                continue;
            }
            foreach ($methods as $method) {
                if ($method->isOverriddenByAnother()) {
                    continue;
                }
                $getter_to_property_map[$method->getName()] = $prop_name;
            }
        }
        if (!$getter_to_property_map) {
            return;
        }
        $this->getter_to_property_map = $getter_to_property_map;
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
        $this->recursivelyCheck($node->children['stmts']);
    }

    private function recursivelyCheck(Node $node): void
    {
        switch ($node->kind) {
            // TODO: Handle phan-closure-scope.
            // case ast\AST_CLOSURE:
            // case ast\AST_ARROW_FUNC:
            case ast\AST_FUNC_DECL:
            case ast\AST_CLASS:
                return;
                // This only supports instance method getters, not static getters (AST_STATIC_CALL)
            case ast\AST_METHOD_CALL:
                if (!ConditionVisitor::isThisVarNode($node->children['expr'])) {
                    break;
                }
                $method_name = $node->children['method'];
                if (is_string($method_name)) {
                    $property_name = $this->getter_to_property_map[$method_name] ?? null;
                    if ($property_name !== null) {
                        $this->warnCanReplaceGetterWithProperty($node, $property_name);
                        return;
                    }
                }
                break;
        }
        foreach ($node->children as $child_node) {
            if ($child_node instanceof Node) {
                $this->recursivelyCheck($child_node);
            }
        }
    }

    private function warnCanReplaceGetterWithProperty(Node $node, string $property_name): void
    {
        $class = $this->context->getClassInScope($this->code_base);
        if ($class->isTrait()) {
            $issue_name = 'PhanPluginAvoidableGetterInTrait';
        } else {
            $issue_name = 'PhanPluginAvoidableGetter';
        }

        $this->emitPluginIssue(
            $this->code_base,
            (clone $this->context)->withLineNumberStart($node->lineno),
            $issue_name,
            "Can replace {METHOD} with {PROPERTY}",
            [ASTReverter::toShortString($node), '$this->' . $property_name]
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.

return new AvoidableGetterPlugin();
