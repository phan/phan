<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\AST\UnionTypeVisitor;
use \Phan\Analyze\ClassNameVisitor;
use \Phan\Analyze\ClassName\ValidationVisitor as ClassNameValidationVisitor;
use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\NodeException;
use \Phan\Exception\TypeException;
use \Phan\Language\AST\Element;
use \Phan\Language\Element\Clazz;
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
 * A set of methods for extracting details from AST nodes.
 */
class AST {

    /**
     * @param Context $context
     * @param null|string|Node $node
     *
     * @param CodeBase $code_base
     *
     * @param bool $validate_class_name
     * If true, we'll validate that the name of the class
     * is valid.
     *
     * @return string
     * The class name associated with nodes of various types
     *
     * @see \Phan\Deprecated\Util::find_class_name
     * Formerly `function find_class_name`
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     */
    public static function classNameFromNode(
        Context $context,
        CodeBase $code_base,
        Node $node,
        bool $validate_class_name = true
    ) : string {
        // Extract the class name
        $class_name = (new Element($node))->acceptKindVisitor(
            new ClassNameVisitor($context, $code_base)
        );

        if (empty($class_name)) {
            return '';
        }

        if (!$validate_class_name) {
            return $class_name;
        }

        // Validate that the class name is correct
        if (!(new Element($node))->acceptKindVisitor(
            new ClassNameValidationVisitor(
                $context,
                $code_base,
                $class_name
            )
        )) {
            return '';
        }

        return $class_name;
    }

    /**
     * Get a list of fully qualified names from a node
     *
     * @return string[]
     */
    public static function qualifiedNameList(
        CodeBase $code_base,
        Context $context,
        $node
    ) : array {
        if(!($node instanceof Node)) {
            return [];
        }

        return array_map(function($name_node) use ($code_base, $context) {
            return self::qualifiedName($code_base, $context, $name_node);
        }, $node->children);
    }

    /**
     * Get a fully qualified name form a node
     *
     * @return string
     */
    public static function qualifiedName(
        CodeBase $code_base,
        Context $context,
        $node
    ) : string {
        return (string)UnionTypeVisitor::unionTypeFromClassNode(
            $code_base,
            $context,
            $node
        );
    }

    /**
     * Takes an AST_VAR node and tries to find the variable in
     * the current scope and returns its likely type. For
     * pass-by-ref args, we suppress the not defined error message
     *
     * @param Context $context
     * @param null|string\Node $node
     *
     * @param Node $node
     * The node to get a union type for
     *
     * @return UnionType
     *
     * @see \Phan\Deprecated\Pass2::var_type
     * From `function var_type`
     *
     * @deprecated
     */
    public static function varUnionType(
        Context $context,
        Node $node
    ) : UnionType {

        // Check for $$var or ${...} (whose idea was that anyway?)
        if(($node->children['name'] instanceof Node)
            && ($node->children['name']->kind == \ast\AST_VAR
                || $node->children['name']->kind == \ast\AST_BINARY_OP)
        ) {
            return MixedType::instance()->asUnionType();
        }

        // This is nonsense. Give up.
        if($node->children['name'] instanceof Node) {
            return new UnionType();
        }

        $variable_name = $node->children['name'];

        if (!$context->getScope()->hasVariableWithName($variable_name)
        ) {
            if(!Variable::isSuperglobalVariableWithName($variable_name)) {
                Log::err(
                    Log::EVAR,
                    "Variable \$$variable_name is not defined",
                    $context->getFile(),
                    $node->lineno ?? 0
                );
            }
        } else {
            $variable =
                $context->getScope()->getVariableWithName($variable_name);

            return $variable->getUnionType();
        }

        return new UnionType();
    }

    /**
     * @param mixed|Node $node
     *
     * @return string
     * A variable name associated with the given node
     */
    public static function variableName($node) : string {
        if(!$node instanceof \ast\Node) {
            return (string)$node;
        }

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
     * Perform some backwards compatibility checks on a node
     *
     * @param Context $context
     * The context in which the node appears
     *
     * @param Node $node
     * The node we'd like to check
     *
     * @return null
     *
     * @see \Phan\Deprecated::bc_check
     * Formerly `function bc_check`
     */
    public static function backwardCompatibilityCheck(
        Context $context,
        Node $node
    ) {
        if(empty($node->children['expr'])) {
            return;
        }

        if($node->kind !== \ast\AST_DIM) {
            if(!($node->children['expr'] instanceof Node)) {
                return;
            }

            if($node->children['expr']->kind !== \ast\AST_DIM) {
                AST::backwardCompatibilityCheck($context, $node->children['expr']);
                return;
            }

            $temp = $node->children['expr']->children['expr'];
            $lnode = $temp;
        } else {
            $temp = $node->children['expr'];
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
        if($lnode->kind === \ast\AST_STATIC_PROP && $node->kind !== \ast\AST_CALL) {
            return;
        }

        if(
           (($lnode->children['prop'] instanceof Node && $lnode->children['prop']->kind == \ast\AST_VAR) ||
            (!empty($lnode->children['class']) && $lnode->children['class'] instanceof Node && ($lnode->children['class']->kind == \ast\AST_VAR || $lnode->children['class']->kind == \ast\AST_NAME))) &&
                ($temp->kind == \ast\AST_VAR || $temp->kind == \ast\AST_NAME)
        ) {
            $ftemp = new \SplFileObject($context->getFile());
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'}[') === false
                || strpos($line,']}') === false
                || strpos($line,'>{') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "expression may not be PHP 7 compatible",
                    $context->getFile(),
                    $node->lineno ?? 0
                );
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which we're looking for classes
     *
     * @param Context $context
     * The context in which the node exists
     *
     * @param Node $node
     * The node we want to get a type for and classes from
     * the types
     *
     * @return Clazz[]
     * A list of classes representing the non-native types
     * associated with the given node
     *
     * @throws CodeBaseException
     * An exception is thrown if a non-native type does not have
     * an associated class
     */
    public static function classListFromNodeInContext(
        CodeBase $code_base,
        Context $context,
        $node
    ) {
        $union_type = UnionTypeVisitor::unionTypeFromClassNode(
            $code_base,
            $context,
            $node
        );

        $class_list = [];

        foreach ($union_type->asClassList($code_base)
            as $i => $clazz
        ) {
            $class_list[] = $clazz;
        }

        return $class_list;
    }

    /**
     * @param Node $node
     * The node that has a reference to a class
     *
     * @param Context $context
     * The context in which we found the node
     *
     * @param CodeBase $code_base
     * The global code base holding all state
     *
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
    public static function classFromNodeInContext(
        Node $node,
        Context $context,
        CodeBase $code_base,
        bool $validate_class_name = true
    ) : Clazz {
        // Figure out the name of the class
        $class_name = self::classNameFromNode(
            $context,
            $code_base,
            $node,
            $validate_class_name
        );

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (empty($class_name)) {
            throw new NodeException($node, 'Could not find class name');
        }

        $class_fqsen =
            FullyQualifiedClassName::fromStringInContext(
                $class_name,
                $context
            );

        // Check to see if the class actually exists
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            throw new CodeBaseException(
                $class_fqsen,
                "Can't find class {$class_fqsen}"
            );
        }

        $class =
            $code_base->getClassByFQSEN($class_fqsen);

        return $class;
    }

    /**
     * @param Node $node
     * The node that has a reference to a class
     *
     * @param Context $context
     * The context in which we found the node
     *
     * @param CodeBase $code_base
     *
     * @param Node|string $method_name_or_node
     * Either then name of the method or a node that
     * produces the name of the method.
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
    public static function classMethodFromNodeInContext(
        Node $node,
        Context $context,
        CodeBase $code_base,
        $method_name_or_node,
        bool $is_static
    ) : Method {
        $clazz = self::classFromNodeInContext(
            $node,
            $context,
            $code_base
        );

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
            "Method name must be a string. Found non-string at {$context}");

        if (!$clazz->hasMethodWithName($code_base, $method_name)) {

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
            $code_base,
            $method_name,
            $context
        );

        return $method;
    }

    /**
     * @param string $function_name
     * The name of the function we'd like to look up
     *
     * @param Context $context
     * The context in which we found the reference to the
     * given function name
     *
     * @param CodeBase $code_base
     * The global code base holding all state
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
    public static function functionFromNameInContext(
        string $function_name,
        Context $context,
        CodeBase $code_base,
        bool $is_function_declaration = false
    ) : Method {

        if ($is_function_declaration) {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $context->getNamespace(),
                    $function_name
                );
        } else {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $context->getNamespace(),
                    $function_name
                );

            // If it doesn't exist in the local namespace, try it
            // in the global namespace
            if (!$code_base->hasMethod($function_fqsen)) {
                $function_fqsen =
                    FullyQualifiedFunctionName::fromStringInContext(
                        $function_name,
                        $context
                    );
            }

        }

        // Make sure the method we're calling actually exists
        if (!$code_base->hasMethod($function_fqsen)) {
            throw new CodeBaseException(
                $function_fqsen,
                "call to undefined function {$function_fqsen}()"
            );
        }

        $method = $code_base->getMethod($function_fqsen);

        return $method;
    }

    /**
     * @param Node $node
     * A node that has a reference to a variable
     *
     * @param Context $context
     * The context in which we found the reference
     *
     * @param CodeBase $code_base
     *
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     */
    public static function getOrCreateVariableFromNodeInContext(
        Node $node,
        Context $context,
        CodeBase $code_base
    ) : Variable {

        // Get the name of the variable
        $variable_name = self::variableName($node);

        if(empty($variable_name)) {
            throw new NodeException($node, "Variable name not found");
        }

        // Check to see if the variable exists in this scope
        if ($context->getScope()->hasVariableWithName($variable_name)) {
            return $context->getScope()->getVariableWithName(
                $variable_name
            );
        }

        // Create a new variable
        $variable = Variable::fromNodeInContext(
            $node, $context, $code_base, false
        );

        $context->addScopeVariable($variable);

        return $variable;
    }

    /**
     * @param Node $node
     * A node that has a reference to a variable
     *
     * @param Context $context
     * The context in which we found the reference
     *
     * @param CodeBase $code_base
     *
     * @return Variable
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
    public static function getOrCreatePropertyFromNodeInContext(
        string $property_name,
        Node $node,
        Context $context,
        CodeBase $code_base
    ) : Property {
        assert(is_string($property_name),
            'Property name must be a string. '
            . 'Got '
            . print_r($property_name, true)
            . ' at '
            . $context);

        // Figure out the class we're looking the property
        // up for
        $clazz = self::classFromNodeInContext(
            $node,
            $context,
            $code_base
        );

        // Return it if the property exists on the class
        if ($clazz->hasPropertyWithName($code_base, $property_name)) {
            return $clazz->getPropertyByNameInContext(
                $code_base,
                $property_name,
                $context
            );
        }

        $flags = 0;
        if ($node->kind == \ast\AST_STATIC_PROP) {
            $flags |= \ast\flags\MODIFIER_STATIC;
        }

        // Otherwise, we'll create it
        $property = new Property(
            $context,
            $property_name,
            new UnionType(),
            $flags
        );

        $property->setFQSEN(
            FullyQualifiedPropertyName::make(
                $clazz->getFQSEN(),
                $property_name
            )
        );

        $clazz->addProperty($code_base, $property);

        return $property;
    }

    /**
     * @return string
     * A unique and stable name for an anonymous class
     */
    public static function unqualifiedNameForAnonymousClassNode(
        Node $node,
        Context $context
    ) : string {
        assert((bool)($node->flags & \ast\flags\CLASS_ANONYMOUS),
            "Node must be an anonymous class node");

        $class_name = 'anonymous_class_'
            . substr(md5(implode('|', [
                $context->getFile(),
                $context->getLineNumberStart()
            ])), 0, 8);

        return $class_name;
    }

}
