<?php declare(strict_types=1);
namespace Phan\Analyze\ClassName;

use \Phan\Analyze\ClassNameVisitor;
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
class MethodCallVisitor extends KindVisitorImplementation {

    /**
     * @var Context
     * The context of the current execution
     */
    private $context;

    /**
     * @var CodeBase
     */
    private $code_base;

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
        return $this->visitMethodCall($node);
    }

    /**
     * This is things like j
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitDim(Node $node) : string {
        // Return the class name of the expression behind the dim
        return (new Element($node->children['expr']))->acceptKindVisitor(
            new MethodCallVisitor(
                $this->context,
                $this->code_base
            )
        );
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
        return (new Element($node))->acceptKindVisitor(
            new ClassNameVisitor(
                $this->context,
                $this->code_base
            )
        );
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitVar(Node $node) : string {
        // $$var->method()
        if(($node->children['name'] instanceof Node)) {
            return '';
        }

        // $this->method()
        if($node->children['name'] == 'this') {
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

        $variable_name = $node->children['name'];

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
        foreach($variable->getUnionType()->nonGenericArrayTypes()->getTypeList() as $type) {
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

        // We land here if we have a variable
        // with a native type or no known type.
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
        if(!($node->children['expr']->kind == \ast\AST_VAR
            && !($node->children['expr']->children['name'] instanceof Node))
        ) {
            return '';
        }

        // $var->prop->method()
        $var = $node->children['expr'];

        $class = null;
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

            // $this->$node->method()
            if($node->children['prop'] instanceof Node) {
                // Too hard. Giving up.
                return '';
            }

            $class = $this->context->getClassInScope(
                $this->code_base
            );

        } else {
            // Get the list of viable class types for the
            // variable
            $union_type =
                AST::varUnionType($this->context, $var)
                    ->nonNativeTypes()
                    ->nonGenericArrayTypes();

            if ($union_type->isEmpty()) {
                return '';
            }

            $class_fqsen = $union_type->head()->asFQSEN();

            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                return '';
            }

            $class = $this->code_base->getClassByFQSEN(
                $class_fqsen
            );
        }

        $property_name = $node->children['prop'];

        if (!is_string($property_name)) {
            // This'll happen for things like `$foo->{'prop_' . $name}`.
            return '';
        }

        assert(is_string($property_name),
            "Property name should be a string.");

        if (!$class->hasPropertyWithName(
            $this->code_base,
            $property_name
        )) {
            // If we can't find the property, there's
            // no type. Thie issue should be caught
            // elsewhere.
            return '';
        }

        try {
            $property = $class->getPropertyByNameInContext(
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

        $union_type = $property->getUnionType()
            ->nonNativeTypes();

        if ($union_type->isEmpty()) {
            // If we don't have a type on the property we
            // can't figure out the class type.
            return '';
        } else {
            // Return the first type on the property
            // that could be a reference to a class
            return (string)$union_type->head()->asFQSEN();
        }

        // No such property was found, or none were classes
        // that could be found
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
    public function visitMethodCall(Node $node) : string {
        // Get the type returned by the first method
        // call.
        $union_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node
        );

        // Find the subset of types that are viable
        // classes
        $viable_class_types = $union_type
            ->nonNativeTypes()
            ->nonGenericArrayTypes();

        // If there are no non-native types, give up
        if ($viable_class_types->isEmpty()) {
            return '';
        }

        // Return the first non-native type in the
        // list and hope its a class
        return (string)$viable_class_types->head();
    }

}
