<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Config;
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
    Parameter,
    Property,
    Variable
};
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\Type;
use \Phan\Language\Scope;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new DepthFirstVisitor($context)
 *     );
 * ```
 */
class DepthFirstVisitor extends ScopeVisitor {

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(Context $context, CodeBase $code_base) {
        parent::__construct($context, $code_base);
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

        if ($node->flags & \ast\flags\CLASS_ANONYMOUS) {
            $class_name =
                AST::unqualifiedNameForAnonymousClassNode(
                    $node,
                    $this->context
                );
        } else {
            $class_name = $node->name;
        }

        assert(!empty($class_name), "Class name cannot be empty");

        $alternate_id = 0;

        // Hunt for the alternate of this class defined
        // in this file
        do {
            $class_fqsen =
                FullyQualifiedClassName::fromStringInContext(
                    $class_name,
                    $this->context
                )->withAlternateId($alternate_id++);

            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                Log::err(
                    Log::EFATAL,
                    "Can't find class {$class_fqsen} - aborting",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

            $clazz = $this->code_base->getClassByFQSEN(
                $class_fqsen
            );

        } while(
            $this->context->getFile() != $clazz->getContext()->getFile()
            || $this->context->getLineNumberStart() != $clazz->getContext()->getLineNumberStart()
        );

        return $clazz->getContext()->withClassFQSEN(
            $class_fqsen
        );
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD`
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
        $clazz = $this->getContextClass();

        if (!$clazz->hasMethodWithName(
            $this->code_base,
            $method_name
        )) {
            Log::err(
                Log::EFATAL,
                "Can't find method {$clazz->getFQSEN()}::$method_name() - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $method = $clazz->getMethodByNameInContext(
            $this->code_base,
            $method_name,
            $this->context
        );

        return $method->getContext()->withMethodFQSEN(
            $method->getFQSEN()
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

        try {
            $method = AST::functionFromNameInContext(
                $function_name, $this->context, $this->code_base, true
            );
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EFATAL,
                $exception->getMessage(),
                $this->context->getFile(),
                $node->lineno
            );

            return $this->context;
        }

        // Hunt for the alternate associated with the file we're
        // looking at currently in this context.
        foreach ($method->alternateGenerator($this->code_base)
            as $i => $alternate_method
        ) {
            if ($alternate_method->getContext()->getFile()
                === $this->context->getFile()
            ) {
                return $method->getContext()->withMethodFQSEN(
                    $alternate_method->getFQSEN()
                );
            }
        }

        // No alternate was found
        Log::err(
            Log::EFATAL,
            "Can't find function {$function_name} - aborting",
            $this->context->getFile(),
            $node->lineno
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
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context
            );

        $method = Method::fromNode(
            $this->context,
            $this->code_base,
            $node
        );

        // Override the FQSEN with the found alternate ID
        $method->setFQSEN($closure_fqsen);

        // Make the closure reachable by FQSEN from anywhere
        $this->code_base->addMethod($method);

        // If we have a 'this' variable in our current scope,
        // pass it down into the closure
        $context = $this->context->withScope(new Scope());
        if ($context->getScope()->hasVariableWithName('this')) {
            $context = $context->addScopeVariable(
                $this->context->getScope()->getVariableWithName('this')
            );
        }

        if(!empty($node->children['uses'])
            && $node->children['uses']->kind == \ast\AST_CLOSURE_USES
        ) {
            $uses = $node->children['uses'];
            foreach($uses->children as $use) {
                if($use->kind != \ast\AST_CLOSURE_VAR) {
                    Log::err(
                        Log::EVAR,
                        "You can only have variables in a closure use() clause",
                        $this->context->getFile(),
                        $node->lineno
                    );

                    continue;
                }

                $variable_name =
                    AST::variableName($use->children['name']);

                if(empty($variable_name)) {
                    continue;
                }

                $variable = null;

                // Check to see if the variable exists in this scope
                if (!$this->context->getScope()->hasVariableWithName(
                    $variable_name
                )) {
                    // If this is not pass-by-reference variable we
                    // have a problem
                    if (!($use->flags & \ast\flags\PARAM_REF)) {
                        Log::err(
                            Log::EVAR,
                            "Variable \${$variable_name} is not defined",
                            $this->context->getFile(),
                            $node->lineno
                        );

                        continue;
                    } else {
                        // If the variable doesn't exist, but its
                        // a pass-by-reference variable, we can
                        // just create it
                        $variable = Variable::fromNodeInContext(
                            $use,
                            $this->context,
                            $this->code_base,
                            false
                        );
                    }
                } else {
                    $variable =
                        $this->context->getScope()->getVariableWithName(
                            $variable_name
                        );

                    // If this isn't a pass-by-reference variable, we
                    // clone the variable so state within this scope
                    // doesn't update the outer scope
                    if (!($use->flags & \ast\flags\PARAM_REF)) {
                        $variable = clone($variable);
                    }
                }

                // Pass the variable into a new scope
                $context = $context->withScopeVariable($variable);
            }
        }

        // Add all parameters to the scope
        if (!empty($node->children['params'])
            && $node->children['params']->kind == \ast\AST_PARAM_LIST
        ) {
            $params = $node->children['params'];
            foreach ($params->children as $param) {
                // Read the parameter
                $parameter = Parameter::fromNode(
                    $this->context,
                    $this->code_base,
                    $param
                );

                // Add it to the scope
                $context = $context->withScopeVariable($parameter);
            }
        }

        return $context->withClosureFQSEN($closure_fqsen);
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
                $variable =
                    Variable::fromNodeInContext(
                        $child_node,
                        $this->context,
                        $this->code_base,
                        false
                    );

                $this->context->addScopeVariable($variable);
            }

        // Otherwise, read the value as regular variable and
        // add it to the scope
        } else {
            // Create a variable for the value
            $variable = Variable::fromNodeInContext(
                $node->children['value'],
                $this->context,
                $this->code_base,
                false
            );

            // Get the type of the node from the left side
            $type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $node->children['expr']
            );

            // Filter out the non-generic types of the
            // expression
            $non_generic_type = $type->genericArrayElementTypes();

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
                $this->code_base,
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

        // Get the name of the class
        $class_name = $node->children['class']->children['name'];

        $clazz = null;

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (!empty($class_name)) {
            $class_fqsen =
                FullyQualifiedClassName::fromStringInContext(
                    $class_name,
                    $this->context
                );

            // Check to see if the class actually exists
            if ($this->code_base->hasClassWithFQSEN($class_fqsen)) {
                $clazz = $this->code_base->getClassByFQSEN(
                    $class_fqsen
                );
            } else {
                Log::err(
                    Log::EUNDEF,
                    "call to method on undeclared class $class_name",
                    $this->context->getFile(),
                    $node->lineno
                );
            }

        }

        $variable_name =
            AST::variableName($node->children['var']);

        if (!empty($variable_name)) {
            $variable = Variable::fromNodeInContext(
                $node->children['var'],
                $this->context,
                $this->code_base,
                false
            );

            if ($clazz) {
                $variable->setUnionType($clazz->getUnionType());
            }

            $this->context->addScopeVariable($variable);
        }

        return $this->context;
    }

    /**
     * @return Clazz
     * Get the class on this scope or fail real hard
     */
    private function getContextClass() : Clazz {
        return $this->context->getClassInScope($this->code_base);
    }
}
