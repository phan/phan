<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\NodeException;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\Visitor\ClassNameKindVisitor;
use \Phan\Language\AST\Visitor\ClassNameValidationVisitor;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Variable;
use \Phan\Language\Type\MixedType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A set of methods for extracting details from AST nodes.
 */
class AST {

    /**
     * ast_node_type() is for places where an actual type
     * name appears. This returns that type name. Use node_type()
     * instead to figure out the type of a node
     *
     * @param Context $context
     * @param null|string|Node $node
     *
     * @see \Phan\Deprecated\AST::ast_node_type
     */
    public static function unionTypeFromSimpleNode(
        Context $context,
        $node
    ) : UnionType {
        $type_string = null;
        if($node instanceof \ast\Node) {
            switch($node->kind) {
            case \ast\AST_NAME:
                $type_string =
                    self::qualifiedName(
                        $context,
                        $node
                    );
                break;
            case \ast\AST_TYPE:
                if($node->flags == \ast\flags\TYPE_CALLABLE) {
                    $type_string = 'callable';
                } else if($node->flags == \ast\flags\TYPE_ARRAY) {
                    $type_string = 'array';
                } else {
                    assert(false, "Unknown type: {$node->flags}");
                }
                break;
            default:
                Log::err(
                    Log::EFATAL,
                    "ast_node_type: unknown node type: "
                    . \ast\get_kind_name($node->kind)
                );
                break;
            }
        } else {
            $type_string = (string)$node;
        }

        return UnionType::fromStringInContext(
            $type_string,
            $context
        );
    }

    /**
     * @param Context $context
     * @param null|string|Node $node
     *
     * @return string
     * The class name associated with nodes of various types
     *
     * @see \Phan\Deprecated\Util::find_class_name
     * Formerly `function find_class_name`
     */
    public static function classNameFromNode(
        Context $context,
        Node $node
    ) : string {
        // Extract the class name
        $class_name = (new Element($node))->acceptKindVisitor(
            new ClassNameKindVisitor($context)
        );

        if (empty($class_name)) {
            return '';
        }

        // Validate that the class name is correct
        if (!(new Element($node))->acceptKindVisitor(
            new ClassNameValidationVisitor($context, $class_name)
        )) {
            return '';
        }

        return $class_name;
    }

    /**
     * Get a list of fully qualified names from a node
     *
     * @return string[]
     *
     * @see \Phan\Deprecated\node_namelist
     * Formerly `function node_namelist`
     */
    public static function qualifiedNameList(
        Context $context,
        $node
    ) : array {
        if(!($node instanceof Node)) {
            return [];
        }

        return array_map(function($name_node) use ($context) {
            return self::qualifiedName($context, $name_node);
        }, $node->children);
    }

    /**
     * Get a fully qualified name form a node
     *
     * @return string
     *
     * @see \Phan\Deprecated\Util::qualified_name
     * From `function qualified_name`
     */
    public static function qualifiedName(
        Context $context,
        $node
    ) : string {
        if(!($node instanceof \ast\Node)
            || $node->kind != \ast\AST_NAME
        ) {
            return (string)self::varUnionType($context, $node);
        }

        $type_name = $node->children['name'];
        $type = null;

        // Check to see if the name is fully qualified
        if(!($node->flags & \ast\flags\NAME_NOT_FQ)) {
            if (0 !== strpos($type_name, '\\')) {
                $type_name = '\\' . $type_name;
            }
            return (string) UnionType::fromFullyQualifiedString(
                $type_name
            );
        }

        $type = UnionType::fromStringInContext(
            $type_name, $context
        );

        return (string)$type;
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
                    $node->lineno
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

            // TODO: The name usually goes in slot zero, right?
            $node = array_values($node->children)[0];
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
        if(!($node->children['expr'] instanceof \node\Node)) {
            return;
        }

        if($node->children['expr']->kind !== \node\node_DIM) {
            return;
        }

        $temp = $node->children['expr']->children['expr'];
        $lnode = $temp;
        if(!($temp->kind == \node\node_PROP
            || $temp->kind == \node\node_STATIC_PROP
        )) {
            return;
        }

        while($temp instanceof \node\Node
            && ($temp->kind == \node\node_PROP
            || $temp->kind == \node\node_STATIC_PROP)
        ) {
            $lnode = $temp;

            // Lets just hope the 0th is the expression
            // we want
            $temp = array_values($temp->children)[0];
        }

        if(!($temp instanceof \node\Node)) {
            return;
        }

        if(($lnode->children['prop'] instanceof \node\Node
            && $lnode->children['prop']->kind == \node\node_VAR
            ) && ($temp->kind == \node\node_VAR
            || $temp->kind == \node\node_NAME)
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
                    $node->lineno
                );
            }
        }
    }

    /**
     * @param Node $node
     * The node that has a reference to a class
     *
     * @param Context $context
     * The context in which we found the node
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
     */
    public static function classFromNodeInContext(
        Node $node,
        Context $context
    ) : Clazz {
        // Figure out the name of the class
        $class_name = self::classNameFromNode($context, $node);

        // If we can't figure out the class name (which happens
        // from time to time), then give up
        if (empty($class_name)) {
            throw new NodeException($node, 'Could not find class name');
        }

        $class_fqsen =
            $context->getScopeFQSEN()->withClassName(
                $context, $class_name
            );

        // Check to see if the class actually exists
        if (!$context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
            throw new CodeBaseException(
                "Can't find class {$class_fqsen}"
            );
        }

        $class =
            $context->getCodeBase()->getClassByFQSEN($class_fqsen);

        return $class;

        /*
        // Hunt for an appropriate alternate for the class
        // that is associated with the current context
        foreach ($class->alternateGenerator($context->getCodeBase())
            as $alternate_id => $alternate_class
        ) {
            if ($alternate_class->getFile() == $context->getFile()) {
                return $class;
            }
        }

        assert(false,
            "Couldn't find appropriate alternate for class $class_fqsen.");

        // We didn't find an appropriate alternate
        return $class;
         */
    }

    /**
     * @param Node $node
     * The node that has a reference to a class
     *
     * @param Context $context
     * The context in which we found the node
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
     */
    public static function classMethodFromNodeInContext(
        Node $node,
        Context $context,
        $method_name_or_node,
        bool $is_static
    ) : Method {
        $clazz = self::classFromNodeInContext($node, $context);

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

        if (!$clazz->hasMethodWithName($method_name)) {
            if ($is_static) {
                throw new CodeBaseException(
                    "static call to undeclared method {$clazz->getFQSEN()}::$method_name()"
                );
            } else {
                throw new CodeBaseException(
                    "call to undeclared method {$clazz->getFQSEN()}->$method_name()"
                );
            }
        }

        $method = $clazz->getMethodByName($method_name);

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
     * @return Method
     * A method with the given name in the given context
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * function
     */
    public static function functionFromNameInContext(
        string $function_name,
        Context $context
    ) : Method {

        $function_fqsen =
            $context->getScopeFQSEN()->withFunctionName(
                $context, $function_name
            );

        // Make sure the method we're calling actually exists
        if (!$context->getCodeBase()->hasMethodWithFQSEN(
            $function_fqsen
        )) {
            throw new CodeBaseException(
                "call to undefined function {$function_name}()"
            );
        }

        return $context->getCodeBase()->getMethodByFQSEN(
            $function_fqsen
        );
    }

    /**
     * @param Node $node
     * A node that has a reference to a variable
     *
     * @param Context $context
     * The context in which we found the reference
     *
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     */
    public static function getOrCreateVariableFromNodeInContext(
        Node $node,
        Context $context
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
            $node, $context, false
        );


        $context->addScopeVariable($variable);

        return $variable;
    }

}
