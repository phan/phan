<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

class Parameter extends Variable {

    /**
     * @var \mixed
     * The default value for a parameter
     */
    private $default_value = null;

    /**
     * @var UnionType
     * The type of the default value if any
     */
    private $default_value_type = null;

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        Comment $comment,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $context,
            $comment,
            $name,
            $type,
            $flags
        );
    }

    /**
     * @param \mixed $default_value
     * The default value for the parameter
     *
     * @return null
     */
    public function setDefaultValue($default_value) {
        $this->default_value = $default_value;
    }

    /**
     * @return bool
     * True if this parameter has a default value
     */
    public function hasDefaultValue() : bool {
        return null !== $this->default_value;
    }

    /**
     * @return \mixed
     * The default value for the parameter if one
     * exists
     */
    public function getDefaultValue() {
        return $this->default_value;
    }

    /**
     * @param UnionType $type
     * The type of the default value for this parameter
     *
     * @return null
     */
    public function setDefaultValueUnionType(UnionType $type) {
        $this->default_value_type = $type;
    }

    /**
     * @return bool
     * True if this parameter has a type for its
     * default value
     */
    public function hasDefaultValueUnionType() : bool {
        return !empty($this->default_value_type);
    }

    /**
     * @return UnionType
     * The type of the default value for this parameter
     * if it exists
     */
    public function getDefaultValueType() : UnionType {
        return $this->default_value_type;
    }

    /**
     * @return Parameter[]
     * A list of parameters from an AST node.
     *
     * @see \Phan\Deprecated\Pass1::node_paramlist
     * Formerly `function node_paramlist`
     */
    public static function listFromNode(
        Context $context,
        Node $node
    ) : array {
        assert($node instanceof Node, "node was not an \\ast\\Node");

        $parameter_list = [];
        $is_optional_seen = false;
        foreach ($node->children as $i => $child_node) {
            $parameter =
                Parameter::fromNode($context, $child_node);

            if (!$parameter->isOptional() && $is_optional_seen) {
                Log::err(
                    Log::EPARAM,
                    "required arg follows optional",
                    $context->getFile(),
                    // TODO: switch to $child_node->lineno
                    $node->lineno
                );
            } else if ($parameter->isOptional()) {
                $is_optional_seen = true;
            }

            $parameter_list[] = $parameter;
        }

        return $parameter_list;
    }

    /**
     * @return Parameter
     * A parameter built from a node
     *
     * @see \Phan\Deprecated\Pass1::node_param
     * Formerly `function node_param`
     */
    public static function fromNode(
        Context $context,
        Node $node
    ) : Parameter {

        assert($node instanceof Node, "node was not an \\ast\\Node");

        // Get the type of the parameter
        $type = UnionType::fromSimpleNode(
            $context,
            $node->children[0]
        );

        $comment =
            Comment::fromString($node->docComment ?? '');

        // Create the skeleton parameter from what we know so far
        $parameter = new Parameter(
            $context,
            $comment,
            (string)$node->children[1],
            $type,
            $node->flags
        );

        // If there is a default value, store it and its type
        if ($node->children[2] !== null) {
            // Set the node as the value
            $parameter->setDefaultValue($node->children[2]);

            // Set the type
            $parameter->setDefaultValueUnionType(
                UnionType::fromNode(
                    $context,
                    $node->children[2]
                )
            );
        }

        return $parameter;
    }

    /**
     * @return bool
     * True if this is an optional parameter
     */
    public function isOptional() : bool {
        return (
            $this->hasDefaultValueUnionType()
            || $this->hasDefaultValue()
        );
    }

    public function isVariadic() : bool {
        return (bool)(
            $this->getFlags() & \ast\flags\PARAM_VARIADIC
        );
    }

    public function __toString() : string {
        $string = '';

        if ($this->getUnionType()->hasAnyType()) {
            $string .= (string)$this->getUnionType() . ' ';
        }

        $string .= $this->getName();

        return $string;
    }

}
