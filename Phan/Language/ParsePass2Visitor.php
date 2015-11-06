<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Comment, Constant, Method, Property};
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Log;
use \ast\Node;

/**
 * The class is a visitor for AST nodes that does parsing. Each
 * visitor populates the $context->getCodeBase() with any
 * globally accessible structural elements and will return a
 * possibly new context as modified by the given node.
 *
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new ParseVisitor($context)
 *     );
 * ```
 */
class ParsePass2Visitor extends KindVisitorImplementation {
    use \Phan\Language\AST;

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
            ), "Trait should already have been proven to exist");

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

        return $this->context;
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

        /*
        $closure_name = '{closure '.$ast->id.'}';
        $functions[$closure_name] = node_func($file, false, $ast, $closure_name, '');
        $current_function = $closure_name;
        $parent_scope = $current_scope;
        $current_scope = $closure_name;
        if(!empty($scope[$parent_scope]['vars']['this'])) {
            // TODO: check for a static closure
            add_var_scope($current_scope, 'this', $scope[$parent_scope]['vars']['this']['type']);
        }
        if(!empty($ast->children[1]) && $ast->children[1]->kind == \ast\AST_CLOSURE_USES) {
            $uses = $ast->children[1];
            foreach($uses->children as $use) {
                if($use->kind != \ast\AST_CLOSURE_VAR) {
                    Log::err(Log::EVAR, "You can only have variables in a closure use() clause", $file, $ast->lineno);
                } else {
                    $name = var_name($use->children[0]);
                    if(empty($name)) continue;
                    if($use->flags & \ast\flags\PARAM_REF) {
                        if(empty($parent_scope) || empty($scope[$parent_scope]['vars']) || empty($scope[$parent_scope]['vars'][$name])) {
                            add_var_scope($parent_scope, $name, '');
                        }
                        $scope[$current_scope]['vars'][$name] = &$scope[$parent_scope]['vars'][$name];
                    } else {
                        if(empty($parent_scope) || empty($scope[$parent_scope]['vars']) || empty($scope[$parent_scope]['vars'][$name])) {
                            Log::err(Log::EVAR, "Variable \${$name} is not defined", $file, $ast->lineno);
                        } else {
                            $scope[$current_scope]['vars'][$name] = $scope[$parent_scope]['vars'][$name];
                        }
                    }
                }
            }
        }
        */
        return $this->context;
    }
    public function visitForeach(Node $node) : Context {
        /*
        if(($ast->children[2] instanceof \ast\Node) && ($ast->children[2]->kind == \ast\AST_LIST)) {
            Log::err(Log::EFATAL, "Can't use list() as a key element - aborting", $file, $ast->lineno);
        }
        if($ast->children[1]->kind == \ast\AST_LIST) {
            foreach($ast->children[1]->children as $node) {
                add_var_scope($current_scope, var_name($node), '', true);
            }
            if(!empty($ast->children[2])) {
                add_var_scope($current_scope, var_name($ast->children[2]), '', true);
            }
        } else {
            // value
            add_var_scope($current_scope, var_name($ast->children[1]), '', true);
            // key
            if(!empty($ast->children[2])) {
                add_var_scope($current_scope, var_name($ast->children[2]), '', true);
            }
        }
        */
        return $this->context;
    }

    public function visitCatch(Node $node) : Context {
        /*
        $obj = var_name($ast->children[0]);
        $name = var_name($ast->children[1]);
        if(!empty($name))
            add_var_scope($current_scope, $name, $obj, true);
         */
        return $this->context;
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
        return $this->visitReturn($node);
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz {
        return $this->context->getClassInScope();
    }
}
