<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{
    Clazz,
    Comment,
    Constant,
    Method,
    Property,
    Variable
};
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new AnalyzeDepthFirstVisitor($context)
 *     );
 * ```
 */
class AnalyzeDepthFirstVisitor extends KindVisitorImplementation {
    use \Phan\Language\AST;
    use \Phan\Analyze\ArgumentType;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(Context $context) {
        $this->context = $context;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_NAMESPACE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNamespace(Node $node) : Context {
        return $this->context->withNamespace(
            (string)$node->children['name']
        );
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUseTrait(Node $node) : Context {
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_USE`
     * such as `use \ast\Node;`.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUse(Node $node) : Context {
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClass(Node $node) : Context {
        $class_name = $node->name;

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context, $class_name
            );

        if (!$this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
            Log::err(
                Log::EFATAL,
                "Can't find class {$class_fqsen} - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $clazz = $this->context->getCodeBase()->getClassByFQSEN(
            $class_fqsen
        );

        return $clazz->getContext()->withClassFQSEN(
            $clazz->getFQSEN()
        );
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_REFERENCE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethod(Node $node) : Context {
        $method_name = $node->name;

        $method_fqsen =
            $this->context->getScopeFQSEN()->withMethodName(
                $this->context, $method_name
            );

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN($method_fqsen)) {
            Log::err(
                Log::EFATAL,
                "Can't find method {$method_fqsen} - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $method = $this->context->getCodeBase()->getMethodByFQSEN(
            $method_fqsen
        );

        return $method->getContext()->withMethodFQSEN(
            $method_fqsen
        );
    }

    /**
     * Visit a node with kind `\ast\AST_FUNC_DECL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitFuncDecl(Node $node) : Context {
        $function_name = $node->name;

        $function_fqsen =
            $this->context->getScopeFQSEN()->withFunctionName(
                $this->context, $function_name
            );

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN($function_fqsen)) {
            Log::err(
                Log::EFATAL,
                "Can't find function {$function_fqsen} - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $method = $this->context->getCodeBase()->getMethodByFQSEN(
            $function_fqsen
        );

        return $method->getContext()->withMethodFQSEN(
            $function_fqsen
        );
    }

    /**
     * Visit a node with kind `\ast\AST_CLOSURE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Node $node) : Context {
        $closure_name = 'closure_' . $node->lineno;

        $closure_fqsen =
            $this->context->getScopeFQSEN()->withClosureName(
                $this->context,
                $closure_name
            );

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
            $closure_fqsen
        )) {
            Log::err(
                Log::EFATAL,
                "Can't find closure {$closure_fqsen} - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $closure = $this->context->getCodeBase()->getMethodByFQSEN(
            $closure_fqsen
        );

        /*
        if(!empty($scope[$parent_scope]['vars']['this'])) {
            // TODO: check for a static closure
            add_var_scope($current_scope, 'this', $scope[$parent_scope]['vars']['this']['type']);
        }
         */

        if(!empty($node->children[1])
            && $node->children[1]->kind == \ast\AST_CLOSURE_USES
        ) {
            $uses = $node->children[1];

            foreach($uses->children as $use) {
                if($use->kind != \ast\AST_CLOSURE_VAR) {
                    Log::err(
                        Log::EVAR,
                        "You can only have variables in a closure use() clause",
                        $this->context->getFile(),
                        $node->lineno
                    );
                } else {
                    $variable_name = self::astVariableName($use->children[0]);

                    if(empty($variable_name)) {
                        continue;
                    }

                    if($use->flags & \ast\flags\PARAM_REF) {
                        assert(false, "TODO");
                        /*
                        if(empty($parent_scope)
                            || empty($scope[$parent_scope]['vars'])
                            || empty($scope[$parent_scope]['vars'][$name])
                        ) {
                            add_var_scope($parent_scope, $name, '');
                        }
                        $scope[$current_scope]['vars'][$name] =
                            &$scope[$parent_scope]['vars'][$name];
                         */
                    } else {
                        if (!$this->context->getScope()->hasVariableWithName(
                            $variable_name
                        )) {
                            Log::err(
                                Log::EVAR,
                                "Variable \${$variable_name} is not defined",
                                $this->context->getFile(),
                                $node->lineno
                            );
                        }
                    }
                }
            }
        }

        return $closure->getContext()->withClosureFQSEN(
            $closure_fqsen
        );
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitForeach(Node $node) : Context {
        if($node->children['value']->kind == \ast\AST_LIST) {
            foreach($node->children['value']->children as $child_node) {
                $this->context->addScopeVariable(
                    Variable::fromNodeInContext($child_node, $this->context)
                );
            }

        // Otherwise, read the value as regular variable and
        // add it to the scope
        } else {
            // Create a variable for the value
            $variable = Variable::fromNodeInContext(
                $node->children['value'],
                $this->context,
                false
            );

            // Get the type of the node from the left side
            $type = UnionType::fromNode(
                $this->context,
                $node->children['expr']
            );

            // Filter out the non-generic types of the
            // expression
            $non_generic_type = $type->asNonGenericTypes();

            // If we were able to figure out the type and its
            // a generic type, then set its element types as
            // the type of the variable
            if (!$non_generic_type->isEmpty()) {
                $variable->setUnionType($non_generic_type);
            }

            // Add the variable to the scope
            $this->context->addScopeVariable($variable);
        }

        // If there's a key, make a variable out of that too
        if(!empty($node->children['key'])) {
            if(($node->children['key'] instanceof \ast\Node)
                && ($node->children['key']->kind == \ast\AST_LIST)
            ) {
                Log::err(
                    Log::EFATAL,
                    "Can't use list() as a key element - aborting",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

            $variable = Variable::fromNodeInContext(
                $node->children['key'],
                $this->context,
                false
            );

            $this->context->addScopeVariable($variable);
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCatch(Node $node) : Context {
        $object_name =
            self::astVariableName($node->children['class']);

        $name =
            self::astVariableName($node->children['var']);

        $context = $this->context;
        if (!empty($name)) {
            $context = $this->context->withScopeVariable(
                Variable::fromNodeInContext(
                    $node->children['var'],
                    $context
                )
            );
        }

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethodCall(Node $node) : Context {
        // Find out the name of the class for which we're
        // calling a method
        $class_name =
            self::astClassNameFromNode($this->context, $node);

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (empty($class_name)) {
            return $this->context;
        }

        // TODO: What do we do with a method call on
        //       something that is null. Log an error?
        if (in_array($class_name, [
            '\null', '\string', '\object', 'null'
        ])) {
            // These seem to be coming from default values
            // being assigned to variables that are not being
            // overridden by docBlock annotations
            Debug::printNode($node);
            assert(false, "Class name $class_name is fucked in context {$this->context}");
            return $this->context;
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context, $class_name
            );

        if (!$this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
            Log::err(
                Log::EFATAL,
                "Can't find class {$class_fqsen} - aborting",
                $this->context->getFile(),
                $node->lineno
            );
            return $this->context;
        }

        $clazz = $this->context->getCodeBase()->getClassByFQSEN(
            $class_fqsen
        );

        $method_name = $node->children['method'];

        if ($method_name instanceof Node) {
            // TODO: The method_name turned out to
            //       be a variable. We'd have to look
            //       that up to figure out what the
            //       string is, but thats a drag.
            return $this->context;
        }

        assert(is_string($method_name),
            "Method name must be a string. Found non-string at {$this->context}");

        if (!$clazz->hasMethodWithName($method_name)) {
            Log::err(
                Log::EUNDEF,
                "call to undeclared method {$class_fqsen}->{$method_name}()",
                $this->context->getFile(),
                $node->lineno
            );

            return $this->context;
        }

        $method = $clazz->getMethodByName($method_name);

        if($method->getName() != 'dynamic') {
            if(array_key_exists('avail', $method)
                && !$method['avail']
            ) {
                Log::err(
                    Log::EAVAIL,
                    "method {$class_fqsen}::{$method_name}() is not compiled into this version of PHP",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

            self::analyzeArgumentType($method, $node);

            /*
            if($method->getContext()->getFile() != 'internal') {
                // re-check the function's ast with these args
                if(!$quick_mode) {
                    pass2(
                        $method['file'],
                        $method['namespace'],
                        $method['ast'],
                        $method['scope'],
                        $ast,
                        $classes[strtolower($class_name)],
                        $method,
                        $parent_scope
                    );
                }
            }
             */
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_GLOBAL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGlobal(Node $node) : Context {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            false
        );

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

        return $this->context;
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz {
        return $this->context->getClassInScope();
    }
}
