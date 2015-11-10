<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\Visitor\ClassNameKindVisitor;
use \Phan\Language\AST\Visitor\ClassNameValidationVisitor;
use \Phan\Language\Element\Variable;
use \Phan\Language\Type\MixedType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A set of methods for extracting details from AST nodes.
 */
trait AST {

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
    protected static function astUnionTypeFromSimpleNode(
        Context $context,
        $node
    ) : UnionType {
        if($node instanceof \ast\Node) {
            switch($node->kind) {
            case \ast\AST_NAME:
                $result =
                    static::astQualifiedName(
                        $context,
                        $node
                    );
                break;
            case \ast\AST_TYPE:
                if($node->flags == \ast\flags\TYPE_CALLABLE) {
                    $result = 'callable';
                } else if($node->flags == \ast\flags\TYPE_ARRAY) {
                    $result = 'array';
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
            $result = (string)$node;
        }

        return UnionType::fromStringInContext($result, $context);
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
    protected static function astClassNameFromNode(
        Context $context,
        $node
    ) : string {
        // Extract the class name
        $class_name = (new Element($node))->acceptKindVisitor(
            new ClassNameKindVisitor($context)
        );

        if (empty($class_name)) {
            return '';
        }

        if ('ast\\Node' == $class_name) {
            Debug::printNode($node);
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
    protected static function astQualifiedNameList(
        Context $context,
        $node
    ) : array {
        if(!($node instanceof Node)) {
            return [];
        }

        return array_map(function($name_node) use ($context) {
            return self::astQualifiedName($context, $name_node);
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
    protected static function astQualifiedName(
        Context $context,
        $node
    ) : string {
        if(!($node instanceof \ast\Node)
            || $node->kind != \ast\AST_NAME
        ) {
            return self::astVarUnionType($context, $node);
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
     * @param Node|mixed $node
     * The node to get a union type for
     *
     * @return UnionType
     *
     * @see \Phan\Deprecated\Pass2::var_type
     * From `function var_type`
     */
    protected static function astVarUnionType(
        Context $context,
        $node
    ) : UnionType {

        // Check for $$var or ${...} (whose idea was that anyway?)
        if(($node->children['name'] instanceof Node)
            && ($node->children['name']->kind == \ast\AST_VAR
                || $node->children['name']->kind == \ast\AST_BINARY_OP)
        ) {
            return MixedType::instance()->asUnionType();
        }

        if($node->children['name'] instanceof Node) {
            return new UnionType();
        }

        $variable_name = $node->children['name'];

        if (!$context->getScope()->hasVariableWithName($variable_name)
        ) {
            if(!Variable::isSuperglobalVariableWithName($variable_name)) {
                Log::err(
                    Log::EVAR,
                    "Variable $variable_name is not defined",
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
    public static function astVariableName($node) : string {
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

}
