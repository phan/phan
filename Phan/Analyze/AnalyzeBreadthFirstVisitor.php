<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Analyze\Analyzable;
use \Phan\Analyze\AnalyzeAssignmentVisitor;
use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\NodeException;
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
            '\\' . (string)$node->children['name']
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
    public function visitAssign(Node $node) : Context {

        // Get the type of the right side of the
        // assignment
        $right_type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        assert($node->children['var'] instanceof Node,
            "Expected left side of assignment to be a var in {$this->context}");

        $context =
            (new Element($node->children['var']))->acceptKindVisitor(
                new AnalyzeAssignmentVisitor(
                    $this->context,
                    $node,
                    $right_type
                )
            );

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
        $this->analyzeNoOp($node, "no-op variable");
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
        $this->analyzeNoOp($node, "no-op array");
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
        $this->analyzeNoOp($node, "no-op constant");
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
        $this->analyzeNoOp($node, "no-op closure");
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
            try {
                $method = AST::functionFromNameInContext(
                    $expression->children['name'],
                    $this->context
                );
            } catch (CodeBaseException $exception) {
                // TODO: extension methods aren't
                //       available here. Not sure
                //       how we should handle those
                /*
                Log::err(
                    Log::EUNDEF,
                    $exception->getMessage(),
                    $this->context->getFile(),
                    $node->lineno
                );
                 */

                return $this->context;
            }

            // Check the call for paraemter and argument types
            $this->analyzeCallToMethod($method, $node);
        }

        /*
        else if ($expression->kind == \ast\AST_VAR) {
            $name = AST::variableName($expression);
            if(!empty($name)) {
                // $var() - hopefully a closure, otherwise we don't know
                if ($this->context->getScope()->hasVariableWithName(
                    $name
                )) {
                    $variable = $this->context->getScope()
                        ->getVariableWithName($name);


                    // TODO
                    // if(($pos=strpos($scope[$current_scope]['vars'][$name]['type'], '{closure '))!==false) {
                    //     $closure_id = (int)substr($scope[$current_scope]['vars'][$name]['type'], $pos+9);
                    //     $func_name = '{closure '.$closure_id.'}';
                    //     $found = $functions[$func_name];
                    //     arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
                    //     if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                    // }
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
    public function visitNew(Node $node) : Context {
        try {
            $method = AST::classMethodFromNodeInContext(
                $node,
                $this->context,
                '__construct',
                false
            );

            $this->analyzeCallToMethod(
                $method,
                $node
            );

        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                $exception->getMessage(),
                $this->context->getFile(),
                $node->lineno
            );
            return $this->context;
        } catch (NodeException $exception) {
            // If we can't figure out what kind of a call
            // this is, don't worry about it
            return $this->context;
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

        // Get the name of the method being called
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

        // Short circuit on a constructor being called statically
        // on something other than 'parent'
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

        try {
            // Get a reference to the method being called
            $method = AST::classMethodFromNodeInContext(
                $node,
                $this->context,
                $method_name,
                true
            );

            // If the method isn't static and we're not calling
            // it on 'parent', we're in a bad spot.
            if(!$method->isStatic() && 'parent' !== $static_class) {
                $clazz = AST::classFromNodeInContext(
                    $node,
                    $this->context
                );

                Log::err(
                    Log::ESTATIC,
                    "static call to non-static method {$clazz->getFQSEN()}::{$method_name}()"
                    . " defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

            // Make sure the parameters look good
            $this->analyzeCallToMethod(
                $method,
                $node
            );

        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                $exception->getMessage(),
                $this->context->getFile(),
                $node->lineno
            );
            return $this->context;
        } catch (NodeException $exception) {
            // If we can't figure out what kind of a call
            // this is, don't worry about it
            return $this->context;
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
    public function visitMethodCall(Node $node) : Context {
        try {
            $method = AST::classMethodFromNodeInContext(
                $node,
                $this->context,
                $node->children['method'],
                false
            );
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                $exception->getMessage(),
                $this->context->getFile(),
                $node->lineno
            );
            return $this->context;
        } catch (NodeException $exception) {
            // If we can't figure out what kind of a call
            // this is, don't worry about it
            return $this->context;
        }

        // Check the call for paraemter and argument types
        $this->analyzeCallToMethod($method, $node);

        return $this->context;
    }

    /**
     * Analyze the parameters and arguments for a call
     * to the given method or function
     *
     * @param Method $method
     * @param Node $node
     *
     * @return null
     */
    private function analyzeCallToMethod(
        Method $method,
        Node $node
    ) {
        // Create variables for any pass-by-reference
        // parameters
        $argument_list = $node->children['args'];
        foreach ($argument_list->children as $i => $argument) {
            $parameter = $method->getParameterList()[$i] ?? null;

            if (!$parameter) {
                continue;
            }

            // If the parameter is pass-by-reference and we're
            // passing a variable in, see if we should pass
            // the parameter and variable types to eachother
            if ($parameter->isPassByReference()
                && $argument->kind == \ast\AST_VAR
            ) {
                $variable =
                    AST::getOrCreateVariableFromNodeInContext(
                        $argument,
                        $this->context
                    );

                $variable->getUnionType()->addUnionType(
                    $parameter->getUnionType()
                );
            }
        }

        // Confirm the argument types are clean
        self::analyzeArgumentType($method, $node, $this->context);

        // Now that we've made sure the arguments are sufficient
        // for definitions on the method, we iterate over the
        // arguments again and add their types to the parameter
        // types so we can test the method again
        $argument_list = $node->children['args'];
        foreach ($argument_list->children as $i => $argument) {
            $parameter = $method->getParameterList()[$i] ?? null;

            if (!$parameter) {
                continue;
            }

            // If the parameter has no type, pass the
            // argument's type to it
            if ($parameter->getUnionType()->isEmpty()) {
                $argument_type = UnionType::fromNode(
                    $this->context, $argument
                );

                // If this isn't an internal function or method
                // and it has no type, add the argument's type
                // to it so we can compare it to subsequent
                // calls
                if (!$parameter->getContext()->isInternal()) {
                    $parameter->getUnionType()->addUnionType(
                        $argument_type
                    );
                }
            }
        }

        // Now that we know something about the parameters used
        // to call the method, we can reanalyze the method with
        // the types of the parameter, making sure we don't get
        // into an infinite loop of checking calls to the current
        // method in scope
        if (!$method->getContext()->isInternal()
            && (!$this->context->isMethodScope()
                || $method->getFQSEN() !== $this->context->getMethodFQSEN())
        ) {
            $method->analyze($method->getContext());
        }
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
    private function analyzeNoOp(Node $node, string $message) {
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
