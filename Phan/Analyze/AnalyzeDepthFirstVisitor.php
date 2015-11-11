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
    Parameter,
    Property,
    Variable
};
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
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

        $alternate_id = 0;

        // Hunt for the alternate of this class defined
        // in this file
        do {
            $class_fqsen =
                $this->context->getScopeFQSEN()->withClassName(
                    $this->context, $class_name
                )->withAlternateId($alternate_id++);

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

        } while($this->context->getFile()
            != $clazz->getContext()->getFile());

        return $clazz->getContext()->withClassFQSEN(
            $class_fqsen
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
        $clazz = $this->getContextClass();

        if (!$clazz->hasMethodWithName($method_name)) {
            Log::err(
                Log::EFATAL,
                "Can't find method {$clazz->getFQSEN()}::$method_name() - aborting",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $method = $clazz->getMethodByName($method_name);

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

        $alternate_id = 0;

        // Hunt for the alternate of this method defined
        // in this file
        do {
            $function_fqsen =
                $this->context->getScopeFQSEN()->withFunctionName(
                    $this->context, $function_name
                )->withAlternateId($alternate_id++);

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
        } while($this->context->getFile() !=
            $method->getContext()->getFile());

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

        $method =
            Method::fromNode($this->context, $node);

        // Override the FQSEN with the found alternate ID
        $method->setFQSEN($closure_fqsen);

        // TODO: We don't need this, right?
        // $this->context->getCodeBase()->addClosure($method);

        // If we have a 'this' variable in our current scope,
        // pass it down into the closure
        $context = $this->context;
        if ($context->getScope()->hasVariableWithName('this')) {
            $context = $context->withScopeVariable(
                $context->getScope()->getVariableWithName('this')
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
                    self::astVariableName($use->children['name']);

                if(empty($variable_name)) {
                    continue;
                }

                if (!$this->context->getScope()->hasVariableWithName(
                    $variable_name
                )) {
                    Log::err(
                        Log::EVAR,
                        "Variable \${$variable_name} is not defined",
                        $this->context->getFile(),
                        $node->lineno
                    );
                    continue;
                }

                $variable =
                    $this->context->getScope()->getVariableWithName(
                        $variable_name
                    );

                // If its a reference variable, we pass the variable
                // directly into the closure's scope
                if($use->flags & \ast\flags\PARAM_REF) {
                    $context =
                        $context->withScopeVariable($variable);

                // If its not a reference, we pass a copy into the
                // closure's scope
                } else {
                    $context =
                        $context->withScopeVariable(clone($variable));
                }
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
        $variable_name =
            self::astVariableName($node->children['var']);

        if (!empty($variable_name)) {
            $this->context->addScopeVariable(
                Variable::fromNodeInContext(
                    $node->children['var'],
                    $this->context,
                    false
                )
            );
        }

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
        // Find out the name of the class for which we're
        // calling a method
        $class_name =
            self::astClassNameFromNode($this->context, $node);

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (empty($class_name)) {
            return $this->context;
        }

        $class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context, $class_name
            );

        // Ensure that we're not getting native types here
        assert(!Type::fromFullyQualifiedString((string)$class_fqsen)
                ->isNativeType(),
            "Cannot call methods on native type $class_fqsen in {$this->context}");

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
