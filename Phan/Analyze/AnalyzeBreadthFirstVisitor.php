<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Language\AST;
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
use \Phan\Langauge\Type;
use \Phan\Language\FQSEN;
use \Phan\Language\Type\ArrayType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new AnalyzeBreadthFirstVisitor($context)
 *     );
 * ```
 */
class AnalyzeBreadthFirstVisitor extends KindVisitorImplementation {
    use \Phan\Analyze\ArgumentType;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @var Node|null
     */
    private $parent_node;

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|null $parent_node
     * The parent node of the node being analyzed
     */
    public function __construct(
        Context $context,
        Node $parent_node = null
    ) {
        $this->context = $context;
        $this->parent_node = $parent_node;
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
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssign(Node $node) : Context {
        if($node->children['var'] instanceof \ast\Node
            && $node->children['var']->kind == \ast\AST_LIST
        ) {
            // Get the type of the right side of the
            // assignment
            $right_type =
                UnionType::fromNode($this->context, $node);

            // Figure out the type of elements in the list
            $element_type =
                $right_type->asNonGenericTypes();

            foreach($node->children['var']->children as $child_node) {
                // Some times folks like to pass a null to
                // a list to throw the element away. I'm not
                // here to judge.
                if (!($child_node instanceof Node)) {
                    continue;
                }

                $variable = Variable::fromNodeInContext(
                    $child_node,
                    $this->context,
                    false
                );

                // Set the element type on each element of
                // the list
                $variable->setUnionType($element_type);

                // Note that we're not creating a new scope, just
                // adding variables to the existing scope
                $this->context->addScopeVariable($variable);
            }

            return $this->context;
        }

        // Get the type of the right side of the
        // assignment
        $right_type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        $variable = null;

        // Check to see if this is an array offset type
        // thing like '$a[] = 5'.
        if ($node->children['var'] instanceof Node
            && $node->children['var']->kind === \ast\AST_DIM
        ) {
            $variable_name =
                AST::variableName($node->children['var']);

            // Check to see if the variable is not yet defined
            if ($this->context->getScope()->hasVariableWithName(
                $variable_name
            )) {
                $variable = $this->context->getScope()->getVariableWithName(
                    $variable_name
                );

            // If it didn't exist, create the variable
            } else {
                $variable = Variable::fromNodeInContext(
                    $node->children['var'],
                    $this->context
                );
            }

            // Make the right type a generic (i.e. int -> int[])
            $right_type = $right_type->asGenericTypes();
        } else if ($node->children['var']->kind === \ast\AST_PROP) {

            $property_name = $node->children['var']->children['prop'];

            // Things like $foo->$bar
            if (!is_string($property_name)) {
                return $this->context;
            }

            assert(is_string($property_name),
                "Property must be string in context {$this->context}");

            $class_name = AST::classNameFromNode(
                $this->context, $node->children['var']
            );

            // If we can't figure out the class name (which happens
            // from time to time), then give up
            if (empty($class_name)) {
                return $this->context;
            }

            $class_fqsen =
                $this->context->getScopeFQSEN()->withClassName(
                    $this->context, $class_name
                );

            // Check to see if the class actually exists
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

            if (!$clazz->hasPropertyWithName($property_name)) {

                // Check to see if the class has a __set method
                if (!$clazz->hasMethodWithName('__set')) {
                    Log::err(
                        Log::EAVAIL,
                        "Missing property with name '$property_name'",
                        $this->context->getFile(),
                        $node->lineno
                    );
                }

                return $this->context;
            }

            $property = $clazz->getPropertyWithName($property_name);

            if (!$right_type->canCastToExpandedUnionType(
                $property->getUnionType(),
                $this->context->getCodeBase()
            )) {
                Log::err(
                    Log::ETYPE,
                    "assigning $right_type to property but {$property->getName()} is declared to be {$property->getUnionType()}",
                    $this->context->getFile(),
                    $node->lineno
                );

                return $this->context;

                // TODO: Alternatively, we could add this type to the
                //       property's possible types
                // Add the type assigned to it to its type
                // $property->getUnionType()->addUnionType($right_type);
            }

        } else {
            // Create a new variable
            $variable = Variable::fromNodeInContext(
                $node,
                $this->context
            );

            // Set that type on the variable
            $variable->setUnionType($right_type);

            // Note that we're not creating a new scope, just
            // adding variables to the existing scope
            $this->context->addScopeVariable($variable);
        }

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
    public function visitAssignRef(Node $node) : Context {
        return $this->visitAssign($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitList(Node $node) : Context {
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
    public function visitIfElem(Node $node) : Context {
        // Just check for errors in the expression
        if (isset($node->children['cond'])
            && $node->children['cond'] instanceof Node
        ) {
            $expression_type = UnionType::fromNode(
                $this->context,
                $node->children['cond']
            );
        }

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
    public function visitWhile(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitch(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitchCase(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitExprList(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDoWhile(Node $node) : Context {
        /*
        node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
         */
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
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitForeach(Node $node) : Context {
        $expression_type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        // Check the expression type to make sure its
        // something we can iterate over
        if ($expression_type->isScalar()) {
            Log::err(
                Log::ETYPE,
                "$expression_type passed to foreach instead of array",
                $this->context->getFile(),
                $node->lineno
            );
        }

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
    public function visitStatic(Node $node) : Context {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            false
        );

        // If the element has a default, set its type
        // on the variable
        if (isset($node->children['default'])) {
            $default_type = UnionType::fromNode(
                $this->context,
                $node->children['default']
            );

            $variable->setUnionType($default_type);
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

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
    public function visitEcho(Node $node) : Context {
        return $this->visitPrint($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPrint(Node $node) : Context {
        $type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        if ($type->isType(ArrayType::instance())
            || $type->isGeneric()
        ) {
            Log::err(
                Log::ETYPE,
                "array to string conversion",
                $this->context->getFile(),
                $node->lineno
            );
        }

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
    public function visitVar(Node $node) : Context {
        $this->checkNoOp($node, "no-op variable");
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
    public function visitArray(Node $node) : Context {
        $this->checkNoOp($node, "no-op array");
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
    public function visitConst(Node $node) : Context {
        $this->checkNoOp($node, "no-op constant");
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
    public function visitClosure(Node $node) : Context {
        $this->checkNoOp($node, "no-op closure");
        return $this->context->withClosureFQSEN(
            $this->context->getScopeFQSEN()->withClosureName(
                $this->context,
                'closure_' . $node->lineno
            )
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
    public function visitReturn(Node $node) : Context {

        // Don't check return types in traits
        if ($this->context->isClassScope()) {
            $clazz = $this->context->getClassInScope();
            if ($clazz->isTrait()) {
                return $this->context;
            }
        }

        // Make sure we're actually returning from a method.
        if (!$this->context->isMethodScope()
            && !$this->context->isClosureScope()) {
            return $this->context;
        }

        // Get the method/function/closure we're in
        $method = null;
        if ($this->context->isClosureScope()) {
            $method = $this->context->getClosureInScope();
        } else if ($this->context->isMethodScope()) {
            $method = $this->context->getMethodInScope();
        } else {
            assert(false,
                "We're supposed to be in either method or closure scope.");
        }

        // Figure out what we intend to return
        $method_return_type = $method->getUnionType();

        // Figure out what is actually being returned
        $expression_type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        // If there is no declared type, see if we can deduce
        // what it should be based on the return type
        if ($method_return_type->isEmpty()) {

            // Set the inferred type of the method based
            // on what we're returning
            $method->getUnionType()->addUnionType($expression_type);

            // No point in comparing this type to the
            // type we just set
            return $this->context;
        }

        if (!$expression_type->canCastToExpandedUnionType(
            $method_return_type,
            $this->context->getCodeBase()
        )) {
            Log::err(
                Log::ETYPE,
                "return $expression_type but {$method->getName()}() is declared to return {$method_return_type}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        /*
        // a return from within a trait context is meaningless
        if($current_class['flags'] & \ast\flags\CLASS_TRAIT) break;
        // Check if there is a return type on the current function
        if(!empty($current_function['oret'])) {
            $ret = $ast->children[0];
            if($ret instanceof \ast\Node) {
                #	if($ast->children[0]->kind == \ast\AST_ARRAY) $ret_type='array';
                #	else $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
                $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
            } else {
                $ret_type = type_map(gettype($ret));
                // This is distinct from returning actual NULL which doesn't hit this else since it is an AST_CONST node
                if($ret_type=='null') $ret_type='void';
            }
            $check_type = $current_function['oret'];
            if(strpos("|$check_type|",'|self|')!==false) {
                $check_type = preg_replace("/\bself\b/", $current_class['name'], $check_type);
            }
            if(strpos("|$check_type|",'|static|')!==false) {
                $check_type = preg_replace("/\bstatic\b/", $current_class['name'], $check_type);
            }
            if(strpos("|$check_type|",'|\$this|')!==false) {
                $check_type = preg_replace("/\b\$this\b/", $current_class['name'], $check_type);
            }
            if(!type_check(all_types($ret_type), all_types($check_type), $namespace)) {
                Log::err(Log::ETYPE, "return $ret_type but {$current_function['name']}() is declared to return {$current_function['oret']}", $file, $ast->lineno);
            }
        } else {
            $lcs = strtolower($current_scope);
            $type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class);
            if(!empty($functions[$lcs]['oret'])) { // The function has a return type declared
                if(!type_check(all_types($type), all_types($functions[$lcs]['oret']), $namespace)) {
                    Log::err(Log::ETYPE, "return $type but {$functions[$lcs]['name']}() is declared to return {$functions[$lcs]['oret']}", $file, $ast->lineno);
                }
            } else {
                if(strpos($current_scope, '::') !== false) {
                    list($class_name,$method_name) = explode('::',$current_scope,2);
                    $idx = find_method_class($class_name, $method_name);
                    if($idx) {
                        $classes[$idx]['methods'][strtolower($method_name)]['ret'] = merge_type($classes[$idx]['methods'][strtolower($method_name)]['ret'], strtolower($type));
                    }
                } else {
                    if(!empty($functions[$lcs]['ret'])) {
                        $functions[$lcs]['ret'] = merge_type($functions[$lcs]['ret'], $type);
                    } else {
                        if($current_scope != 'global') {
                            $functions[$lcs]['ret'] = $type;
                        }
                    }
                }
            }
        }
         */

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
    public function visitClassConstDecl(Node $node) : Context {
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
    public function visitPropDecl(Node $node) : Context {
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
    public function visitCall(Node $node) : Context {
        $expression = $node->children['expr'];

        if($expression->kind == \ast\AST_NAME) {
            $function_name = $expression->children['name'];

            $function_fqsen =
                $this->context->getScopeFQSEN()->withFunctionName(
                    $this->context,
                    $function_name
                );

            if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
                $function_fqsen
            )) {
                Log::err(
                    Log::EUNDEF,
                    "call to undefined function {$function_name}()",
                    $this->context->getFile(),
                    $node->lineno
                );

                return $this->context;
            }

            $method = $this->context->getCodeBase()->getMethodByFQSEN(
                $function_fqsen
            );

            /*
            if (!$this->context->isInternal()) {
                // re-check the function's ast with these args
                if(!$quick_mode) {
                    pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                }
            } else {
                if(!$found) {
                    Log::err(
                        Log::EAVAIL,
                        "function {$function_name}() is not compiled into this version of PHP",
                        $this->context->getFile(),
                        $node->lineno
                    );
                }
            }
             */

            // Iterate through the arguments looking for arguments
            // that are not defined in this scope. If the method
            // takes a pass-by-reference parameter, then we add
            // the variable to the scope.
            $arguments = $node->children['args'];
            foreach ($arguments->children as $i => $argument) {
                // Look for variables passed as arguments
                if ($argument instanceof Node
                    && $argument->kind === \ast\AST_VAR
                ) {
                    $parameter = $method->getParameterList()[$i] ?? null;

                    // Check to see if the parameter at this
                    // position is pass-by-reference.
                    if (!$parameter || !$parameter->isPassByReference()) {
                        continue;
                    }

                    $variable_name =
                        AST::variableName($argument);

                    // Check to see if the variable is not yet defined
                    if (!$this->context->getScope()->hasVariableWithName(
                        $variable_name
                    )) {
                        $variable = Variable::fromNodeInContext(
                            $argument,
                            $this->context,
                            false
                        );

                        // Set the element type on each element of
                        // the list
                        $variable->setUnionType(
                            $parameter->getUnionType()
                        );

                        // Note that we're not creating a new scope, just
                        // adding variables to the existing scope
                        $this->context->addScopeVariable($variable);
                    }
                }
            }

            // Check the arguments and make sure they're cool.
            self::analyzeArgumentType($method, $node, $this->context);

        } else if ($expression->kind == \ast\AST_VAR) {
            $name = AST::variableName($expression);
            if(!empty($name)) {
                // $var() - hopefully a closure, otherwise we don't know
                if ($this->context->getScope()->hasVariableWithName(
                    $name
                )) {
                    $variable = $this->context->getScope()
                        ->getVariableWithName($name);

                    // TODO
                    /*
                    if(($pos=strpos($scope[$current_scope]['vars'][$name]['type'], '{closure '))!==false) {
                        $closure_id = (int)substr($scope[$current_scope]['vars'][$name]['type'], $pos+9);
                        $func_name = '{closure '.$closure_id.'}';
                        $found = $functions[$func_name];
                        arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
                        if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                    }
                     */
                }
            }
        }


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
    public function visitNew(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
        if($class_name) {
            $method_name = '__construct';  // No type checking for PHP4-style constructors
            $method = find_method($class_name, $method_name);
            if($method) { // Found a constructor
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
         */

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
    public function visitInstanceof(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
         */

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
    public function visitStaticCall(Node $node) : Context {

        $class_name = AST::classNameFromNode(
            $this->context, $node
        );

        if(!$class_name) {
            return $this->context;
        }

        // The class is declared, but does it have the method?
        $method_name = $node->children['method'];

        // Give up on things like Class::$var
        if (!is_string($method_name)) {
            return $this->context;
        }

        // Get the name of the static class being referenced
        $static_class = '';
        if($node->children['class']->kind == \ast\AST_NAME) {
            $static_class = $node->children['class']->children['name'];
        }

        if ($method_name === '__construct') {
            if ($static_class !== 'parent') {
                Log::err(
                    Log::EUNDEF,
                    "static call to undeclared method {$static_class}::{$method_name}()",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

            return $this->context;
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context, $class_name
            );

        // Check to see if the class actually exists
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


        if (!$clazz->hasMethodWithName($method_name)) {
            Log::err(
                Log::EUNDEF,
                "static call to undeclared method {$class_fqsen}::{$method_name}()",
                $this->context->getFile(),
                $node->lineno
            );

            return $this->context;
        }

        $method = $clazz->getMethodByName($method_name);

        /*
        if(array_key_exists('avail', $method) && !$method['avail']) {
            Log::err(
                Log::EAVAIL,
                "method {$class_name}::{$method_name}() is not compiled into this version of PHP",
                $file,
                $ast->lineno
            );
        }
        */

        // TODO: wha?
        // else if($method != 'dynamic') {

        // Was it declared static?
        if(!$method->isStatic() && 'parent' !== $static_class) {
            Log::err(
                Log::ESTATIC,
                "static call to non-static method {$class_fqsen}::{$method_name}() defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                $this->context->getFile(),
                $node->lineno
            );
        }

        // Confirm the arguments are clean
        ArgumentType::analyzeArgumentType($method, $node, $this->context);

        /*
        // re-check the function's ast with these args
        if (!$method->getContext()->isInternal()) {
            if(!$quick_mode) {
                pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
            }
        }
         */

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
    public function visitMethodCall(Node $node) : Context {
        // Find out the name of the class for which we're
        // calling a method
        $class_name =
            AST::classNameFromNode($this->context, $node);

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (empty($class_name)) {
            return $this->context;
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context, $class_name
            );

        /*
        // Ensure that we're not getting native types here
        assert(!Type::fromFullyQualifiedString((string)$class_fqsen)->isNativeType(),
            "Cannot call methods on native type $class_fqsen in {$this->context}");
         */

        // Check to see if the class actually exists
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
                "call to undeclared method $class_fqsen->$method_name()",
                $this->context->getFile(),
                $node->lineno
            );

            return $this->context;
        }

        $method = $clazz->getMethodByName($method_name);

        self::analyzeArgumentType($method, $node, $this->context);


        // TODO: whats this?
        /*
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

            self::analyzeArgumentType($method, $node, $this->context);

            // if($method->getContext()->getFile() != 'internal') {
            //     // re-check the function's ast with these args
            //     if(!$quick_mode) {
            //         pass2(
            //             $method['file'],
            //             $method['namespace'],
            //             $method['ast'],
            //             $method['scope'],
            //             $ast,
            //             $classes[strtolower($class_name)],
            //             $method,
            //             $parent_scope
            //         );
            //     }
            // }
        }
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to check to see if its a no-op
     *
     * @param string $message
     * A message to emit if its a no-op
     *
     * @return null
     */
    private function checkNoOp(Node $node, string $message) {
        if($this->parent_node instanceof Node &&
            $this->parent_node->kind == \ast\AST_STMT_LIST
        ) {
            Log::err(
                Log::ENOOP,
                $message,
                $this->context->getFile(),
                $node->lineno
            );
        }

    }

}
