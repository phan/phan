<?php declare(strict_types=1);
namespace Phan\Language;

use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\GenericMultiArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\TrueType;
use ast\Node;

class UnionType implements \Serializable
{
    /**
     * @var string
     * A list of one or more types delimited by the '|'
     * character (e.g. 'int|DateTime|string[]')
     */
    const union_type_regex =
        Type::type_regex
        . '(\|' . Type::type_regex . ')*';

    /**
     * @var string
     * A list of one or more types delimited by the '|'
     * character (e.g. 'int|DateTime|string[]' or 'null|$this')
     * This may be used for return types.
     *
     * TODO: Equivalent variants with no capturing? (May not improve performance much)
     */
    const union_type_regex_or_this =
        Type::type_regex_or_this
        . '(\|' . Type::type_regex_or_this . ')*';

    /**
     * @var Type[] - [int $type_object_id => Type $type]
     */
    private $type_set;

    /**
     * @param Type[] $type_list
     * @param bool $is_unique - Whether or not this is already unique. Only set to true within UnionSet code.
     *
     * An optional list of types represented by this union
     */
    public function __construct(array $type_list = [], bool $is_unique = false)
    {
        $this->type_set = ($is_unique || \count($type_list) <= 1) ? $type_list : self::getUniqueTypes($type_list);
    }

    // __clone of $this->type_set would be a no-op due to copy on write semantics.

    /**
     * @param string $fully_qualified_string
     * A '|' delimited string representing a type in the form
     * 'int|string|null|ClassName'.
     *
     * @return UnionType
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) : UnionType {
        if ($fully_qualified_string === '') {
            return new UnionType();
        }

        static $memoize_map = [];
        $type_set = $memoize_map[$fully_qualified_string] ?? null;

        if (!isset($type_set)) {
            $types = \array_map(function (string $type_name) {
                return Type::fromFullyQualifiedString($type_name);
            }, self::extractTypeParts($fully_qualified_string));

            // TODO: Support brackets, template types within <>, etc.
            $type_set = self::getUniqueTypes(self::normalizeGenericMultiArrayTypes($types));
            $memoize_map[$fully_qualified_string] = $type_set;
        }

        return new UnionType($type_set, true);
    }

    /**
     * @param Type[] $type_list
     * @return Type[]
     */
    public static function getUniqueTypes(array $type_list) : array
    {
        $new_type_list = [];
        foreach ($type_list as $type) {
            if (!\in_array($type, $new_type_list, true)) {
                $new_type_list[] = $type;
            }
        }
        return $new_type_list;
    }

    /**
     * @param string $type_string
     * A '|' delimited string representing a type in the form
     * 'int|string|null|ClassName'.
     *
     * @param Context $context
     * The context in which the type string was
     * found
     *
     * @param int $source one of the constants in Type::FROM_*
     *
     * @return UnionType
     */
    public static function fromStringInContext(
        string $type_string,
        Context $context,
        int $source
    ) : UnionType {
        if (empty($type_string)) {
            return new UnionType();
        }

        // If our scope has a generic type identifier defined on it
        // that matches the type string, return that UnionType.
        if ($context->getScope()->hasTemplateType($type_string)) {
            return $context->getScope()->getTemplateType(
                $type_string
            )->asUnionType();
        }
        $types = \array_map(
            function (string $type_name) use ($context, $source) : Type {
                \assert($type_name !== '', "Type cannot be empty.");
                return Type::fromStringInContext(
                    $type_name,
                    $context,
                    $source
                );
            },
            \array_filter(self::extractTypeParts($type_string), function (string $type_name) {
                // Exclude empty type names
                // Exclude namespaces without type names (e.g. `\`, `\NS\`)
                return $type_name !== '' && \preg_match('@\\\\[\[\]]*$@', $type_name) === 0;
            })
        );

        return new UnionType(self::normalizeGenericMultiArrayTypes($types));
    }

    /**
     * @return string[]
     */
    private static function extractTypeParts(string $type_string) : array
    {
        $parts = \array_map('trim', \explode('|', $type_string));
        if (\count($parts) <= 1) {
            return $parts;
        }
        if (!\preg_match('/[<(]/', $type_string)) {
            return $parts;
        }
        return self::mergeTypeParts($parts);
    }

    /**
     * Expands any GenericMultiArrayType instances in $types if necessary.
     *
     * @param Type[] $types
     * @return Type[]
     */
    private static function normalizeGenericMultiArrayTypes(array $types) : array
    {
        foreach ($types as $i => $type) {
            if ($type instanceof GenericMultiArrayType) {
                foreach ($type->asGenericArrayTypeInstances() as $new_type) {
                    $types[] = $new_type;
                }
                unset($types[$i]);
            }
        }
        return $types;
    }

    /**
     * @param string[] $parts (already trimmed)
     * @return string[]
     * @see Type::extractTemplateParameterTypeNameList (Similar method)
     */
    private static function mergeTypeParts(array $parts) : array
    {
        $prev_parts = [];
        $delta = 0;
        $results = [];
        foreach ($parts as $part) {
            if (\count($prev_parts) > 0) {
                $prev_parts[] = $part;
                $delta += \substr_count($part, '<') + \substr_count($part, '(') - \substr_count($part, '>') - \substr_count($part, ')');
                if ($delta <= 0) {
                    if ($delta === 0) {
                        $results[] = \implode('|', $prev_parts);
                    }  // ignore unparseable data such as "<T,T2>>"
                    $prev_parts = [];
                    $delta = 0;
                    continue;
                }
                continue;
            }
            $bracket_count = \substr_count($part, '<') + \substr_count($part, '(');
            if ($bracket_count === 0) {
                $results[] = $part;
                continue;
            }
            $delta = $bracket_count - \substr_count($part, '>') - \substr_count($part, ')');
            if ($delta === 0) {
                $results[] = $part;
            } elseif ($delta > 0) {
                $prev_parts[] = $part;
            }  // otherwise ignore unparseable data such as ">" (should be impossible)
        }
        return $results;
    }

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Node|string|bool|int|float|null $node
     * The node for which we'd like to determine its type
     *
     * @param bool $should_catch_issue_exception
     * Set to true to cause loggable issues to be thrown
     * instead of emitted as issues to the log.
     *
     * @return UnionType
     *
     * @throws IssueException
     * If $should_catch_issue_exception is false an IssueException may
     * be thrown for optional issues.
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        $node,
        bool $should_catch_issue_exception = true
    ) : UnionType {
        return UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node,
            $should_catch_issue_exception
        );
    }

    /**
     * @param ?\ReflectionType $reflection_type
     *
     * @return UnionType
     * A UnionType with 0 or 1 nullable/non-nullable Types
     */
    public static function fromReflectionType($reflection_type) : UnionType
    {
        if ($reflection_type !== null) {
            return Type::fromReflectionType($reflection_type)->asUnionType();
        }
        return new UnionType();
    }

    /**
     * @return string[]
     * Get a map from property name to its type for the given
     * class name.
     */
    public static function internalPropertyMapForClassName(
        string $class_name
    ) : array {
        $map = self::internalPropertyMap();

        $canonical_class_name = \strtolower($class_name);

        return $map[$canonical_class_name] ?? [];
    }

    /**
     * @return array
     * A map from builtin class properties to type information
     *
     * @see \Phan\Language\Internal\PropertyMap
     */
    private static function internalPropertyMap() : array
    {
        static $map = [];

        if (!$map) {
            $map_raw = require(__DIR__.'/Internal/PropertyMap.php');
            foreach ($map_raw as $key => $value) {
                $map[\strtolower($key)] = $value;
            }

            // Merge in an empty type for dynamic properties on any
            // classes listed as supporting them.
            foreach (require(__DIR__.'/Internal/DynamicPropertyMap.php') as $class_name) {
                $map[\strtolower($class_name)]['*'] = '';
            }
        }

        return $map;
    }

    /**
     * A list of types for parameters associated with the
     * given builtin function with the given name
     *
     * @param FullyQualifiedMethodName|FullyQualifiedFunctionName $function_fqsen
     *
     * @see internal_varargs_check
     * Formerly `function internal_varargs_check`
     */
    public static function internalFunctionSignatureMapForFQSEN(
        $function_fqsen
    ) : array {
        $map = self::internalFunctionSignatureMap();

        if ($function_fqsen instanceof FullyQualifiedMethodName) {
            $class_fqsen =
                $function_fqsen->getFullyQualifiedClassName();
            $class_name = $class_fqsen->getName();
            $function_name =
                $class_name . '::' . $function_fqsen->getName();
        } else {
            $function_name = $function_fqsen->getNamespacedName();
        }

        $function_name = \strtolower($function_name);

        $function_name_original = $function_name;
        $alternate_id = 0;

        /**
         * @param string|null $type_name
         * @return UnionType|null
         */
        $get_for_global_context = function ($type_name) {
            if (!$type_name) {
                return null;
            }

            static $internal_fn_cache = [];


            $result = $internal_fn_cache[$type_name] ?? null;
            if ($result === null) {
                $context = new Context;
                $result = UnionType::fromStringInContext($type_name, $context, Type::FROM_PHPDOC);
                $internal_fn_cache[$type_name] = $result;
            }
            return clone($result);
        };

        $configurations = [];
        while (isset($map[$function_name])) {
            // Get some static data about the function
            $type_name_struct = $map[$function_name];
            if (empty($type_name_struct)) {
                continue;
            }

            // Figure out the return type
            $return_type_name = \array_shift($type_name_struct);
            $return_type = $get_for_global_context($return_type_name);

            $name_type_name_map = $type_name_struct;
            $parameter_name_type_map = [];

            foreach ($name_type_name_map as $name => $type_name) {
                $parameter_name_type_map[$name] = $get_for_global_context($type_name) ?? new UnionType();
            }

            $configurations[] = [
                'return_type' => $return_type,
                'parameter_name_type_map' => $parameter_name_type_map,
            ];

            $function_name =
                $function_name_original . '\'' . (++$alternate_id);
        }

        return $configurations;
    }

    /**
     * @return Type[]
     * The list of simple types associated with this
     * union type. Keys are consecutive.
     */
    public function getTypeSet() : array
    {
        return $this->type_set;
    }

    /**
     * Add a type name to the list of types
     *
     * @return void
     */
    public function addType(Type $type)
    {
        if (!\in_array($type, $this->type_set, true)) {
            $this->type_set[] = $type;
        }
    }

    /**
     * Remove a type name from the list of types,
     * keeping the keys in a consecutive order.
     *
     * Each type in $this->type_set occurs exactly once.
     *
     * @return void
     */
    public function removeType(Type $type)
    {
        $i = \array_search($type, $this->type_set, true);
        if ($i === false) {
            return;
        }
        $replacement_type = \array_pop($this->type_set);
        if ($replacement_type !== $type) {
            $this->type_set[$i] = $replacement_type;
        }
    }

    /**
     * @return bool
     * True if this union type contains the given named
     * type.
     */
    public function hasType(Type $type) : bool
    {
        return \in_array($type, $this->type_set, true);
    }

    /**
     * Add the given types to this type
     *
     * @return void
     */
    public function addUnionType(UnionType $union_type)
    {
        $type_set = $this->type_set;
        if (count($type_set) === 0) {
            // take advantage of array copy-on-write to save a bit of memory
            $this->type_set = $union_type->type_set;
        } else {
            foreach ($union_type->type_set as $type) {
                if (!\in_array($type, $type_set, true)) {
                    $this->type_set[] = $type;
                }
            }
        }
    }

    /**
     * @return bool
     * True if this type has a type referencing the
     * class context in which it exists such as 'self'
     * or '$this'
     */
    public function hasSelfType() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isSelfType();
        });
    }

    /**
     * @return bool
     * True if this union type has any types that are bool/false/true types
     */
    public function hasTypeInBoolFamily() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->getIsInBoolFamily();
        });
    }

    /**
     * @return UnionType[]
     * A map from template type identifiers to the UnionType
     * to replace it with
     */
    public function getTemplateParameterTypeList() : array
    {
        if ($this->isEmpty()) {
            return [];
        }

        return \array_reduce(
            $this->type_set,
            function (array $map, Type $type) {
                return \array_merge(
                    $type->getTemplateParameterTypeList(),
                    $map
                );
            },
            []
        );
    }


    /**
     * @param CodeBase $code_base
     * The code base to look up classes against
     *
     * TODO: Defer resolving the template parameters until parse ends. Low priority.
     *
     * @return UnionType[]
     * A map from template type identifiers to the UnionType
     * to replace it with
     */
    public function getTemplateParameterTypeMap(
        CodeBase $code_base
    ) : array {
        if ($this->isEmpty()) {
            return [];
        }

        return \array_reduce(
            $this->type_set,
            function (array $map, Type $type) use ($code_base) {
                return \array_merge(
                    $type->getTemplateParameterTypeMap($code_base),
                    $map
                );
            },
            []
        );
    }


    /**
     * @param UnionType[] $template_parameter_type_map
     * A map from template type identifiers to concrete types
     *
     * @return UnionType
     * This UnionType with any template types contained herein
     * mapped to concrete types defined in the given map.
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ) : UnionType {

        $concrete_type_list = [];
        foreach ($this->type_set as $type) {
            if ($type instanceof TemplateType
                && isset($template_parameter_type_map[$type->getName()])
            ) {
                $union_type =
                    $template_parameter_type_map[$type->getName()];

                foreach ($union_type->type_set as $concrete_type) {
                    $concrete_type_list[] = $concrete_type;
                }
            } else {
                $concrete_type_list[] = $type;
            }
        }

        return new UnionType($concrete_type_list);
    }

    /**
     * @return bool
     * True if this union type has any types that are generic
     * types
     */
    public function hasTemplateType() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return ($type instanceof TemplateType);
        });
    }

    /**
     * @return bool
     * True if this type has a type referencing the
     * class context 'static'.
     */
    public function hasStaticType() : bool
    {
        static $static_types = null;
        if ($static_types === null) {
            $static_types = [
                StaticType::instance(false),
                StaticType::instance(true),
            ];
        }
        return $this->hasAnyType($static_types);
    }

    /**
     * @return UnionType
     * A new UnionType with any references to 'static' resolved
     * in the given context.
     */
    public function withStaticResolvedInContext(
        Context $context
    ) : UnionType {

        // If the context isn't in a class scope, there's nothing
        // we can do
        if (!$context->isInClassScope()) {
            return $this;
        }

        static $static_type;
        static $static_nullable_type;
        if ($static_type === null) {
            $static_type = StaticType::instance(false);
            $static_nullable_type = StaticType::instance(true);
        }

        $has_static_type = \in_array($static_type, $this->type_set, true);
        $has_static_nullable_type = \in_array($static_nullable_type, $this->type_set, true);

        // If this doesn't reference 'static', there's nothing to do.
        if (!($has_static_type || $has_static_nullable_type)) {
            return $this;
        }

        // Get a copy of this UnionType to avoid having to know
        // who has copies of it out in the wild and what they're
        // hoping for.
        $union_type = clone($this);

        // Remove the static type
        if ($has_static_type) {
            $union_type->removeType($static_type);

            // Add in the class in scope
            $union_type->addType($context->getClassFQSEN()->asType());
        } else {
            $union_type->removeType($static_type);

            // Add in the nullable class in scope
            $union_type->addType($context->getClassFQSEN()->asType()->withIsNullable(true));
        }

        return $union_type;
    }

    /**
     * @return bool
     * True if and only if this UnionType contains
     * the given type and no others.
     */
    public function isType(Type $type) : bool
    {
        $type_set = $this->type_set;
        if (\count($type_set) !== 1) {
            return false;
        }

        return \reset($type_set) === $type;
    }

    /**
     * @return bool
     * True if this UnionType is exclusively native
     * types
     */
    public function isNativeType() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return !$type->isNativeType();
        });
    }

    /**
     * @return bool
     * True iff this union contains the exact set of types
     * represented in the given union type.
     */
    public function isEqualTo(UnionType $union_type) : bool
    {
        $type_set = $this->type_set;
        $other_type_set = $union_type->type_set;
        /**
        assert(ArraySet::is_array_set($type_set));
        assert(ArraySet::is_array_set($other_type_set));
         */
        if (\count($type_set) !== \count($other_type_set)) {
            return false;
        }
        foreach ($type_set as $type) {
            if (!\in_array($type, $other_type_set, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool
     * True iff this union contains a type that's also in
     * the other union type.
     */
    public function hasCommonType(UnionType $union_type) : bool
    {
        $other_type_set = $union_type->type_set;
        foreach ($this->type_set as $type) {
            if (\in_array($type, $other_type_set, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool - True if not empty and at least one type is NullType or nullable.
     */
    public function containsNullable() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->getIsNullable()) {
                return true;
            }
        }
        return false;
    }

    public function nonNullableClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->type_set as $type) {
            if (!$type->getIsNullable()) {
                $result->addType($type);
                continue;
            }
            if ($type === NullType::instance(false)) {
                continue;
            }

            $result->addType($type->withIsNullable(false));
        }
        return $result;
    }

    public function nullableClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->type_set as $type) {
            if ($type->getIsNullable()) {
                $result->addType($type);
                continue;
            }

            $result->addType($type->withIsNullable(true));
        }
        return $result;
    }

    /**
     * @return bool - True if type set is not empty and at least one type is NullType or nullable or FalseType or BoolType.
     * (I.e. the type is always falsey, or both sometimes falsey with a non-falsey type it can be narrowed down to)
     * This does not include values such as `IntType`, since there is currently no `NonZeroIntType`.
     */
    public function containsFalsey() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->getIsPossiblyFalsey()) {
                return true;
            }
        }
        return false;
    }

    public function nonFalseyClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyFalsey()) {
                $result->addType($type);
                continue;
            }
            if ($type->getIsAlwaysFalsey()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $result->addType($type->asNonFalseyType());
        }
        return $result;
    }

    /**
     * @return bool - True if type set is not empty and at least one type is NullType or nullable or FalseType or BoolType.
     * (I.e. the type is always falsey, or both sometimes falsey with a non-falsey type it can be narrowed down to)
     * This does not include values such as `IntType`, since there is currently no `NonZeroIntType`.
     */
    public function containsTruthy() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->getIsPossiblyTruthy()) {
                return true;
            }
        }
        return false;
    }

    public function nonTruthyClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyTruthy()) {
                $result->addType($type);
                continue;
            }
            if ($type->getIsAlwaysTruthy()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $result->addType($type->asNonTruthyType());
        }
        return $result;
    }

    /**
     * @return bool - True if type set is not empty and at least one type is BoolType or FalseType
     */
    public function containsFalse() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->getIsPossiblyFalse()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool - True if type set is not empty and at least one type is BoolType or TrueType
     */
    public function containsTrue() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->getIsPossiblyTrue()) {
                return true;
            }
        }
        return false;
    }

    public function nonFalseClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyFalse()) {
                $result->addType($type);
                continue;
            }
            if ($type->getIsAlwaysFalse()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $result->addType($type->asNonFalseType());
        }
        return $result;
    }

    public function nonTrueClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyTrue()) {
                $result->addType($type);
                continue;
            }
            if ($type->getIsAlwaysTrue()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $result->addType($type->asNonTrueType());
        }
        return $result;
    }

    /**
     * @param UnionType $union_type
     * A union type to compare against
     *
     * @param Context $context
     * The context in which this type exists.
     *
     * @param CodeBase $code_base
     * The code base in which both this and the given union
     * types exist.
     *
     * @return bool
     * True if each type within this union type can cast
     * to the given union type.
     */
    // Currently unused and buggy, commenting this out.
    /**
    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $context,
        CodeBase $code_base
    ) : bool {

        // Special rule: anything can cast to nothing
        // and nothing can cast to anything
        if ($union_type->isEmpty() || $this->isEmpty()) {
            return true;
        }

        // Check to see if the types are equivalent
        if ($this->isEqualTo($union_type)) {
            return true;
        }
        // TODO: Allow casting MyClass<TemplateType> to MyClass (Without the template?

        // Resolve 'static' for the given context to
        // determine whats actually being referred
        // to in concrete terms.
        $other_resolved_type =
            $union_type->withStaticResolvedInContext($context);
        $other_resolved_type_set = $other_resolved_type->type_set;

        // Convert this type to a set of resolved types to iterate over.
        $this_resolved_type_set =
            $this->withStaticResolvedInContext($context)->type_set;

        // TODO: Need to resolve expanded union types (parents, interfaces) of classes *before* this is called.

        // Test to see if every single type in this union
        // type can cast to the given union type.
        foreach ($this_resolved_type_set as $type) {
            // First check if this contains the type as an optimization.
            if ($other_resolved_type_set->contains($type)) {
                continue;
            }
            $expanded_types = $type->asExpandedTypes($code_base);
            if ($other_resolved_type->canCastToUnionType(
                $expanded_types
            )) {
                continue;
            }
        }
        return true;
    }
     */

    /**
     * @param Type[] $type_list
     * A list of types
     *
     * @return bool
     * True if this union type contains any of the given
     * named types
     */
    public function hasAnyType(array $type_list) : bool
    {
        $type_set = $this->type_set;
        if (\count($type_set) === 0) {
            return false;
        }
        foreach ($type_list as $type) {
            if (\in_array($type, $type_set, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     * True if this type has any subtype of `iterable` type (e.g. Traversable, Array).
     */
    public function hasIterable() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isIterable();
        });
    }

    /**
     * @return int
     * The number of types in this union type
     */
    public function typeCount() : int
    {
        return \count($this->type_set);
    }

    /**
     * @return bool
     * True if this Union has no types
     */
    public function isEmpty() : bool
    {
        return \count($this->type_set) === 0;
    }

    /**
     * @param UnionType $target
     * The type we'd like to see if this type can cast
     * to
     *
     * @param CodeBase $code_base
     * The code base used to expand types
     *
     * @return bool
     * Test to see if this type can be cast to the
     * given type after expanding both union types
     * to include all ancestor types
     *
     * TODO: ensure that this is only called after the parse phase is over.
     */
    public function canCastToExpandedUnionType(
        UnionType $target,
        CodeBase $code_base
    ) : bool {

        $this_expanded =
            $this->asExpandedTypes($code_base);

        $target_expanded =
            $target->asExpandedTypes($code_base);

        return
            $this_expanded->canCastToUnionType(
                $target_expanded
            );
    }

    /**
     * @param UnionType $target
     * A type to check to see if this can cast to it
     *
     * @return bool
     * True if this type is allowed to cast to the given type
     * i.e. int->float is allowed  while float->int is not.
     */
    public function canCastToUnionType(
        UnionType $target
    ) : bool {
        // Fast-track most common cases first

        // If either type is unknown, we can't call it
        // a success
        if ($this->isEmpty() || $target->isEmpty()) {
            return true;
        }

        // T overlaps with T, a future call to Type->canCastToType will pass.
        if ($this->hasCommonType($target)) {
            return true;
        }
        static $float_type;
        static $int_type;
        static $mixed_type;
        static $null_type;
        if ($null_type === null) {
            $int_type   = IntType::instance(false);
            $float_type = FloatType::instance(false);
            $mixed_type = MixedType::instance(false);
            $null_type  = NullType::instance(false);
        }

        if (Config::get_null_casts_as_any_type()) {
            // null <-> null
            if ($this->isType($null_type)
                || $target->isType($null_type)
            ) {
                return true;
            }
        } else {
            // If null_casts_as_any_type isn't set, then try the other two fallbacks.
            if (Config::get_null_casts_as_array() && $this->isType($null_type) && $target->hasArrayLike()) {
                return true;
            } elseif (Config::get_array_casts_as_null() && $target->isType($null_type) && $this->hasArrayLike()) {
                return true;
            }
        }

        // mixed <-> mixed
        if ($target->hasType($mixed_type)
            || $this->hasType($mixed_type)
        ) {
            return true;
        }

        // int -> float
        if ($this->hasType($int_type)
            && $target->hasType($float_type)
        ) {
            return true;
        }

        // Check conversion on the cross product of all
        // type combinations and see if any can cast to
        // any.
        foreach ($this->type_set as $source_type) {
            foreach ($target->type_set as $target_type) {
                if ($source_type->canCastToType($target_type)) {
                    return true;
                }
            }
        }

        // Allow casting ?T to T|null for any type T. Check if null is part of this type first.
        if ($target->hasType($null_type)) {
            foreach ($this->type_set as $source_type) {
                // Only redo this check for the nullable types, we already failed the checks for non-nullable types.
                if ($source_type->getIsNullable()) {
                    $non_null_source_type = $source_type->withIsNullable(false);
                    foreach ($target->type_set as $target_type) {
                        if ($non_null_source_type->canCastToType($target_type)) {
                            return true;
                        }
                    }
                }
            }
        }

        // Only if no source types can be cast to any target
        // types do we say that we cannot perform the cast
        return false;
    }

    /**
     * @return bool
     * True if all types in this union are scalars
     */
    public function isScalar() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return !$type->isScalar();
        });
    }

    /**
     * @return bool
     * True if this union has array-like types (is of type array, is
     * a generic array, or implements ArrayAccess).
     */
    public function hasArrayLike() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isArrayLike();
        });
    }

    /**
     * @return bool
     * True if this union has array-like types (is of type array, is
     * a generic array, or implements ArrayAccess).
     */
    public function hasGenericArray() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isGenericArray();
        });
    }

    /**
     * @return bool
     * True if this union contains the ArrayAccess type.
     * (Call asExpandedTypes() first to check for subclasses of ArrayAccess)
     */
    public function hasArrayAccess() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isArrayAccess();
        });
    }

    /**
     * @return bool
     * True if this union type represents types that are
     * array-like, and nothing else (e.g. can't be null).
     * If any of the array-like types are nullable, this returns false.
     */
    public function isExclusivelyArrayLike() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return !$type->isArrayLike() || $type->getIsNullable();
        });
    }

    /**
     * @return bool
     * True if this union type represents types that are arrays
     * or generic arrays, but nothing else.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function isExclusivelyArray() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type !== ArrayType::instance(false) && !$type->isGenericArray();
        });
    }

    /**
     * @return UnionType
     * Get the subset of types which are not native
     */
    public function nonNativeTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) {
            return !$type->isNativeType();
        });
    }

    /**
     * A memory efficient way to create a UnionType from a filter operation.
     * If this the filter preserves everything, calls clone() instead.
     */
    public function makeFromFilter(\Closure $cb) : UnionType
    {
        $new_type_list = [];
        foreach ($this->type_set as $type) {
            if ($cb($type)) {
                $new_type_list[] = $type;
            }
        }
        if (\count($new_type_list) === \count($this->type_set)) {
            return clone($this);
        }
        return new UnionType($new_type_list, true);
    }

    /**
     * @param Context $context
     * The context in which we're resolving this union
     * type.
     *
     * @return \Generator
     *
     * A list of class FQSENs representing the non-native types
     * associated with this UnionType
     *
     * @throws CodeBaseException
     * An exception is thrown if a non-native type does not have
     * an associated class
     *
     * @throws IssueException
     * An exception is thrown if static is used as a type outside of an object
     * context
     *
     * TODO: Add a method to ContextNode to directly get FQSEN instead?
     */
    public function asClassFQSENList(
        Context $context
    ) {
        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($this->nonNativeTypes()->type_set as $class_type) {
            // Get the class FQSEN
            $class_fqsen = $class_type->asFQSEN();

            if ($class_type->isStaticType()) {
                if (!$context->isInClassScope()) {
                    throw new IssueException(
                        Issue::fromType(Issue::ContextNotObject)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                                $class_type->getName()
                            ]
                        )
                    );
                }
                yield $class_fqsen;
            } else {
                yield $class_fqsen;
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which to find classes
     *
     * @param Context $context
     * The context in which we're resolving this union
     * type.
     *
     * @return \Generator
     *
     * A list of classes representing the non-native types
     * associated with this UnionType
     *
     * @throws CodeBaseException
     * An exception is thrown if a non-native type does not have
     * an associated class
     *
     * @throws IssueException
     * An exception is thrown if static is used as a type outside of an object
     * context
     */
    public function asClassList(
        CodeBase $code_base,
        Context $context
    ) {
        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($this->nonNativeTypes()->type_set as $class_type) {
            // Get the class FQSEN
            $class_fqsen = $class_type->asClassFQSEN();

            if ($class_type->isStaticType()) {
                if (!$context->isInClassScope()) {
                    throw new IssueException(
                        Issue::fromType(Issue::ContextNotObject)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                                $class_type->getName()
                            ]
                        )
                    );
                }
                yield $context->getClassInScope($code_base);
            } else {
                if ($class_type->isSelfType()) {
                    if (!$context->isInClassScope()) {
                        throw new IssueException(
                            Issue::fromType(Issue::ContextNotObject)(
                                $context->getFile(),
                                $context->getLineNumberStart(),
                                [
                                    $class_type->getName()
                                ]
                            )
                        );
                    }
                    if (strcasecmp($class_type->getName(), 'self') === 0) {
                        yield $context->getClassInScope($code_base);
                    } else {
                        yield $class_type;
                    }
                    continue;
                }
                // See if the class exists
                if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                    throw new CodeBaseException(
                        $class_fqsen,
                        "Cannot find class $class_fqsen"
                    );
                }

                yield $code_base->getClassByFQSEN($class_fqsen);
            }
        }
    }

    /**
     * Takes "a|b[]|c|d[]|e" and returns "a|c|e"
     *
     * @return UnionType
     * A UnionType with generic array types filtered out
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function nonGenericArrayTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return !$type->isGenericArray();
        });
    }

    /**
     * Takes "a|b[]|c|d[]|e" and returns "b[]|d[]"
     *
     * @return UnionType
     * A UnionType with generic array types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @see genericArrayElementTypes
     */
    public function genericArrayTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->isGenericArray();
        });
    }

    /**
     * Takes "MyClass|int|array|?object" and returns "MyClass|?object"
     *
     * @return UnionType
     * A UnionType with known object types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @see genericArrayElementTypes
     */
    public function objectTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->isObject();
        });
    }

    /**
     * Returns true if objectTypes would be non-empty.
     *
     * @return bool
     */
    public function hasObjectTypes() : bool
    {
        return $this->hasTypeMatchingCallback((function (Type $type) : bool {
            return $type->isObject();
        }));
    }

    /**
     * Returns the types for which is_scalar($x) would be true.
     * This means null/nullable is removed.
     * Takes "MyClass|int|?bool|array|?object" and returns "int|bool"
     * Takes "?MyClass" and returns an empty union type.
     *
     * @return UnionType
     * A UnionType with known scalar types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @see genericArrayElementTypes
     */
    public function scalarTypes() : UnionType
    {
        // TODO: is_scalar(null) is false, account for that in analysis.
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->isScalar() && !($type instanceof NullType);
        });
    }

    /**
     * Returns the types for which is_callable($x) would be true.
     * TODO: Check for __invoke()?
     * Takes "Closure|false" and returns "Closure"
     * Takes "?MyClass" and returns an empty union type.
     *
     * @return UnionType
     * A UnionType with known callable types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @see genericArrayElementTypes
     */
    public function callableTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->isCallable();
        });
    }

    /**
     * Returns true if this has one or more callable types
     * TODO: Check for __invoke()?
     * Takes "Closure|false" and returns true
     * Takes "?MyClass" and returns false
     *
     * @return bool
     * A UnionType with known callable types kept, other types filtered out.
     *
     * @see $this->callableTypes()
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function hasCallableType() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isCallable();
        });
    }

    /**
     * Returns true if every type in this type is callable.
     * TODO: Check for __invoke()?
     * Takes "callable" and returns true
     * Takes "callable|false" and returns false
     *
     * @return bool
     * A UnionType with known callable types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @see genericArrayElementTypes
     */
    public function isExclusivelyCallable() : bool
    {
        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return !$type->isCallable();
        });
    }

    /**
     * Takes "a|b[]|c|d[]|e|array|ArrayAccess" and returns "a|c|e|ArrayAccess"
     *
     * @return UnionType
     * A UnionType with generic types(as well as the non-generic type "array")
     * filtered out.
     *
     * @see nonGenericArrayTypes
     */
    public function nonArrayTypes() : UnionType
    {
        return $this->makeFromFilter(
            function (Type $type) : bool {
                return !$type->isGenericArray()
                    && $type !== ArrayType::instance(false);
            }
        );
    }

    /**
     * @return bool
     * True if this is exclusively generic types
     */
    public function isGenericArray() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return !$type->isGenericArray();
        });
    }

    /**
     * @return bool
     * True if any of the types in this UnionType made $matcher_callback return true
     */
    public function hasTypeMatchingCallback(\Closure $matcher_callback) : bool
    {
        foreach ($this->type_set as $type) {
            if ($matcher_callback($type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Type|false
     * Returns the first type in this UnionType made $matcher_callback return true
     */
    public function findTypeMatchingCallback(\Closure $matcher_callback)
    {
        foreach ($this->type_set as $type) {
            if ($matcher_callback($type)) {
                return $type;
            }
        }
        return false;
    }

    /**
     * Takes "a|b[]|c|d[]|e" and returns "b|d"
     *
     * @return UnionType
     * The subset of types in this
     */
    public function genericArrayElementTypes() : UnionType
    {
        $union_type = new UnionType(
            \array_map(function (Type $type) : Type {
                return $type->genericArrayElementType();
            }, \array_filter($this->type_set, function (Type $type) : bool {
                return $type->isGenericArray();
            }))
        );

        // If array is in there, then it can be any type
        // Same for mixed
        if ($this->hasType(ArrayType::instance(false))
            || $this->hasType(MixedType::instance(false))
            || (
                Config::get_null_casts_as_any_type()
                && $this->hasType(ArrayType::instance(true))
            )
        ) {
            $union_type->addType(MixedType::instance(false));
        }

        if ($this->hasType(ArrayType::instance(false))) {
            $union_type->addType(NullType::instance(false));
        }

        return $union_type;
    }

    /**
     * Takes "b|d[]" and returns "b[]|d[][]"
     *
     * @return UnionType
     * The subset of types in this
     */
    public function elementTypesToGenericArray() : UnionType
    {
        return new UnionType(
            \array_map(function (Type $type) : Type {
                if ($type instanceof MixedType) {
                    return ArrayType::instance(false);
                }
                return GenericArrayType::fromElementType($type, false);
            }, $this->type_set)
        );
    }

    /**
     * @param \Closure $closure
     * A closure mapping `Type` to `Type`
     *
     * @return UnionType
     * A new UnionType with each type mapped through the
     * given closure
     */
    public function asMappedUnionType(\Closure $closure) : UnionType
    {
        return new UnionType(\array_map($closure, $this->type_set));
    }

    /**
     * @return UnionType
     * Get a new type for each type in this union which is
     * the generic array version of this type. For instance,
     * 'int|float' will produce 'int[]|float[]'.
     *
     * If $this is an empty UnionType, this method will produce an empty UnionType
     */
    public function asGenericArrayTypes() : UnionType
    {
        return $this->asMappedUnionType(
            function (Type $type) : Type {
                return $type->asGenericArrayType();
            }
        );
    }

    /**
     * @return UnionType
     * Get a new type for each type in this union which is
     * the generic array version of this type. For instance,
     * 'int|float' will produce 'int[]|float[]'.
     *
     * If $this is an empty UnionType, this method will produce 'array'
     */
    public function asNonEmptyGenericArrayTypes() : UnionType
    {
        if (\count($this->type_set) === 0) {
            return ArrayType::instance(false)->asUnionType();
        }
        return $this->asMappedUnionType(
            function (Type $type) : Type {
                return $type->asGenericArrayType();
            }
        );
    }
    /**
     * @param CodeBase
     * The code base to use in order to find super classes, etc.
     *
     * @param $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands all class types to all inherited classes returning
     * a superset of this type.
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ) : UnionType {
        \assert(
            $recursion_depth < 10,
            "Recursion has gotten out of hand"
        );

        $union_type = new UnionType();
        foreach ($this->type_set as $type) {
            $union_type->addUnionType(
                $type->asExpandedTypes(
                    $code_base,
                    $recursion_depth + 1
                )
            );
        }
        return $union_type;
    }

    /**
     * As per the Serializable interface
     *
     * @return string
     * A serialized representation of this type
     *
     * @see \Serializable
     */
    public function serialize() : string
    {
        return (string)$this;
    }

    /**
     * As per the Serializable interface
     *
     * @param string $serialized
     * A serialized UnionType
     *
     * @return void
     *
     * @see \Serializable
     */
    public function unserialize($serialized)
    {
        $this->type_set = self::getUniqueTypes(
            \array_map(function (string $type_name) : Type {
                return Type::fromFullyQualifiedString($type_name);
            }, \explode('|', $serialized ?? ''))
        );
    }

    /**
     * @return string
     * A human-readable string representation of this union
     * type
     */
    public function __toString() : string
    {
        // Create a new array containing the string
        // representations of each type
        $types = $this->type_set;
        $type_name_list =
            \array_map(function (Type $type) : string {
                return (string)$type;
            }, $types);

        // Sort the types so that we get a stable
        // representation
        \asort($type_name_list);

        // Join them with a pipe
        return \implode('|', $type_name_list);
    }

    /**
     * @return array
     * A map from builtin function name to type information
     *
     * @see \Phan\Language\Internal\FunctionSignatureMap
     */
    public static function internalFunctionSignatureMap()
    {
        static $map = [];

        if (!$map) {
            $map_raw = require(__DIR__.'/Internal/FunctionSignatureMap.php');
            foreach ($map_raw as $key => $value) {
                $map[\strtolower($key)] = $value;
            }
        }

        return $map;
    }

    /**
     * @return UnionType - A normalized version of this union type (May or may not be the same object, if no modifications were made)
     *
     * The following normalization rules apply
     *
     * 1. If one of the types is null or nullable, convert all types to nullable and remove "null" from the union type
     * 2. If both "true" and "false" (possibly nullable) coexist, or either coexists with "bool" (possibly nullable),
     *    then remove "true" and "false"
     */
    public function asNormalizedTypes() : UnionType
    {
        $type_set = $this->type_set;
        if (count($type_set) <= 1) {
            // Optimization: can't simplify if there's only one type
            return $this;
        }
        $flags = 0;
        foreach ($type_set as $type) {
            $flags |= $type->getNormalizationFlags();
        }
        if ($flags === 0) {
            // Optimization: nothing to do if no types are null/nullable or booleans
            return $this;
        }
        return self::asNormalizedTypesInner($type_set, $flags);
    }

    /**
     * @param Type[] $type_set
     * @param int $flags non-zero
     */
    public static function asNormalizedTypesInner(array $type_set, int $flags) : UnionType
    {
        $nullable = ($flags & Type::_bit_nullable) !== 0;
        if ($nullable) {
            $new_union_type = new UnionType();
            if (\count($type_set) > 0) {
                foreach ($type_set as $type) {
                    if ($type->getIsNullable()) {
                        $new_union_type->addType($type);
                    } else {
                        $new_union_type->addType($type->withIsNullable(true));
                    }
                }
                static $nullable_type = null;
                if ($nullable_type === null) {
                    $nullable_type = NullType::instance(false);
                }
                $new_union_type->removeType($nullable_type);
            }
        } else {
            $new_union_type = new UnionType($type_set);
        }
        // If this contains both true and false types, filter out both and add "bool" (or "?bool" for nullable)
        if (($flags & Type::_bit_bool_combination) === Type::_bit_bool_combination) {
            if ($nullable) {
                self::convertToTypeSetWithNormalizedNullableBools($new_union_type);
            } else {
                self::convertToTypeSetWithNormalizedNonNullableBools($new_union_type);
            }
        }
        return $new_union_type;
    }

    /**
     * @param UnionType[] $union_types
     * @return UnionType union of these UnionTypes
     */
    public static function merge(array $union_types) : UnionType
    {
        $new_type_set = [];
        foreach ($union_types as $type) {
            $type_set = $type->type_set;
            if (\count($type_set) === 0) {
                continue;
            }
            if (\count($new_type_set) === 0) {
                $new_type_set = $type_set;
                continue;
            }
            foreach ($type_set as $type) {
                if (!\in_array($type, $new_type_set, true)) {
                    $new_type_set[] = $type;
                }
            }
        }
        return new UnionType($new_type_set, true);
    }

    /**
     * Must be called after converting nullable to non-nullable.
     * Removes false|true types and adds bool
     *
     * @param UnionType $type (Containing only non-nullable values)
     * @return void
     * @var int $bool_id
     */
    private static function convertToTypeSetWithNormalizedNonNullableBools(UnionType $type)
    {
        static $true_type = null;
        static $false_type = null;
        static $bool_type = null;
        if ($bool_type === null) {
            $true_type = TrueType::instance(false);
            $false_type = FalseType::instance(false);
            $bool_type = BoolType::instance(false);
        }
        if (!$type->isEmpty()) {
            $type->removeType($true_type);
            $type->removeType($false_type);
        }

        $type->addType($bool_type);
    }

    /**
     * Must be called after converting all types to null.
     * Removes ?false|?true types and adds ?bool
     *
     * @param UnionType $type (Containing only non-nullable values)
     */
    private static function convertToTypeSetWithNormalizedNullableBools(UnionType $type)
    {
        static $true_type = null;
        static $false_type = null;
        static $bool_type = null;
        if ($bool_type === null) {
            $true_type = TrueType::instance(true);
            $false_type = FalseType::instance(true);
            $bool_type = BoolType::instance(true);
        }
        if (!$type->isEmpty()) {
            $type->removeType($true_type);
            $type->removeType($false_type);
        }

        $type->addType($bool_type);
    }
}
