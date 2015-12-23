<?php declare(strict_types=1);
namespace Phan\AST;

use \Phan\AST\UnionTypeVisitor;
use \Phan\Analyze\ClassNameVisitor;
use \Phan\Analyze\ClassName\ValidationVisitor as ClassNameValidationVisitor;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\NodeException;
use \Phan\Exception\TypeException;
use \Phan\Exception\UnanalyzableException;
use \Phan\Language\Context;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Constant;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Property;
use \Phan\Language\Element\Variable;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\FQSEN\FullyQualifiedPropertyName;
use \Phan\Language\Type\MixedType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * Methods for an AST node in context
 */
class ContextNode {

    /** @var CodeBase */
    private $code_base;

    /** @var Context */
    private $context;

    /** @var Node|string */
    private $node;

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param Node|string $node
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        $node
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->node = $node;
    }

    /**
     * @param bool $validate_class_name
     * If true, we'll validate that the name of the class
     * is valid.
     *
     * @return string
     * The class name associated with nodes of various types
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     */
    public function getClassName(
        bool $validate_class_name = true
    ) : string {

        if (!($this->node instanceof Node)) {
            print $this->node . "\n";
        } 

        // Extract the class name
        $class_name = (new ClassNameVisitor(
            $this->code_base, $this->context
        ))($this->node);

        if (empty($class_name)) {
            return '';
        }

        if (!$validate_class_name) {
            return $class_name;
        }

        // Validate that the class name is correct
        if (!(new ClassNameValidationVisitor(
                $this->context,
                $this->code_base,
                $class_name
            ))($this->node)
        ) {
            return '';
        }

        return $class_name;
    }

    /**
     * Get a list of fully qualified names from a node
     *
     * @return string[]
     */
    public function getQualifiedNameList() : array {
        if(!($this->node instanceof Node)) {
            return [];
        }

        return array_map(function($name_node)  {
            return (new ContextNode(
                $this->code_base,
                $this->context,
                $name_node
            ))->getQualifiedName();
        }, $this->node->children ?? []);
    }

    /**
     * Get a fully qualified name form a node
     *
     * @return string
     */
    public function getQualifiedName(
    ) : string {
        return (string)UnionTypeVisitor::unionTypeFromClassNode(
            $this->code_base,
            $this->context,
            $this->node
        );
    }

    /**
     * @return string
     * A variable name associated with the given node
     */
    public function getVariableName() : string {
        if(!$this->node instanceof \ast\Node) {
            return (string)$this->node;
        }

        $node = $this->node;
        $parent = $node;

        while(($node instanceof \ast\Node)
            && ($node->kind != \ast\AST_VAR)
            && ($node->kind != \ast\AST_STATIC)
            && ($node->kind != \ast\AST_MAGIC_CONST)
        ) {
            $parent = $node;
            $node = array_values($node->children ?? [])[0];
        }

        if(!$node instanceof \ast\Node) {
            return (string)$node;
        }

        if(empty($node->children['name'])) {
            return '';
        }

        if($node->children['name'] instanceof \ast\Node) {
            return '';
        }

        return (string)$node->children['name'];
    }

    /**
     * @return Clazz[]
     * A list of classes representing the non-native types
     * associated with the given node
     *
     * @throws CodeBaseException
     * An exception is thrown if a non-native type does not have
     * an associated class
     */
    public function getClassList() {
        $union_type = UnionTypeVisitor::unionTypeFromClassNode(
            $this->code_base,
            $this->context,
            $this->node
        );

        $class_list = [];

        foreach ($union_type->asClassList($this->code_base)
            as $i => $clazz
        ) {
            $class_list[] = $clazz;
        }

        return $class_list;
    }

    /**
     * @param bool $validate_class_name
     * If true, we'll validate that the name of the class
     * is valid.
     *
     * @return Clazz
     * The class being referenced in the given node in
     * the given context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the referenced
     * class
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     */
    public function getClass(
        bool $validate_class_name = true
    ) : Clazz {
        // Figure out the name of the class
        $class_name = $this->getClassName(
            $validate_class_name
        );

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (empty($class_name)) {
            throw new NodeException($this->node, 'Could not find class name');
        }

        $class_fqsen =
            FullyQualifiedClassName::fromStringInContext(
                $class_name,
                $this->context
            );

        // Check to see if the class actually exists
        if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            throw new CodeBaseException(
                $class_fqsen,
                "Can't find class {$class_fqsen}"
            );
        }

        $class =
            $this->code_base->getClassByFQSEN($class_fqsen);

        return $class;
    }

    /**
     * @param Node|string $method_name_or_node
     * Either then name of the method or a node that
     * produces the name of the method.
     *
     * @param bool $is_static
     * Set to true if this is a static method call
     *
     * @return Method
     * A method with the given name on the class referenced
     * from the given node
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * method
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     */
    public function getMethod(
        $method_name_or_node,
        bool $is_static
    ) : Method {
        $clazz = $this->getClass();

        if ($method_name_or_node instanceof Node) {
            // TODO: The method_name turned out to
            //       be a variable. We'd have to look
            //       that up to figure out what the
            //       string is, but thats a drag.
            throw new NodeException(
                $method_name_or_node,
                "Unexpected method node"
            );
        }

        $method_name = $method_name_or_node;

        assert(is_string($method_name),
            "Method name must be a string. Found non-string at {$this->context}");

        if (!$clazz->hasMethodWithName($this->code_base, $method_name)) {

            $method_fqsen = FullyQualifiedMethodName::make(
                $clazz->getFQSEN(),
                $method_name
            );

            if ($is_static) {
                throw new CodeBaseException(
                    $method_fqsen,
                    "static call to undeclared method $method_fqsen"
                );
            } else {
                throw new CodeBaseException(
                    $method_fqsen,
                    "call to undeclared method $method_fqsen"
                );
            }
        }

        $method = $clazz->getMethodByNameInContext(
            $this->code_base,
            $method_name,
            $this->context
        );

        return $method;
    }

    /**
     * @param string $function_name
     * The name of the function we'd like to look up
     *
     * @param bool $is_function_declaration
     * This must be set to true if we're getting a function
     * that is being declared and false if we're getting a
     * function being called.
     *
     * @return Method
     * A method with the given name in the given context
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * function
     */
    public function getFunction(
        string $function_name,
        bool $is_function_declaration = false
    ) : Method {

        if ($is_function_declaration) {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $this->context->getNamespace(),
                    $function_name
                );
        } else {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $this->context->getNamespace(),
                    $function_name
                );

            // If it doesn't exist in the local namespace, try it
            // in the global namespace
            if (!$this->code_base->hasMethod($function_fqsen)) {
                $function_fqsen =
                    FullyQualifiedFunctionName::fromStringInContext(
                        $function_name,
                        $this->context
                    );
            }

        }

        // Make sure the method we're calling actually exists
        if (!$this->code_base->hasMethod($function_fqsen)) {
            throw new CodeBaseException(
                $function_fqsen,
                "call to undefined function {$function_fqsen}()"
            );
        }

        $method = $this->code_base->getMethod($function_fqsen);

        return $method;
    }

    /**
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     */
    public function getOrCreateVariable() : Variable {

        // Get the name of the variable
        $variable_name = $this->getVariableName();

        if(empty($variable_name)) {
            throw new NodeException($this->node, "Variable name not found");
        }

        // Check to see if the variable exists in this scope
        if ($this->context->getScope()->hasVariableWithName($variable_name)) {
            return $this->context->getScope()->getVariableWithName(
                $variable_name
            );
        }

        // Create a new variable
        $variable = Variable::fromNodeInContext(
            $this->node,
            $this->context,
            $this->code_base,
            false
        );

        $this->context->addScopeVariable($variable);

        return $variable;
    }

    /**
     * @param string|Node $property_name
     * The name of the property we're looking up
     *
     * @return Property 
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws AccessException
     * An exception is thrown if the property is private or
     * protected and we don't have access to it from this
     * context
     *
     * @throws UnanalyzableException
     * An exception is thrown if we hit a construct in which
     * we can't determine if the property exists or not
     */
    public function getProperty(
        $property_name
    ) : Property {

        $property_name = $this->node->children['prop'];

        // Give up for things like C::$prop_name
        if (!is_string($property_name)) {
            throw new NodeException(
                $this->node,
                "Cannot figure out non-string property name"
            );
        }

        $class_fqsen = null;

        $class_list = (new ContextNode(
            $this->code_base,
            $this->context,
            $this->node->children['expr'] ??
                $this->node->children['class']
        ))->getClassList();

        foreach ($class_list as $i => $class) {
            $class_fqsen = $class->getFQSEN();

            // Keep hunting if this class doesn't have the given
            // property
            if (!$class->hasPropertyWithName(
                    $this->code_base,
                    $property_name
            )) {
                // If there's a getter on properties than all
                // bets are off.
                if ($class->hasMethodWithName(
                    $this->code_base, '__get'
                )) {
                    throw new UnanalyzableException(
                        $this->node,
                        "Can't determine if property {$property_name} exists in class {$class->getFQSEN()} with __get defined"
                    );
                }

                continue;
            }

            return $class->getPropertyByNameInContext(
                $this->code_base,
                $property_name,
                $this->context
            );
        }

        // If the class isn't found, we'll get the message elsewhere
        if ($class_fqsen) {
            throw new CodeBaseException(
                $class->getFQSEN(),
                "Can't find property {$property_name} in class {$class_fqsen}"
            );
        }

        throw new NodeException(
            $this->node,
            "Cannot figure out property"
        );
    }

    /**
     * @return Property 
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     */
    public function getOrCreateProperty(
        string $property_name
    ) : Property {

        try {
            return $this->getProperty($property_name);
        } catch (CodeBaseException $exception) {
            // Ignore it, because we'll create our own
            // property
        } catch (UnanalyzableException $exception) {
            // Ignore it, because we'll create our own
            // property
        }

        // Figure out the class we're looking the property
        // up for
        $class = $this->getClass();

        $flags = 0;
        if ($this->node->kind == \ast\AST_STATIC_PROP) {
            $flags |= \ast\flags\MODIFIER_STATIC;
        }

        // Otherwise, we'll create it
        $property = new Property(
            $this->context,
            $property_name,
            new UnionType(),
            $flags
        );

        $property->setFQSEN(
            FullyQualifiedPropertyName::make(
                $class->getFQSEN(),
                $property_name
            )
        );

        $class->addProperty($this->code_base, $property);

        return $property;
    }

    /**
     * @return Constant
     * Get the (non-class) constant associated with this node
     * in this context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     */
    public function getConst() : Constant {
        assert($this->node->kind === \ast\AST_CONST,
            "Node must be of type \ast\AST_CONST");

        if($this->node->children['name']->kind !== \ast\AST_NAME) {
            throw new NodeException(
                $this->node,
                "Can't determine constant name"
            );
        }

        // Get an FQSEN for the root namespace
        $fqsen = null;

        $constant_name =
            $this->node->children['name']->children['name'];

        if (!$this->code_base->hasConstant($fqsen, $constant_name)) {
            throw new CodeBaseException(
                $fqsen,
                "Cannot find constant with name $constant_name"
            );
        }

        return $this->code_base->getConstant($fqsen, $constant_name);
    }

    /**
     * @return Constant
     * Get the (non-class) constant associated with this node
     * in this context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws UnanalyzableException
     * An exception is thrown if we hit a construct in which
     * we can't determine if the property exists or not
     */
    public function getClassConst() : Constant {
        assert($this->node->kind === \ast\AST_CLASS_CONST,
            "Node must be of type \ast\AST_CLASS_CONST");

        $constant_name = $this->node->children['const'];

        // class name fetch
        if($constant_name == 'class') {
            throw new UnanalyzableException(
                $this->node,
                "Can't get class constant for implicit 'class'"
            );
        }

        $class_fqsen = null;

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['class']
            ))->getClassList();
        } catch (CodeBaseException $exception) {
            throw new CodeBaseException(
                $exception->getFQSEN(),
                "Can't access constant $constant_name from undeclared class {$exception->getFQSEN()}"
            );
        }

        foreach ($class_list as $i => $class) {
            $class_fqsen = $class->getFQSEN();

            // Check to see if the class has the constant
            if (!$class->hasConstantWithName(
                $this->code_base,
                $constant_name
            )) {
                continue;
            }

            return $class->getConstantWithName(
                $this->code_base,
                $constant_name
            );
        }

        // If no class is found, we'll emit the error elsewhere
        if ($class_fqsen) {
            throw new CodeBaseException(
                $class->getFQSEN(),
                "Can't access undeclared constant {$class_fqsen}::{$constant_name}"
            );
        }

        throw new NodeException(
            $this->node,
            "Can't figure out constant {$constant_name} in node"
        );
    }

    /**
     * @return string
     * A unique and stable name for an anonymous class
     */
    public function getUnqualifiedNameForAnonymousClass() : string {
        assert((bool)($this->node->flags & \ast\flags\CLASS_ANONYMOUS),
            "Node must be an anonymous class node");

        $class_name = 'anonymous_class_'
            . substr(md5(implode('|', [
                $this->context->getFile(),
                $this->context->getLineNumberStart()
            ])), 0, 8);

        return $class_name;
    }

    /**
     * Perform some backwards compatibility checks on a node
     *
     * @return void
     */
    public function analyzeBackwardCompatibility() {
        if(!Config::get()->backward_compatibility_checks) {
            return;
        }

        if(empty($this->node->children['expr'])) {
            return;
        }

        if($this->node->kind !== \ast\AST_DIM) {
            if(!($this->node->children['expr'] instanceof Node)) {
                return;
            }

            if($this->node->children['expr']->kind !== \ast\AST_DIM) {
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $this->node->children['expr']
                ))->analyzeBackwardCompatibility();
                return;
            }

            $temp = $this->node->children['expr']->children['expr'];
            $lnode = $temp;
        } else {
            $temp = $this->node->children['expr'];
            $lnode = $temp;
        }
        if(!($temp->kind == \ast\AST_PROP
            || $temp->kind == \ast\AST_STATIC_PROP
        )) {
            return;
        }

        while($temp instanceof Node
            && ($temp->kind == \ast\AST_PROP
            || $temp->kind == \ast\AST_STATIC_PROP)
        ) {
            $lnode = $temp;

            // Lets just hope the 0th is the expression
            // we want
            $temp = array_values($temp->children)[0];
        }

        if(!($temp instanceof Node)) {
            return;
        }

        // Foo::$bar['baz'](); is a problem
        // Foo::$bar['baz'] is not
        if($lnode->kind === \ast\AST_STATIC_PROP
            && $this->node->kind !== \ast\AST_CALL
        ) {
            return;
        }

        if((
                (
                    $lnode->children['prop'] instanceof Node
                    && $lnode->children['prop']->kind == \ast\AST_VAR
                )
                ||
                (
                    !empty($lnode->children['class'])
                    && $lnode->children['class'] instanceof Node
                    && (
                        $lnode->children['class']->kind == \ast\AST_VAR
                        || $lnode->children['class']->kind == \ast\AST_NAME
                    )
                )
            )
            &&
            (
                $temp->kind == \ast\AST_VAR
                || $temp->kind == \ast\AST_NAME
            )
        ) {
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($this->node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'}[') === false
                || strpos($line,']}') === false
                || strpos($line,'>{') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "expression may not be PHP 7 compatible",
                    $this->context->getFile(),
                    $this->node->lineno ?? 0
                );
            }
        }
    }
}
