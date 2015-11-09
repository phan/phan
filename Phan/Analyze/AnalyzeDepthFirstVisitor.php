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
            (string)$node->children[0]
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

        // Copy information from the traits into this class
        foreach ($clazz->getTraitFQSENList() as $trait_fqsen) {
            assert($this->context->getCodeBase()->hasClassWithFQSEN(
                $trait_fqsen
            ), "Trait $trait_fqsen should already have been proven to exist");

            $trait =
                $this->context->getCodeBase()->getClassByFQSEN(
                    $trait_fqsen
                );

            // Copy properties
            foreach ($trait->getPropertyMap() as $property) {
                $clazz->addProperty($property);
            }

            // Copy constants
            foreach ($trait->getConstantMap() as $constant) {
                $clazz->addConstant($constant);
            }

            // Copy methods
            foreach ($trait->getMethodMap() as $method) {
                // TODO: if the method is already there, don't add
                $clazz->addMethod($method);
            }

        }

        /*
        foreach($traits as $trait) {
            $tocopy = [];
            foreach($classes[$ltrait]['methods'] as $k=>$method) {
                if(!empty($classes[$lname]['methods'][$k])) continue; // We already have this method, skip it
                $tocopy[$k] = $method;
            }

            $classes[$lname]['methods'] = array_merge($classes[$ltrait]['methods'], $classes[$lname]['methods']);
            // Need the scope as well
            foreach($tocopy as $k=>$method) {
                if(empty($scope["{$classes[$ltrait]['name']}::{$method['name']}"])) continue;
                $cs = $namespace.$ast->name.'::'.$method['name'];
                if(!array_key_exists($cs, $scope)) $scope[$cs] = [];
                if(!array_key_exists('vars', $scope[$cs])) $scope[$cs]['vars'] = [];
                $scope[$cs] = $scope["{$classes[$ltrait]['name']}::{$method['name']}"];
                $classes[$lname]['methods'][$k]['scope'] = "{$classes[$lname]['name']}::{$method['name']}";
                // And finally re-map $this to point to this class
                $scope[$cs]['vars']['this']['type'] = $namespace.$ast->name;
            }
        }
         */
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
        if(($node->children[2] instanceof \ast\Node)
            && ($node->children[2]->kind == \ast\AST_LIST)
        ) {
            Log::err(
                Log::EFATAL,
                "Can't use list() as a key element - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $context = $this->context;
        if($node->children[1]->kind == \ast\AST_LIST) {
            foreach($node->children[1]->children as $child_node) {
                $context = $this->context->withScopeVariable(
                    Variable::fromNodeInContext($child_node, $context)
                );
            }
            if(!empty($node->children[2])) {
                $context = $this->context->withScopeVariable(
                    Variable::fromNodeInContext($node->children[2], $context)
                );
            }
        } else {
            $variable =
                Variable::fromNodeInContext(
                    $node->children[1],
                    $context,
                    false
                );

            /*
            // Get the type of the node from the left side
            $type = UnionType::fromNode(
                $this->context,
                $node->children[0]
            );

            // Set the type on the variable
            $variable->setUnionType($type);
            */

            // Add the variable to the scope
            $context =
                $this->context->withScopeVariable($variable);

            if(!empty($node->children[2])) {
                $variable =
                    Variable::fromNodeInContext(
                        $node->children[2],
                        $context,
                        false
                    );

                $context =
                    $this->context->withScopeVariable($variable);
            }
        }

        return $context;
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
        $object_name = self::astVariableName($node->children[0]);
        $name = self::astVariableName($node->children[1]);

        $context = $this->context;
        if (!empty($name)) {
            $context = $this->context->withScopeVariable(
                Variable::fromNodeInContext($node->children[1], $context)
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

        /*
        if (!$class_name) {
            Debug::PrintNode($node);
        }
        assert(!empty($class_name), 'Class name cannot be empty');
         */

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

        $method_name = $node->children[1];

        $method_fqsen =
            $clazz->getFQSEN()->withMethodName(
                $this->context, $method_name
            );

        if (!$this->context->getCodeBase()->hasMethodWithFQSEN($method_fqsen)) {
            Log::err(
                Log::EUNDEF,
                "call to undeclared method {$class_fqsen}->{$method_name}()",
                $this->context->getFile(),
                $node->lineno
            );
            return $this->context;
        }

        $method = $this->context->getCodeBase()->getMethodByFQSEN(
            $method_fqsen
        );

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
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz {
        return $this->context->getClassInScope();
    }
}
