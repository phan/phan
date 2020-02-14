<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use AssertionError;
use ast\Node;
use InvalidArgumentException;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\FutureUnionType;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\ClosureDeclarationParameter;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use Phan\Parse\ParseVisitor;

use function strlen;

/**
 * Represents the information Phan has about a function-like's Parameter
 * (e.g. of a function, closure, method, a PHPDoc closure/callable signature such as `Closure(MyClass=):void`, or phpdoc method.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class Parameter extends Variable
{
    public const REFERENCE_DEFAULT = 1;
    public const REFERENCE_READ_WRITE = 2;
    public const REFERENCE_WRITE_ONLY = 3;
    public const REFERENCE_IGNORED = 4;

    // __construct(Context $context, string $name, UnionType $type, int $flags) inherited from Variable

    /**
     * @var UnionType|null
     * The type of the default value, if any
     */
    private $default_value_type = null;

    /**
     * @var FutureUnionType|null
     * The type of the default value if any
     */
    private $default_value_future_type = null;

    /**
     * @var Node|string|int|float|null
     * The value of the node of the default, if one is set
     */
    private $default_value = null;

    /**
     * @return static
     */
    public static function create(
        Context $context,
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
    public function hasDefaultValue(): bool
    {
        return $this->default_value_type !== null;
    }

    /**
     * @param UnionType $type
     * The type of the default value for this parameter
     */
    public function setDefaultValueType(UnionType $type): void
    {
        $this->default_value_type = $type;
    }

    /**
     * @param FutureUnionType $type
     * The future type of the default value for this parameter
     */
    public function setDefaultValueFutureType(FutureUnionType $type): void
    {
        $this->default_value_future_type = $type;
    }

    /**
     * @return UnionType
     * The type of the default value for this parameter
     * if it exists
     */
    public function getDefaultValueType(): UnionType
    {
        $future_type = $this->default_value_future_type;
        if ($future_type !== null) {
            // Only attempt to resolve the future type once.
            try {
                $this->default_value_type = $future_type->get()->asNonLiteralType();
            } catch (IssueException $exception) {
                // Ignore exceptions
                Issue::maybeEmitInstance(
                    $future_type->getCodebase(),  // @phan-suppress-current-line PhanAccessMethodInternal
                    $future_type->getContext(),  // @phan-suppress-current-line PhanAccessMethodInternal
                    $exception->getIssueInstance()
                );
            } finally {
                // Only try to resolve the FutureType once.
                $this->default_value_future_type = null;
            }
        }
        // @phan-suppress-next-line PhanPossiblyNullTypeReturn callers should check hasDefaultType
        return $this->default_value_type;
    }

    /**
     * @param mixed $value
     * The value of the default for this parameter
     */
    public function setDefaultValue($value): void
    {
        $this->default_value = $value;
    }

    /**
     * If the value's default is null, or a constant evaluating to null,
     * then the parameter type should be converted to nullable
     * (E.g. `int $x = null` and `?int $x = null` are equivalent.
     */
    public function handleDefaultValueOfNull(): void
    {
        if ($this->default_value_type && $this->default_value_type->isType(NullType::instance(false))) {
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
     * @return list<Parameter>
     * A list of parameters from an AST node.
     */
    public static function listFromNode(
        Context $context,
        CodeBase $code_base,
        Node $node
    ): array {
        $parameter_list = [];
        foreach ($node->children as $child_node) {
            $parameter =
                Parameter::fromNode($context, $code_base, $child_node);

            $parameter_list[] = $parameter;
        }

        return $parameter_list;
    }

    /**
     * @param list<\ReflectionParameter> $reflection_parameters
     * @return list<Parameter>
     */
    public static function listFromReflectionParameterList(
        array $reflection_parameters
    ): array {
        return \array_map([self::class, 'fromReflectionParameter'], $reflection_parameters);
    }

    /**
     * Creates a parameter signature for a function-like from the name, type, etc. of the passed in reflection parameter
     */
    public static function fromReflectionParameter(
        \ReflectionParameter $reflection_parameter
    ): Parameter {
        $flags = 0;
        // Check to see if it's a pass-by-reference parameter
        if ($reflection_parameter->isPassedByReference()) {
            $flags |= \ast\flags\PARAM_REF;
        }

        // Check to see if it's variadic
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
                NullType::instance(false)->asPHPDocUnionType()
            );
        }
        return $parameter;
    }

    /**
     * @param Node|string|float|int $node
     * @return ?UnionType - Returns if we know the exact type of $node and can easily resolve it
     */
    private static function maybeGetKnownDefaultValueForNode($node): ?UnionType
    {
        if (!($node instanceof Node)) {
            return Type::nonLiteralFromObject($node)->asRealUnionType();
        }
        // XXX: This could be made more precise and handle things like unary/binary ops.
        // However, this doesn't know about constants that haven't been parsed yet.
        if ($node->kind === \ast\AST_CONST) {
            $name = $node->children['name']->children['name'] ?? null;
            if (\is_string($name)) {
                switch (\strtolower($name)) {
                    case 'false':
                        return FalseType::instance(false)->asRealUnionType();
                    case 'true':
                        return TrueType::instance(false)->asRealUnionType();
                    case 'null':
                        return NullType::instance(false)->asRealUnionType();
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
    ): Parameter {
        // Get the type of the parameter
        $type_node = $node->children['type'];
        if ($type_node) {
            try {
                $union_type = (new UnionTypeVisitor($code_base, $context))->fromTypeInSignature($type_node);
            } catch (IssueException $e) {
                Issue::maybeEmitInstance($code_base, $context, $e->getIssueInstance());
                $union_type = UnionType::empty();
            }
        } else {
            $union_type = UnionType::empty();
        }

        // Create the skeleton parameter from what we know so far
        $parameter = Parameter::create(
            (clone($context))->withLineNumberStart($node->lineno),
            (string)$node->children['name'],
            $union_type,
            $node->flags
        );
        if (($type_node->kind ?? null) === \ast\AST_NULLABLE_TYPE) {
            $parameter->setIsUsingNullableSyntax();
        }

        // If there is a default value, store it and its type
        $default_node = $node->children['default'];
        if ($default_node !== null) {
            // Set the actual value of the default
            $parameter->setDefaultValue($default_node);
            try {
                // @phan-suppress-next-line PhanAccessMethodInternal
                ParseVisitor::checkIsAllowedInConstExpr($default_node);

                // We can't figure out default values during the
                // parsing phase, unfortunately
                $has_error = false;
            } catch (InvalidArgumentException $_) {
                // If the parameter default is an invalid constant expression,
                // then don't use that value elsewhere.
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::InvalidConstantExpression,
                    $default_node->lineno ?? $node->lineno
                );
                $has_error = true;
            }
            $default_value_union_type = $has_error ? null : self::maybeGetKnownDefaultValueForNode($default_node);

            if ($default_value_union_type !== null) {
                // Set the default value
                $parameter->setDefaultValueType($default_value_union_type);
            } else {
                if (!($default_node instanceof Node)) {
                    throw new AssertionError("Somehow failed to infer type for the default_node - not a scalar or a Node");
                }

                if ($default_node->kind === \ast\AST_ARRAY) {
                    // We know the parameter default is some sort of array, but we don't know any more (e.g. key types, value types).
                    // When the future type is resolved, we'll know something more specific.
                    $default_value_union_type = ArrayType::instance(false)->asRealUnionType();
                } else {
                    static $possible_parameter_default_union_type = null;
                    if ($possible_parameter_default_union_type === null) {
                        // These can be constants or literals (or null/true/false)
                        // (STDERR, etc. are constants)
                        $possible_parameter_default_union_type = UnionType::fromFullyQualifiedRealString('array|bool|float|int|string|resource|null');
                    }
                    $default_value_union_type = $possible_parameter_default_union_type;
                }
                $parameter->setDefaultValueType($default_value_union_type);
                if (!$has_error) {
                    $parameter->setDefaultValueFutureType(new FutureUnionType(
                        $code_base,
                        (clone($context))->withLineNumberStart($default_node->lineno ?? 0),
                        $default_node
                    ));
                }
            }
            $parameter->handleDefaultValueOfNull();
        }

        return $parameter;
    }

    /**
     * @return bool
     * True if this is an optional parameter
     */
    public function isOptional(): bool
    {
        return $this->hasDefaultValue();
    }

    /**
     * @return bool
     * True if this is a required parameter
     * @suppress PhanUnreferencedPublicMethod provided for API completeness
     */
    public function isRequired(): bool
    {
        return !$this->isOptional();
    }

    /**
     * @return bool
     * True if this parameter is variadic, i.e. can
     * take an unlimited list of parameters and express
     * them as an array.
     */
    public function isVariadic(): bool
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
    public function getNonVariadicUnionType(): UnionType
    {
        return self::getUnionType();
    }

    /**
     * @return bool - True when this is a non-variadic clone of a variadic parameter.
     * (We avoid bugs by adding new types to a variadic parameter if this is cloned.)
     * However, error messages still need to convert variadic parameters to a string.
     */
    public function isCloneOfVariadic(): bool
    {
        return false;
    }

    /**
     * Add the given union type to this parameter's union type
     *
     * @param UnionType $union_type
     * The type to add to this parameter's union type
     */
    public function addUnionType(UnionType $union_type): void
    {
        parent::setUnionType(self::getUnionType()->withUnionType($union_type));
    }

    /**
     * Add the given type to this parameter's union type
     *
     * @param Type $type
     * The type to add to this parameter's union type
     */
    public function addType(Type $type): void
    {
        parent::setUnionType(self::getUnionType()->withType($type));
    }

    /**
     * @return bool
     * True if this parameter is pass-by-reference
     * i.e. prefixed with '&'.
     */
    public function isPassByReference(): bool
    {
        return $this->getFlagsHasState(\ast\flags\PARAM_REF);
    }

    /**
     * Returns an enum value indicating how this reference parameter is changed by the caller.
     *
     * E.g. for REFERENCE_WRITE_ONLY, the reference parameter ignores the passed in value and always replaces it with another type.
     * (added with (at)phan-output-parameter in PHPDoc or with special prefixes in FunctionSignatureMap.php)
     */
    public function getReferenceType(): int
    {
        $flags = $this->getPhanFlags();
        if (Flags::bitVectorHasState($flags, Flags::IS_IGNORED_REFERENCE)) {
            return self::REFERENCE_IGNORED;
        } elseif (Flags::bitVectorHasState($flags, Flags::IS_READ_REFERENCE)) {
            return self::REFERENCE_READ_WRITE;
        } elseif (Flags::bitVectorHasState($flags, Flags::IS_WRITE_REFERENCE)) {
            return self::REFERENCE_WRITE_ONLY;
        }
        return self::REFERENCE_DEFAULT;
    }

    /**
     * Records that this parameter is an output reference
     * (it overwrites the value of the argument by reference)
     */
    public function setIsOutputReference(): void
    {
        $this->enablePhanFlagBits(Flags::IS_WRITE_REFERENCE);
        $this->disablePhanFlagBits(Flags::IS_READ_REFERENCE);
    }

    /**
     * Records that this parameter is an ignored reference
     * (it should be assumed that the reference does not affect types in a meaningful way for the caller)
     */
    public function setIsIgnoredReference(): void
    {
        $this->enablePhanFlagBits(Flags::IS_IGNORED_REFERENCE);
        $this->disablePhanFlagBits(Flags::IS_READ_REFERENCE | Flags::IS_WRITE_REFERENCE);
    }

    private function setIsUsingNullableSyntax(): void
    {
        $this->enablePhanFlagBits(Flags::IS_PARAM_USING_NULLABLE_SYNTAX);
    }

    /**
     * Is this a parameter that uses the nullable `?` syntax in the actual declaration?
     *
     * E.g. this will be true for `?int $myParam = null`, but false for `int $myParam = null`
     *
     * This is needed to deal with edge cases of analysis.
     */
    public function isUsingNullableSyntax(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_PARAM_USING_NULLABLE_SYNTAX);
    }

    public function __toString(): string
    {
        $string = '';

        $union_type = $this->getNonVariadicUnionType();
        if (!$union_type->isEmpty()) {
            $string .= $union_type->__toString() . ' ';
        }

        if ($this->isPassByReference()) {
            $string .= '&';
        }

        if ($this->isVariadic()) {
            $string .= '...';
        }

        $string .= "\${$this->getName()}";

        if ($this->hasDefaultValue() && !$this->isVariadic()) {
            $default_value = $this->default_value;
            if ($default_value instanceof Node) {
                $string .= ' = null';
            } else {
                $string .= ' = ' . StringUtil::varExportPretty($default_value);
            }
        }

        return $string;
    }

    /**
     * Convert this parameter to a stub that can be used by `tool/make_stubs`
     *
     * @param bool $is_internal is this being requested for the language server instead of real PHP code?
     * @suppress PhanAccessClassConstantInternal
     */
    public function toStubString(bool $is_internal = false): string
    {
        $string = '';

        $union_type = $this->getNonVariadicUnionType();
        if (!$union_type->isEmpty()) {
            $string .= $union_type->__toString() . ' ';
        }

        if ($this->isPassByReference()) {
            $string .= '&';
        }

        if ($this->isVariadic()) {
            $string .= '...';
        }

        $name = $this->getName();
        if (!\preg_match('@' . Builder::WORD_REGEX . '@', $name)) {
            // Some PECL extensions have invalid parameter names.
            // Replace invalid characters with U+FFFD replacement character.
            $name = \preg_replace('@[^a-zA-Z0-9_\x7f-\xff]@', '�', $name);
            if (!\preg_match('@' . Builder::WORD_REGEX . '@', $name)) {
                $name = '_' . $name;
            }
        }

        $string .= "\$$name";

        if ($this->hasDefaultValue() && !$this->isVariadic()) {
            $default_value = $this->default_value;
            if ($default_value instanceof Node) {
                $kind = $default_value->kind;
                if ($kind === \ast\AST_NAME) {
                    $default_repr = (string)$default_value->children['name'];
                } elseif ($kind === \ast\AST_ARRAY) {
                    $default_repr = '[]';
                } else {
                    $default_repr = 'null';
                }
            } else {
                $default_repr = StringUtil::varExportPretty($default_value);
            }
            if (\strtolower($default_repr) === 'null') {
                $default_repr = 'null';
                // If we're certain the parameter isn't nullable,
                // then render the default as `default`, not `null`
                if ($is_internal) {
                    if (!$union_type->isEmpty() && !$union_type->containsNullable()) {
                        $default_repr = 'default';
                    }
                }
            }
            $string .= ' = ' . $default_repr;
        }

        return $string;
    }

    /**
     * Convert this parameter to a stub that can be used for issue messages.
     *
     * @suppress PhanAccessClassConstantInternal
     */
    public function getShortRepresentationForIssue(bool $is_internal = false): string
    {
        $string = '';

        $union_type_string = $this->getUnionTypeRepresentationForIssue();
        if ($union_type_string !== '') {
            $string = $union_type_string . ' ';
        }
        if ($this->isPassByReference()) {
            $string .= '&';
        }

        if ($this->isVariadic()) {
            $string .= '...';
        }

        $name = $this->getName();
        if (!\preg_match('@' . Builder::WORD_REGEX . '@', $name)) {
            // Some PECL extensions have invalid parameter names.
            // Replace invalid characters with U+FFFD replacement character.
            $name = \preg_replace('@[^a-zA-Z0-9_\x7f-\xff]@', '�', $name);
            if (!\preg_match('@' . Builder::WORD_REGEX . '@', $name)) {
                $name = '_' . $name;
            }
        }

        $string .= "\$$name";

        if ($this->hasDefaultValue() && !$this->isVariadic()) {
            $default_value = $this->default_value;
            if ($default_value instanceof Node) {
                $kind = $default_value->kind;
                if ($kind === \ast\AST_NAME) {
                    $default_repr = (string)$default_value->children['name'];
                } elseif ($kind === \ast\AST_ARRAY) {
                    $default_repr = '[]';
                } else {
                    $default_repr = 'null';
                }
            } else {
                $default_repr = StringUtil::varExportPretty($default_value);
                if (strlen($default_repr) >= 50) {
                    $default_repr = 'default';
                }
            }
            if (\strtolower($default_repr) === 'null') {
                $default_repr = 'null';
                // If we're certain the parameter isn't nullable,
                // then render the default as `default`, not `null`
                if ($is_internal) {
                    $union_type = $this->getNonVariadicUnionType();
                    if (!$union_type->isEmpty() && !$union_type->containsNullable()) {
                        $default_repr = 'default';
                    }
                }
            }
            $string .= ' = ' . $default_repr;
        }

        return $string;
    }

    /**
     * Returns a limited length union type representation for issue messages.
     * Long types are truncated or omitted.
     */
    private function getUnionTypeRepresentationForIssue(): string
    {
        $union_type = $this->getNonVariadicUnionType()->asNormalizedTypes();
        if ($union_type->isEmpty()) {
            return '';
        }
        $real_union_type = $union_type->getRealUnionType();
        if ($union_type->typeCount() >= 3) {
            if (!$real_union_type->isEmpty()) {
                $real_union_type_string = $real_union_type->__toString();
                if (strlen($real_union_type_string) <= 100) {
                    return $real_union_type_string;
                }
            }
            return '';
        }

        // TODO: hide template types, generic array or real array types
        $union_type_string = $union_type->__toString();
        if ($union_type_string === 'mixed') {
            return '';
        }
        if (strlen($union_type_string) < 100) {
            return $union_type_string;
        }
        $real_union_type_string = $real_union_type->__toString();
        if (strlen($real_union_type_string) <= 100) {
            return $real_union_type_string;
        }
        return '';
    }

    /**
     * Converts this to a ClosureDeclarationParameter that can be used in FunctionLikeDeclarationType instances.
     *
     * E.g. when analyzing code such as `$x = Closure::fromCallable('some_function')`,
     * this is used on parameters of `some_function()` to infer the create the parameter types of the inferred type.
     */
    public function asClosureDeclarationParameter(): ClosureDeclarationParameter
    {
        $param_type = $this->getNonVariadicUnionType();
        if ($param_type->isEmpty()) {
            $param_type = MixedType::instance(false)->asPHPDocUnionType();
        }
        return new ClosureDeclarationParameter(
            $param_type,
            $this->isVariadic(),
            $this->isPassByReference(),
            $this->isOptional()
        );
    }

    /**
     * @param FunctionInterface $function - The function that has this Parameter.
     * @return Context a Context with the line number of this parameter
     */
    public function createContext(FunctionInterface $function): Context
    {
        return clone($function->getContext())->withLineNumberStart($this->getFileRef()->getLineNumberStart());
    }

    /**
     * Returns true if the non-variadic type of this declared parameter is empty.
     * e.g. `$x`, `...$y`
     */
    public function hasEmptyNonVariadicType(): bool
    {
        return self::getUnionType()->isEmpty();
    }
}
