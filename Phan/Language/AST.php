<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\Visitor\ClassNameKindVisitor;
use \Phan\Language\AST\Visitor\ClassNameValidationVisitor;
use \Phan\Language\Type;
use \ast\Node;

/**
 * A set of methods for extracting details from AST nodes.
 */
trait AST {

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

        if (!$class_name) {
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
            && $node->kind != \ast\AST_NAME
        ) {
            return self::astVarType($context, $node);
        }

        $name = $node->children[0];

        $type = new Type([$name]);

        if($node->flags & \ast\flags\NAME_NOT_FQ) {

            // is it a simple native type name?
            if($type->isNativeType()) {
                return (string)$type;
            }

            // Not fully qualified, check if we have an exact
            // namespace alias for it
            if ($context->hasNamespaceMapFor(T_CLASS, (string)$type)) {
                return
                    $context->getNamespaceMapFor(T_CLASS, (string)$type);
            }

            // Check for a namespace-relative alias
            if(($pos = strpos((string)$type, '\\')) !== false) {

                $first_part = substr((string)$type, 0, $pos);

                if ($context->hasNamespaceMapFor(T_CLASS, $first_part)) {
                    $qualified_first_part =
                        $context->getNamespaceMapFor(T_CLASS, $first_part);

                    // Replace that first aliases part and return the full name
                    return $qualified_first_part
                        . '\\'
                        . substr((string)$type, $pos + 1);
                }
            }

            // No aliasing, just prepend the namespace
            return $context->getNamespace() . '\\' . $name;
        } else {
            return $name;
        }
    }

    /**
     * Takes an AST_VAR node and tries to find the variable in
     * the current scope and returns its likely type. For
     * pass-by-ref args, we suppress the not defined error message
     *
     * @param Context $context
     * @param null|string\Node $node
     *
     * @return Type
     *
     * @see \Phan\Deprecated\Pass2::var_type
     * From `function var_type`
     */
    protected static function astVarType(
        Context $context,
        $node
    ) : Type {

        // Check for $$var or ${...} (whose idea was that anyway?)
        if(($node->children[0] instanceof Node)
            && ($node->children[0]->kind == \ast\AST_VAR
                || $node->children[0]->kind == \ast\AST_BINARY_OP)
        ) {
            return new Type(['mixed']);
        }

        if($node->children[0] instanceof Node) {
            return Type::none();
        }

        $variable_name = $node->children[0];

        // if(empty($scope[$current_scope]['vars'][$node->children[0]])
        if (!$context->getScope()->hasVariableWithName($variable_name)) {
            if(!superglobal($variable_name))
                Log::err(
                    Log::EVAR,
                    "Variable \${$node->children[0]} is not defined",
                    $context->getFile(),
                    $node->lineno
                );
        } else {
            $variable =
                $context->getScope()->getVariableWithName($variable_name);

            return $variable->getType();

            /*
            if(!empty($scope[$current_scope]['vars'][$node->children[0]]['tainted'])
            ) {
                $tainted_by =
                    $scope[$current_scope]['vars'][$node->children[0]]['tainted_by'];
                $taint = true;
            }
            */
        }

        return Type::none();
    }


}
