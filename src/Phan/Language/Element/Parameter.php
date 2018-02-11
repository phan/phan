<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\FutureUnionType;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use ast\Node;

class Parameter extends Variable
{
    const REFERENCE_DEFAULT = 1;
    const REFERENCE_READ_WRITE = 2;
    const REFERENCE_WRITE_ONLY = 3;

    // __construct inherited from Variable

    /**
     * @var UnionType|null
     * The type of the default value if any
     */
    private $default_value_type = null;

    /**
     * @var FutureUnionType|null
     * The type of the default value if any
     */
    private $default_value_future_type = null;

    /**
     * @var Context|null used to resolve default_value_future_type
     */
    private $default_value_context = null;

    /**
     * @var mixed
     * The value of the default, if one is set
     */
    private $default_value = null;

    /**
     * @return static
     */
    public static function create(
        FileRef $context,
        string $name,
        UnionType $type,
        int $flags
    ) {
        if (Flags::bitVectorHasState($flags, \ast\flags\PARAM_VARIADIC)) {
            return new VariadicParameter($context, $name, $type, $flags);
        }
        return new Parameter($context, $name, $type, $flags);
    }

    /**
     * @return bool
     * True if this parameter has a type for its
     * default value
     */
    public function hasDefaultValue() : bool
    {
        return $this->default_value_type !== null;
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
     * @param FutureUnionType $type
     * The future type of the default value for this parameter
     *
     * @return void
     */
    public function setDefaultValueFutureType(FutureUnionType $type)
    {
        $this->default_value_future_type = $type;
    }

    /**
     * @return UnionType
     * The type of the default value for this parameter
     * if it exists
     * @suppress PhanAccessMethodInternal
     */
    public function getDefaultValueType() : UnionType
    {
        $future_type = $this->default_value_future_type;
        if ($future_type !== null) {
            // Only attempt to resolve the future type once.
            try {
                $this->default_value_type = $future_type->get();
            } catch (IssueException $exception) {
                // Ignore exceptions
                Issue::maybeEmitInstance(
                    $future_type->getCodebase(),
                    $future_type->getContext(),
                    $exception->getIssueInstance()
                );
            } finally {
                // Only try to resolve the FutureType once.
                $this->default_value_future_type = null;
            }
        }
        return $this->default_value_type;
    }

    /**
     * @param mixed $value
     * The value of the default for this parameter
     *
     * @return void
     */
    public function setDefaultValue($value)
    {
        $this->default_value = $value;
    }

    /**
     * If the value's default is null, or a constant evaluating to null,
     * then the parameter type should be converted to nullable
     * (E.g. `int $x = null` and `?int $x = null` are equivalent.
     */
    public function handleDefaultValueOfNull()
    {
        if ($this->default_value_type->isType(NullType::instance(false))) {
            // If it isn't already nullable, convert the parameter type to nullable.
            $this->convertToNullable();
        }
    }

    /**
     * @return mixed
     * The value of the default for this parameter if one
     * is defined, otherwise null.
     */
    public function getDefaultValue()
    {
        return $this->default_value;
    }

    /**
     * @return array<int,Parameter>
     * A list of parameters from an AST node.
     * @suppress PhanPluginUnusedVariable
     */
    public static function listFromNode(
        Context $context,
        CodeBase $code_base,
        Node $node
    ) : array {
        $parameter_list = [];
        $is_optional_seen = false;
        foreach ($node->children as $child_node) {
            $parameter =
                Parameter::fromNode($context, $code_base, $child_node);

            if (!$parameter->isOptional() && $is_optional_seen) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::ParamReqAfterOpt,
                    $node->lineno ?? 0
                );
            } elseif ($parameter->isOptional()
                && !$is_optional_seen
                && $parameter->getNonVariadicUnionType()->isEmpty()
            ) {
                $is_optional_seen = true;
            }

            $parameter_list[] = $parameter;
        }

        return $parameter_list;
    }

    /**
     * @param array<int,\ReflectionParameter> $reflection_parameters
     * @return array<int,Parameter>
     */
    public static function listFromReflectionParameterList(
        array $reflection_parameters
    ) : array {
        return \array_map(function (\ReflectionParameter $reflection_parameter) {
            return self::fromReflectionParameter($reflection_parameter);
        }, $reflection_parameters);
    }

    public static function fromReflectionParameter(
        \ReflectionParameter $reflection_parameter
    ) : Parameter {
        $flags = 0;
        // Check to see if its a pass-by-reference parameter
        if ($reflection_parameter->isPassedByReference()) {
            $flags |= \ast\flags\PARAM_REF;
        }

        // Check to see if its variadic
        if ($reflection_parameter->isVariadic()) {
            $flags |= \ast\flags\PARAM_VARIADIC;
        }
        $parameter = self::create(
            new Context(),
            $reflection_parameter->getName() ?? "arg",
            UnionType::fromReflectionType($reflection_parameter->getType()),
            $flags
        );
        if ($reflection_parameter->isOptional()) {
            $parameter->setDefaultValueType(
                NullType::instance(false)->asUnionType()
            );
        }
        return $parameter;
    }

    /**
     * @param Node|string|float|int $node
     * @return ?UnionType - Returns if we know the exact type of $node and can easily resolve it
     */
    private static function maybeGetKnownDefaultValueForNode($node)
    {
        if (!($node instanceof Node)) {
            return Type::fromObject($node)->asUnionType();
        }
        if ($node->kind === \ast\AST_CONST) {
            $name = $node->children['name']->children['name'] ?? null;
            if (\is_string($name)) {
                switch (\strtolower($name)) {
                    case 'false':
                        return FalseType::instance(false)->asUnionType();
                    case 'true':
                        return TrueType::instance(false)->asUnionType();
                    case 'null':
                        return NullType::instance(false)->asUnionType();
                }
            }
        }
        return null;
    }

    /**
     * @return Parameter
     * A parameter built from a node
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        Node $node
    ) : Parameter {
        // Get the type of the parameter
        $union_type = UnionType::fromNode(
            $context,
            $code_base,
            $node->children['type']
        );

        // Create the skeleton parameter from what we know so far
        $parameter = Parameter::create(
            $context,
            (string)$node->children['name'],
            $union_type,
            $node->flags ?? 0
        );

        // If there is a default value, store it and its type
        if (($default_node = $node->children['default']) !== null) {
            // Set the actual value of the default
            $parameter->setDefaultValue($default_node);

            // We can't figure out default values during the
            // parsing phase, unfortunately
            $default_value_union_type = self::maybeGetKnownDefaultValueForNode($default_node);
            if ($default_value_union_type !== null) {
                // Set the default value
                $parameter->setDefaultValueType($default_value_union_type);
            } else {
                \assert($default_node instanceof Node);

                if ($default_node->kind === \ast\AST_ARRAY) {
                    // We know the parameter default is some sort of array, but we don't know any more (e.g. key types, value types).
                    // When the future type is resolved, we'll know something more specific.
                    $default_value_union_type = ArrayType::instance(false)->asUnionType();
                } else {
                    static $possible_parameter_default_union_type = null;
                    if ($possible_parameter_default_union_type === null) {
                        // These can be constants or literals (or null/true/false)
                        $possible_parameter_default_union_type = new UnionType([
                            ArrayType::instance(false),
                            BoolType::instance(false),
                            FloatType::instance(false),
                            IntType::instance(false),
                            StringType::instance(false),
                            NullType::instance(false),
                        ]);
                    }
                    $default_value_union_type = $possible_parameter_default_union_type;
                }
                $parameter->setDefaultValueType($default_value_union_type);
                $parameter->setDefaultValueFutureType(new FutureUnionType(
                    $code_base,
                    clone($context)->withLineNumberStart($default_node->lineno ?? 0),
                    $default_node
                ));
            }
            $parameter->handleDefaultValueOfNull();
        }

        return $parameter;
    }

    /**
     * @return bool
     * True if this is an optional parameter
     */
    public function isOptional() : bool
    {
        return $this->hasDefaultValue();
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
        return false;
    }

    /**
     * Returns the Parameter in the form expected by a caller.
     *
     * If this parameter is variadic (e.g. `DateTime ...$args`), then this
     * would return a parameter with the type of the elements (e.g. `DateTime`)
     *
     * If this parameter is not variadic, returns $this.
     *
     * @return static (usually $this)
     */
    public function asNonVariadic()
    {
        return $this;
    }

    /**
     * If this Parameter is variadic, calling `getUnionType`
     * will return an array type such as `DateTime[]`. This
     * method will return the element type (such as `DateTime`)
     * for variadic parameters.
     */
    public function getNonVariadicUnionType() : UnionType
    {
        return self::getUnionType();
    }

    /**
     * @return bool - True when this is a non-variadic clone of a variadic parameter.
     * (We avoid bugs by adding new types to a variadic parameter if this is cloned.)
     * However, error messages still need to convert variadic parameters to a string.
     */
    public function isCloneOfVariadic() : bool
    {
        return false;
    }

    /**
     * Add the given union type to this parameter's union type
     *
     * @param UnionType $union_type
     * The type to add to this parameter's union type
     *
     * @return void
     */
    public function addUnionType(UnionType $union_type)
    {
        parent::setUnionType(self::getUnionType()->withUnionType($union_type));
    }

    /**
     * Add the given type to this parameter's union type
     *
     * @param Type $type
     * The type to add to this parameter's union type
     *
     * @return void
     */
    public function addType(Type $type)
    {
        parent::setUnionType(self::getUnionType()->withType($type));
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

    public function getReferenceType() : int
    {
        $flags = $this->getPhanFlags();
        if (Flags::bitVectorHasState($flags, Flags::IS_READ_REFERENCE)) {
            return self::REFERENCE_READ_WRITE;
        } elseif (Flags::bitVectorHasState($flags, Flags::IS_WRITE_REFERENCE)) {
            return self::REFERENCE_WRITE_ONLY;
        }
        return self::REFERENCE_DEFAULT;
    }

    /**
     * @return void
     */
    public function setIsOutputReference()
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                Flags::bitVectorWithState(
                    $this->getPhanFlags(),
                    Flags::IS_READ_REFERENCE,
                    false
                ),
                Flags::IS_WRITE_REFERENCE,
                true
            )
        );
    }

    public function __toString() : string
    {
        $string = '';

        $typeObj = $this->getNonVariadicUnionType();
        if (!$typeObj->isEmpty()) {
            $string .= (string)$typeObj . ' ';
        }

        if ($this->isPassByReference()) {
            $string .= '&';
        }

        if ($this->isVariadic()) {
            $string .= '...';
        }

        $string .= "\${$this->getName()}";

        if ($this->hasDefaultValue() && !$this->isVariadic()) {
            $default_value = $this->getDefaultValue();
            if ($default_value instanceof \ast\Node) {
                $string .= ' = null';
            } else {
                $string .= ' = ' . var_export($default_value, true);
            }
        }

        return $string;
    }

    public function toStubString() : string
    {
        $string = '';

        $typeObj = $this->getNonVariadicUnionType();
        if (!$typeObj->isEmpty()) {
            $string .= (string)$typeObj . ' ';
        }

        if ($this->isPassByReference()) {
            $string .= '&';
        }

        if ($this->isVariadic()) {
            $string .= '...';
        }

        $name = $this->getName();
        if (!\preg_match('@' . Comment::WORD_REGEX . '@', $name)) {
            // Some PECL extensions have invalid parameter names.
            // Replace invalid characters with U+FFFD replacement character.
            $name = \preg_replace('@[^a-zA-Z0-9_\x7f-\xff]@', '�', $name);
            if (!\preg_match('@' . Comment::WORD_REGEX . '@', $name)) {
                $name = '_' . $name;
            }
        }

        $string .= "\$$name";

        if ($this->hasDefaultValue() && !$this->isVariadic()) {
            $default_value = $this->getDefaultValue();
            if ($default_value instanceof \ast\Node) {
                $kind = $default_value->kind;
                if ($kind === \ast\AST_NAME) {
                    $default_repr = $default_value->children['name'];
                } elseif ($kind === \ast\AST_ARRAY) {
                    $default_repr = '[]';
                } else {
                    $default_repr = 'null';
                }
            } else {
                $default_repr = var_export($this->getDefaultValue(), true);
            }
            $string .= ' = ' . $default_repr;
        }

        return $string;
    }
}
