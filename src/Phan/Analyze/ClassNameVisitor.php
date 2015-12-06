<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Exception\AccessException;
use \Phan\Language\AST;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A visitor that can extract a class name from a few
 * types of nodes
 */
class ClassNameVisitor extends KindVisitorImplementation {

    /**
     * @var Context
     * The context of the current execution
     */
    private $context;

    /**
     * @var CodeBase
     */
    private $code_basae;

    /**
     * @param Context $context
     * The context of the current execution
     *
     * @param CodeBase $code_base
     */
    public function __construct(Context $context, CodeBase $code_base) {
        $this->context = $context;
        $this->code_base = $code_base;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visit(Node $node) : string {
        if (isset($node->children['class'])) {
            return $this->visitNew($node);
        }

        return '';
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitNew(Node $node) : string {

        // Things of the form `new $class_name();`
        if ($node->children['class']->kind == \ast\AST_VAR) {
            return '';
        }

        // Things of the form `new $method->name()`
        if($node->children['class']->kind !== \ast\AST_NAME) {
            return '';
        }

        $class_name =
            $node->children['class']->children['name'];

        if(!in_array($class_name, ['self', 'static', 'parent'])) {
            return AST::qualifiedName(
                $this->context,
                $node->children['class']
            );
        }

        if (!$this->context->isInClassScope()) {
            Log::err(
                Log::ESTATIC,
                "Cannot access {$class_name}:: when no class scope is active",
                $this->context->getFile(),
                $node->lineno
            );

            return '';
        }

        if($class_name == 'static') {
            return (string)$this->context->getClassFQSEN();
        }

        if($class_name == 'self') {
            if ($this->context->isGlobalScope()) {
                assert(false, "Unimplemented branch is required for {$this->context}");
            } else {
                return (string)$this->context->getClassFQSEN();
            }
        }

        if($class_name == 'parent') {
            $clazz = $this->context->getClassInScope($this->code_base);

            if (!$clazz->hasParentClassFQSEN()) {
                return '';
            }

            return (string)$clazz->getParentClassFQSEN();
        }

        return '';
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitStaticCall(Node $node) : string {
        return $this->visitNew($node);
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitClassConst(Node $node) : string {
        return $this->visitNew($node);
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitInstanceOf(Node $node) : string {
        return $this->visitNew($node);
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitMethodCall(Node $node) : string {
        if($node->children['expr']->kind == \ast\AST_VAR) {
            if(($node->children['expr']->children['name'] instanceof Node)) {
                return '';
            }

            // $var->method()
            if($node->children['expr']->children['name'] == 'this') {
                if(!$this->context->isInClassScope()) {
                    Log::err(
                        Log::ESTATIC,
                        'Using $this when not in object context',
                        $this->context->getFile(),
                        $node->lineno
                    );
                    return '';
                }

                return (string)$this->context->getClassFQSEN();
            }

            $variable_name =
                $node->children['expr']->children['name'];

            if (!$this->context->getScope()->hasVariableWithName(
                $variable_name
            )) {
                // Got lost, couldn't find the variable in the current scope
                // If it really isn't defined, it will be caught by the
                // undefined var error
                return '';
            }

            $variable =
                $this->context->getScope()->getVariableWithName($variable_name);

            // Hack - loop through the possible types of the var and assume
            // first found class is correct
            foreach($variable->getUnionType()->nonGenericTypes()->getTypeList() as $type) {
                $child_class_fqsen =
                    FullyQualifiedClassName::fromStringInContext(
                        (string)$type,
                        $this->context
                    );

                if ($this->code_base->hasClassWithFQSEN($child_class_fqsen)) {
                    return (string)FullyQualifiedClassName::fromStringInContext(
                        (string)$type,
                        $this->context
                    );
                }
            }

            // Could not find name
            return '';
        }

        if($node->children['expr']->kind == \ast\AST_PROP) {
            $prop = $node->children['expr'];

            if(!($prop->children['expr']->kind == \ast\AST_VAR
                && !($prop->children['expr']->children['name'] instanceof Node))
            ) {
                return '';
            }

            // $var->prop->method()
            $var = $prop->children['expr'];
            if($var->children['name'] == 'this') {

                // If we're not in a class scope, 'this' won't work
                if(!$this->context->isInClassScope()) {
                    Log::err(
                        Log::ESTATIC,
                        'Using $this when not in object context',
                        $this->context->getFile(),
                        $node->lineno
                    );

                    return '';
                }

                // Get the class in scope
                $clazz = $this->code_base->getClassByFQSEN(
                    $this->context->getClassFQSEN()
                );

                if($prop->children['prop'] instanceof Node) {
                    // $this->$prop->method() - too dynamic, give up
                    return '';
                }

                $property_name = $prop->children['prop'];

                if ($clazz->hasPropertyWithName(
                    $this->code_base,
                    $property_name
                )) {
                    try {
                        $property = $clazz->getPropertyByNameInContext(
                            $this->code_base,
                            $property_name,
                            $this->context
                        );
                    } catch (AccessException $exception) {
                        Log::err(
                            Log::EACCESS,
                            $exception->getMessage(),
                            $this->context->getFile(),
                            $node->lineno
                        );

                        return '';
                    }

                    // Find the first viable property type
                    foreach ($property->getUnionType()->nonGenericTypes()->getTypeList() as $type) {
                        $class_fqsen =
                            FullyQualifiedClassName::fromStringInContext(
                                (string)$type,
                                $this->context
                            );

                        if ($this->code_base->hasClassWithFQSEN($class_fqsen)) {
                            return (string)$class_fqsen;
                        }
                    }
                }

                // No such property was found, or none were classes
                // that could be found
                return '';
            }

            return '';
        }

        if ($node->children['expr']->kind == \ast\AST_METHOD_CALL) {
            // Get the type returned by the first method
            // call.
            $union_type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $node->children['expr']
            );

            // Find the subset of types that are viable
            // classes
            $viable_class_types = $union_type
                ->nonNativeTypes()
                ->nonGenericTypes();

            // If there are no non-native types, give up
            if ($viable_class_types->isEmpty()) {
                return '';
            }

            // Return the first non-native type in the
            // list and hope its a class
            return (string)$viable_class_types->head();
        }

        return '';
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitProp(Node $node) : string {
        return $this->visitMethodCall($node);
    }

}
