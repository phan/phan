<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use ast\Node;

class Parameter extends Variable
{
    const REFERENCE_DEFAULT = 1;
    const REFERENCE_READ_WRITE = 2;
    const REFERENCE_WRITE_ONLY = 3;

    /**
     * @var UnionType|null
     * The type of the default value if any
     */
    private $default_value_type = null;

    /**
     * @var mixed
     * The value of the default, if one is set
     */
    private $default_value = null;

    /**
     * @param FileRef $file_ref
     * The file and lines in which the unaddressable element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        FileRef $file_ref,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $file_ref,
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

    /**
     * @return static - non-variadic clone which can be modified.
     */
    public function cloneAsNonVariadic()
    {
        $result = clone($this);
        if ($result->isVariadic() && !$result->isCloneOfVariadic()) {
            $result->convertToNonVariadic();
            $result->setPhanFlags(Flags::bitVectorWithState(
                $result->getPhanFlags(),
                Flags::IS_CLONE_OF_VARIADIC,
                true
            ));
        }
        return $result;
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
     * @return UnionType
     * The type of the default value for this parameter
     * if it exists
     */
    public function getDefaultValueType() : UnionType
    {
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
     *  We pretend `int $x = SOME_NULL_CONST` is equivalent as well.)
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
     * @return Parameter[]
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
        foreach ($node->children ?? [] as $child_node) {
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
     * @param \ReflectionParameter[] $reflection_parameters
     * @return Parameter[]
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

        $parameter = new Parameter(
            new Context(),
            $reflection_parameter->getName() ?? "arg",
            UnionType::fromReflectionType($reflection_parameter->getType()),
            $flags
        );
        if ($reflection_parameter->isOptional()) {
            // TODO: check if ($reflection_parameter->isDefaultValueAvailable())
            $parameter->setDefaultValueType(
                NullType::instance(false)->asUnionType()
            );
        }
        return $parameter;
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

                // Set the actual value of the default
                $parameter->setDefaultValue($default_node);
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
                            ArrayType::instance(false),
                        ]);
                    } else {
                        // If we're in the parsing phase and we
                        // depend on a constant that isn't yet
                        // defined, give up and set it to
                        // bool|float|int|string to avoid having
                        // to handle a future type.
                        $union_type = new UnionType([
                            BoolType::instance(false),
                            FloatType::instance(false),
                            IntType::instance(false),
                            StringType::instance(false),
                        ]);
                    }
                }

                // Set the default value
                $parameter->setDefaultValueType($union_type);

                // Set the actual value of the default
                $parameter->setDefaultValue($default_node);
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
        if (!$this->isVariadic()) {
            return $this;
        }
        // TODO: Is it possible to cache this while maintaining
        //       correctness? PostOrderAnalysisVisitor clones the
        //       value to avoid it being reused.
        //
        // Also, figure out if the cloning still working correctly
        // after this PR for fixing variadic args. Create a single
        // Parameter instance for analyzing callers of the
        // corresponding method/function.
        // e.g. $this->getUnionType() is of type T[]
        //      $this->non_variadic->getUnionType() is of type T
        return new Parameter(
            $this->getFileRef(),
            $this->getName(),
            $this->getNonVariadicUnionType(),
            Flags::bitVectorWithState($this->getFlags(), \ast\flags\PARAM_VARIADIC, false)
        );
    }

    /**
     * If this Parameter is variadic, calling `getUnionType`
     * will return an array type such as `DateTime[]`. This
     * method will return the element type (such as `DateTime`)
     * for variadic parameters.
     */
    public function getNonVariadicUnionType() : UnionType
    {
        $union_type = parent::getUnionType();
        if ($this->isCloneOfVariadic()) {
            return $union_type->nonArrayTypes();  // clones converted inner types to a generic array T[]. Convert it back to T.
        }
        return $union_type;
    }

    /**
     * If this parameter is variadic (e.g. `DateTime ...$args`),
     * then this returns the corresponding array type(s) of $args.
     * (e.g. `DateTime[]`)
     *
     * NOTE: For analyzing the code within a function,
     * code should pass $param->cloneAsNonVariadic() instead.
     * Modifying/analyzing the clone should work without any bugs.
     *
     * TODO(Issue #376) : We will probably want to be able to modify
     * the underlying variable, e.g. by creating
     * `class UnionTypeGenericArrayView extends UnionType`.
     * Otherwise, type inference of `...$args` based on the function
     * source will be less effective without phpdoc types.
     *
     * @override
     */
    public function getUnionType() : UnionType
    {
        if ($this->isVariadic() && !$this->isCloneOfVariadic()) {
            // TODO: Figure out why asNonEmptyGenericArrayTypes() causes test failures
            return parent::getUnionType()->asGenericArrayTypes();
        }
        return parent::getUnionType();
    }

    /**
     * @return bool - True when this is a non-variadic clone of a variadic parameter.
     * (We avoid bugs by adding new types to a variadic parameter if this is cloned.)
     * However, error messages still need to convert variadic parameters to a string.
     */
    protected function isCloneOfVariadic() : bool
    {
        return Flags::bitVectorHasState($this->getPhanFlags(), Flags::IS_CLONE_OF_VARIADIC);
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
        parent::getUnionType()->addUnionType($union_type);
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
        parent::getUnionType()->addType($type);
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
