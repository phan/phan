<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\AST\UnionTypeVisitor;
use \Phan\CodeBase;
use \Phan\Exception\CodeBaseException;
use \Phan\Exception\IssueException;
use \Phan\Issue;
use \Phan\Language\Context;
use \Phan\Language\Type\ArrayType;
use \Phan\Language\Type\BoolType;
use \Phan\Language\Type\FloatType;
use \Phan\Language\Type\IntType;
use \Phan\Language\Type\NullType;
use \Phan\Language\Type\StringType;
use \Phan\Language\UnionType;
use \ast\Node;
use \ast\Node\Decl;

class Parameter extends Variable
{

    /**
     * @var UnionType
     * The type of the default value if any
     */
    private $default_value_type = null;

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
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
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags
        );
    }

    /**
     * After a clone is called on this object, clone our
     * deep objects.
     *
     * @return null
     */
    public function __clone()
    {
        parent::__clone();
        $this->default_value_type = $this->default_value_type
            ? clone($this->default_value_type)
            : $this->default_value_type;
    }

    public function setUnionType(UnionType $type)
    {
        parent::setUnionType($type);
    }

    /**
     * @param UnionType $type
     * The type of the default value for this parameter
     *
     * @return void
     */
    public function setDefaultValueType(UnionType $type)
    {
        $this->default_value_type = $type;
    }

    /**
     * @return bool
     * True if this parameter has a type for its
     * default value
     */
    public function hasDefaultValue() : bool
    {
        return !empty($this->default_value_type);
    }

    /**
     * @return UnionType
     * The type of the default value for this parameter
     * if it exists
     */
    public function getDefaultValueType() : UnionType
    {
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
        CodeBase $code_base,
        Node $node
    ) : array {
        assert($node instanceof Node, "node was not an \\ast\\Node");

        $parameter_list = [];
        $is_optional_seen = false;
        foreach ($node->children ?? [] as $i => $child_node) {
            $parameter =
                Parameter::fromNode($context, $code_base, $child_node);

            if (!$parameter->isOptional() && $is_optional_seen) {
                Issue::emit(
                    Issue::ParamReqAfterOpt,
                    $context->getFile(),
                    $node->lineno ?? 0
                );
            } elseif ($parameter->isOptional()
                && !$is_optional_seen
                && $parameter->getUnionType()->isEmpty()
            ) {
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
        CodeBase $code_base,
        Node $node
    ) : Parameter {

        assert($node instanceof Node, "node was not an \\ast\\Node");

        // Get the type of the parameter
        $union_type = UnionType::fromNode(
            $context,
            $code_base,
            $node->children['type']
        );

        // Create the skeleton parameter from what we know so far
        $parameter = new Parameter(
            $context,
            (string)$node->children['name'],
            $union_type,
            $node->flags ?? 0
        );

        // If there is a default value, store it and its type
        if (($default_node = $node->children['default']) !== null) {

            // We can't figure out default values during the
            // parsing phase, unfortunately
            if (!($default_node instanceof Node)) {
                // Get the type of the default
                $union_type = UnionType::fromNode(
                    $context,
                    $code_base,
                    $default_node
                );

                // Set the default value
                $parameter->setDefaultValueType(
                    $union_type
                );
            } else {
                try {
                    // Get the type of the default
                    $union_type = UnionType::fromNode(
                        $context,
                        $code_base,
                        $default_node,
                        false
                    );
                } catch (IssueException $exception) {
                    if ($default_node instanceof Node
                        && $default_node->kind === \ast\AST_ARRAY
                    ) {
                        $union_type = new UnionType([
                            ArrayType::instance(),
                        ]);
                    } else {
                        // If we're in the parsing phase and we
                        // depend on a constant that isn't yet
                        // defined, give up and set it to
                        // bool|float|int|string to avoid having
                        // to handle a future type.
                        $union_type = new UnionType([
                            BoolType::instance(),
                            FloatType::instance(),
                            IntType::instance(),
                            StringType::instance(),
                        ]);
                    }
                }

                // Set the default value
                $parameter->setDefaultValueType($union_type);
            }
        }

        return $parameter;
    }

    /**
     * @return bool
     * True if this is an optional parameter
     */
    public function isOptional() : bool
    {
        return (
            $this->hasDefaultValue()
            || $this->isVariadic()
        );
    }

    /**
     * @return bool
     * True if this is a required parameter
     */
    public function isRequired() : bool
    {
        return !$this->isOptional();
    }

    /**
     * @return bool
     * True if this parameter is variadic, i.e. can
     * take an unlimited list of parameters and express
     * them as an array.
     */
    public function isVariadic() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\PARAM_VARIADIC
        );
    }

    /**
     * @return bool
     * True if this parameter is pass-by-reference
     * i.e. prefixed with '&'.
     */
    public function isPassByReference() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\PARAM_REF
        );
    }

    public function __toString() : string
    {
        $string = '';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= (string)$this->getUnionType() . ' ';
        }

        if ($this->isPassByReference()) {
            $string .= '&';
        }

        $string .= "\${$this->getName()}";

        if ($this->isVariadic()) {
            $string .= ' ...';
        }

        if ($this->isOptional()) {
            $string .= ' = null';
        }

        return $string;
    }
}
