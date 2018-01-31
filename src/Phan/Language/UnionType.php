<?php declare(strict_types=1);
namespace Phan\Language;

use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\GenericMultiArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\TrueType;
use ast\Node;

if (!\function_exists('spl_object_id')) {
    require_once __DIR__ . '/../../spl_object_id.php';
}

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
     * @var array<int,Type> * This is an immutable list of unique types.
     */
    private $type_set;

    /**
     * @param array<int,Type> $type_list
     * @param bool $is_unique - Whether or not this is already unique. Only set to true within UnionSet code.
     *
     * An optional list of types represented by this union
     */
    public function __construct(array $type_list = [], bool $is_unique = false)
    {
        $this->type_set = ($is_unique || \count($type_list) <= 1) ? $type_list : self::getUniqueTypes($type_list);
    }

    /**
     * @param Type[] $type_list
     * @return UnionType
     */
    public static function of(array $type_list)
    {
        $n = \count($type_list);
        if ($n === 0) {
            return self::$empty_instance;
        } elseif ($n === 1) {
            return \reset($type_list)->asUnionType();
        } else {
            return new self($type_list);
        }
    }

    private static function ofUniqueTypes(array $type_list)
    {
        $n = \count($type_list);
        if ($n === 0) {
            return self::$empty_instance;
        } elseif ($n === 1) {
            return \reset($type_list)->asUnionType();
        } else {
            return new self($type_list, true);
        }
    }

    /** @var EmptyUnionType */
    private static $empty_instance;

    /**
     * @return EmptyUnionType (Real return type omitted for performance)
     */
    public static function empty()
    {
        return self::$empty_instance;
    }

    /**
     * @return void
     * @internal
     */
    public static function init()
    {
        if (is_null(self::$empty_instance)) {
            self::$empty_instance = EmptyUnionType::instance();
        }
    }

    // __clone of $this->type_set would be a no-op due to copy on write semantics.
    // And clone isn't necessary anymore now that type_set is immutable

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
            return self::$empty_instance;
        }

        /** @var array<string,UnionType> annotation not read by phan */
        static $memoize_map = [];
        $union_type = $memoize_map[$fully_qualified_string] ?? null;

        if (is_null($union_type)) {
            $types = \array_map(function (string $type_name) {
                return Type::fromFullyQualifiedString($type_name);
            }, self::extractTypeParts($fully_qualified_string));

            $unique_types = self::getUniqueTypes(self::normalizeGenericMultiArrayTypes($types));
            if (\count($unique_types) === 1) {
                $union_type = \reset($unique_types)->asUnionType();
            } else {
                // TODO: Support brackets, template types within <>, etc.
                $union_type = new UnionType(
                    $unique_types,
                    true
                );
            }
            $memoize_map[$fully_qualified_string] = $union_type;
        }

        return $union_type;
    }

    /**
     * @param array<int,Type> $type_list
     * @return array<int,Type>
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
            return self::$empty_instance;
        }

        // If our scope has a generic type identifier defined on it
        // that matches the type string, return that UnionType.
        if ($context->getScope()->hasTemplateType($type_string)) {
            return $context->getScope()->getTemplateType(
                $type_string
            )->asUnionType();
        }
        $types = [];
        foreach (self::extractTypePartsForStringInContext($type_string) as $type_name) {
            $types[] = Type::fromStringInContext(
                $type_name,
                $context,
                $source
            );
        }
        return UnionType::of(self::normalizeGenericMultiArrayTypes($types));
    }

    /**
     * @return array<int,string>
     */
    private static function extractTypePartsForStringInContext(string $type_string)
    {
        static $cache = [];
        $parts = $cache[$type_string] ?? null;
        if (\is_array($parts)) {
            return $parts;
        }
        $parts = [];
        foreach (self::extractTypeParts($type_string) as $type_name) {
            // Exclude empty type names
            // Exclude namespaces without type names (e.g. `\`, `\NS\`)
            if ($type_name !== '' && \preg_match('@\\\\[\[\]]*$@', $type_name) === 0) {
                $parts[] = $type_name;
            }
        }
        $cache[$type_string] = $parts;
        return $parts;
    }

    /**
     * @return array<int,string>
     */
    private static function extractTypeParts(string $type_string) : array
    {
        $parts = [];
        foreach (\explode('|', $type_string) as $part) {
            $parts[] = \trim($part);
        }

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
     * @param array<int,Type> $types
     * @return array<int,Type>
     */
    public static function normalizeGenericMultiArrayTypes(array $types) : array
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
        return self::$empty_instance;
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
            return $result;
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
                $parameter_name_type_map[$name] = $get_for_global_context($type_name) ?? self::$empty_instance;
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
     * @return array<int,Type>
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
     * @return UnionType
     */
    public function withType(Type $type)
    {
        $type_set = $this->type_set;
        if (\count($type_set) === 0) {
            return $type->asUnionType();
        }
        if (\in_array($type, $type_set, true)) {
            return $this;
        }
        // 2 or more types in type_set
        $type_set[] = $type;
        return new UnionType($type_set, true);
    }

    /**
     * Returns a new union type
     * which removes this type from the list of types,
     * keeping the keys in a consecutive order.
     *
     * Each type in $this->type_set occurs exactly once.
     *
     * @return UnionType
     */
    public function withoutType(Type $type)
    {
        // Copy the array $this->type_set
        $type_set = $this->type_set;
        foreach ($type_set as $key => $other_type) {
            if ($type === $other_type) {
                // Remove the only instance of $type from the copy.
                unset($type_set[$key]);
                return self::ofUniqueTypes($type_set);
            }
        }
        // We did not find $type in type_set. The resulting union type is unchanged.
        return $this;
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
     * Returns a union type which add the given types to this type
     *
     * @return UnionType
     */
    public function withUnionType(UnionType $union_type)
    {
        // Precondition: Both UnionTypes have lists of unique types.
        $type_set = $this->type_set;
        if (\count($type_set) === 0) {
            return $union_type;
        }
        $other_type_set = $union_type->type_set;

        if (\count($other_type_set) === 0) {
            return $this;
        }
        $new_type_set = $type_set;
        foreach ($other_type_set as $type) {
            if (!\in_array($type, $type_set, true)) {
                $new_type_set[] = $type;
            }
        }
        return new UnionType($new_type_set, true);
    }

    /**
     * @return bool
     * True if this type has a type referencing the
     * class context in which it exists such as 'self'
     * or '$this'
     */
    public function hasSelfType() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->isSelfType()) {
                return true;
            }
        }
        return false;
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
     * @return array<int,UnionType>
     * A map from template type identifiers to the UnionType
     * to replace it with
     * TODO: Is anything using this? This makes sense for Type but not UnionType.
     */
    public function getTemplateParameterTypeList() : array
    {
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
     * @return array<string,UnionType>
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
     * @param array<string,UnionType> $template_parameter_type_map
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
        foreach ($this->type_set as $type) {
            if ($type instanceof StaticType) {
                return true;
            }
        }
        return false;
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

        if ($has_static_type) {
            // Remove the static type and add in the class in scope
            return $this->withoutType($static_type)->withType($context->getClassFQSEN()->asType());
        } else {
            return $this->withoutType($static_type)->withType($context->getClassFQSEN()->asType()->withIsNullable(true));
        }
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
        $builder = new UnionTypeBuilder();
        $did_change = false;
        foreach ($this->type_set as $type) {
            if (!$type->getIsNullable()) {
                $builder->addType($type);
                continue;
            }
            $did_change = true;
            if ($type === NullType::instance(false)) {
                continue;
            }

            $builder->addType($type->withIsNullable(false));
        }
        return $did_change ? $builder->getUnionType() : $this;
    }

    public function nullableClone() : UnionType
    {
        $builder = new UnionTypeBuilder();
        $did_change = false;
        foreach ($this->type_set as $type) {
            if ($type->getIsNullable()) {
                $builder->addType($type);
                continue;
            }
            $did_change = true;
            $builder->addType($type->withIsNullable(true));
        }
        return $did_change ? $builder->getUnionType() : $this;
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
        $builder = new UnionTypeBuilder();
        $did_change = false;
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyFalsey()) {
                $builder->addType($type);
                continue;
            }
            $did_change = true;
            if ($type->getIsAlwaysFalsey()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $builder->addType($type->asNonFalseyType());
        }
        return $did_change ? $builder->getUnionType() : $this;
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
        $builder = new UnionTypeBuilder();
        $did_change = false;
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyTruthy()) {
                $builder->addType($type);
                continue;
            }
            $did_change = true;
            if ($type->getIsAlwaysTruthy()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $builder->addType($type->asNonTruthyType());
        }
        return $did_change ? $builder->getUnionType() : $this;
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
        $builder = new UnionTypeBuilder();
        $did_change = false;
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyFalse()) {
                $builder->addType($type);
                continue;
            }
            $did_change = true;
            if ($type->getIsAlwaysFalse()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $builder->addType($type->asNonFalseType());
        }
        return $did_change ? $builder->getUnionType() : $this;
    }

    public function nonTrueClone() : UnionType
    {
        $builder = new UnionTypeBuilder();
        $did_change = false;
        foreach ($this->type_set as $type) {
            if (!$type->getIsPossiblyTrue()) {
                $builder->addType($type);
                continue;
            }
            $did_change = true;
            if ($type->getIsAlwaysTrue()) {
                // don't add null/false to the resulting type
                continue;
            }

            // add non-nullable equivalents, and replace BoolType with non-nullable TrueType
            $builder->addType($type->asNonTrueType());
        }
        return $did_change ? $builder->getUnionType() : $this;
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
        $type_set = $this->type_set;
        // If either type is unknown, we can't call it
        // a success
        if (\count($type_set) === 0) {
            return true;
        }
        $target_type_set = $target->type_set;
        if (\count($target_type_set) === 0) {
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
        if (\in_array($mixed_type, $type_set, true)
            || \in_array($mixed_type, $target_type_set, true)
        ) {
            return true;
        }

        // int -> float
        if (\in_array($int_type, $type_set, true)
            && \in_array($float_type, $target_type_set, true)
        ) {
            return true;
        }

        // Check conversion on the cross product of all
        // type combinations and see if any can cast to
        // any.
        foreach ($type_set as $source_type) {
            foreach ($target_type_set as $target_type) {
                if ($source_type->canCastToType($target_type)) {
                    return true;
                }
            }
        }

        // Allow casting ?T to T|null for any type T. Check if null is part of this type first.
        if (\in_array($null_type, $target_type_set, true)) {
            foreach ($type_set as $source_type) {
                // Only redo this check for the nullable types, we already failed the checks for non-nullable types.
                if ($source_type->getIsNullable()) {
                    $non_null_source_type = $source_type->withIsNullable(false);
                    foreach ($target_type_set as $target_type) {
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
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isArrayAccess();
        });
    }

    /**
     * @return bool
     * True if this union contains the Traversable type.
     * (Call asExpandedTypes() first to check for subclasses of Traversable)
     */
    public function hasTraversable() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isTraversable();
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
     * If this the filter preserves everything, returns $this instead
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
            return $this;
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
        foreach ($this->type_set as $class_type) {
            if ($class_type->isNativeType()) {
                continue;
            }
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
        foreach ($this->type_set as $class_type) {
            if ($class_type->isNativeType()) {
                continue;
            }
            // Get the class FQSEN
            $class_fqsen = FullyQualifiedClassName::fromType($class_type);

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
     * Returns true if at least one type could possibly be an object.
     * E.g. returns true for iterator.
     * NOTE: this returns false for `mixed`
     *
     * @return bool
     */
    public function hasPossiblyObjectTypes() : bool
    {
        return $this->hasTypeMatchingCallback((function (Type $type) : bool {
            return $type->isPossiblyObject();
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
     */
    public function isExclusivelyCallable() : bool
    {
        return !$this->hasTypeMatchingCallback(function (Type $type) : bool {
            return !$type->isCallable();
        });
    }

    public function isExclusivelyBoolTypes() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }
        foreach ($this->type_set as $type) {
            if (!$type->getIsInBoolFamily() || $type->getIsNullable()) {
                return false;
            }
        }
        return true;
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
     * Takes "array{field:int,other:string}" and returns "int|string"
     *
     * @return UnionType
     * The subset of types in this
     */
    public function genericArrayElementTypes() : UnionType
    {
        // This is frequently called, and has been optimized
        $builder = new UnionTypeBuilder();
        $type_set = $this->type_set;
        foreach ($type_set as $type) {
            if ($type->isGenericArray()) {
                if ($type instanceof ArrayShapeType) {
                    $builder->addUnionType($type->genericArrayElementUnionType());
                } else {
                    $builder->addType($type->genericArrayElementType());
                }
            }
        }

        static $array_type_nonnull = null;
        static $array_type_nullable = null;
        static $mixed_type = null;
        static $null_type = null;
        if ($array_type_nonnull === null) {
            $array_type_nonnull = ArrayType::instance(false);
            $array_type_nullable = ArrayType::instance(true);
            $mixed_type = MixedType::instance(false);
            $null_type = NullType::instance(false);
        }

        // If array is in there, then it can be any type
        if (\in_array($array_type_nonnull, $type_set, true)) {
            $builder->addType($mixed_type);
            $builder->addType($null_type);
        } elseif (\in_array($mixed_type, $type_set, true)
            || (
                Config::get_null_casts_as_any_type()
                && \in_array($array_type_nullable, $type_set, true)
            )
        ) {
            // Same for mixed
            $builder->addType($mixed_type);
        }

        return $builder->getUnionType();
    }

    /**
     * Takes "b|d[]" and returns "b[]|d[][]"
     *
     * @param int $key_type
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     *
     * @return UnionType
     * The subset of types in this
     */
    public function elementTypesToGenericArray(int $key_type) : UnionType
    {
        return new UnionType(
            \array_map(function (Type $type) use ($key_type) : Type {
                if ($type instanceof MixedType) {
                    return ArrayType::instance(false);
                }
                return GenericArrayType::fromElementType($type, false, $key_type);
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
     * @param int $key_type
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     *
     * @return UnionType
     * Get a new type for each type in this union which is
     * the generic array version of this type. For instance,
     * 'int|float' will produce 'int[]|float[]'.
     *
     * If $this is an empty UnionType, this method will produce an empty UnionType
     */
    public function asGenericArrayTypes(int $key_type) : UnionType
    {
        return $this->asMappedUnionType(
            function (Type $type) use ($key_type) : Type {
                return $type->asGenericArrayType($key_type);
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
    public function asNonEmptyGenericArrayTypes(int $key_type) : UnionType
    {
        if (\count($this->type_set) === 0) {
            return ArrayType::instance(false)->asUnionType();
        }
        return $this->asMappedUnionType(
            function (Type $type) use ($key_type) : Type {
                return $type->asGenericArrayType($key_type);
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

        $type_set = $this->type_set;
        if (\count($type_set) === 0) {
            return self::$empty_instance;
        } elseif (\count($type_set) === 1) {
            return \reset($type_set)->asExpandedTypes(
                $code_base,
                $recursion_depth + 1
            );
        }
        // 2 or more union types to merge

        $builder = new UnionTypeBuilder();
        foreach ($type_set as $type) {
            $builder->addUnionType(
                $type->asExpandedTypes(
                    $code_base,
                    $recursion_depth + 1
                )
            );
        }
        return $builder->getUnionType();
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
        // NOTE: Potentially need to handle "array{field:int|string}" in the future.
        // TODO: Not going to work with template types
        $this->type_set = UnionType::fromFullyQualifiedString($serialized)->getTypeSet();
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
        $builder = new UnionTypeBuilder($type_set);
        if ($nullable) {
            if (\count($type_set) > 0) {
                foreach ($type_set as $type) {
                    if (!$type->getIsNullable()) {
                        $builder->removeType($type);
                        $builder->addType($type->withIsNullable(true));
                    }
                }
                static $nullable_type = null;
                if ($nullable_type === null) {
                    $nullable_type = NullType::instance(false);
                }
                $builder->removeType($nullable_type);
            }
        }

        // If this contains both true and false types, filter out both and add "bool" (or "?bool" for nullable)
        if (($flags & Type::_bit_bool_combination) === Type::_bit_bool_combination) {
            if ($nullable) {
                self::convertToTypeSetWithNormalizedNullableBools($builder);
            } else {
                self::convertToTypeSetWithNormalizedNonNullableBools($builder);
            }
        }
        return $builder->getUnionType();
    }

    /**
     * @param UnionType[] $union_types
     * @return UnionType union of these UnionTypes
     */
    public static function merge(array $union_types) : UnionType
    {
        $n = \count($union_types);
        if ($n < 2) {
            return \reset($union_types) ?: UnionType::$empty_instance;
        }
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
        return UnionType::ofUniqueTypes($new_type_set);
    }

    /**
     * Must be called after converting nullable to non-nullable.
     * Removes false|true types and adds bool
     *
     * @param UnionTypeBuilder $builder (Containing only non-nullable values)
     * @return void
     * @var int $bool_id
     */
    private static function convertToTypeSetWithNormalizedNonNullableBools(UnionTypeBuilder $builder)
    {
        static $true_type = null;
        static $false_type = null;
        static $bool_type = null;
        if ($bool_type === null) {
            $true_type = TrueType::instance(false);
            $false_type = FalseType::instance(false);
            $bool_type = BoolType::instance(false);
        }
        if (!$builder->isEmpty()) {
            $builder->removeType($true_type);
            $builder->removeType($false_type);
        }

        $builder->addType($bool_type);
    }

    /**
     * Must be called after converting all types to null.
     * Removes ?false|?true types and adds ?bool
     *
     * @param UnionTypeBuilder $builder (Containing only non-nullable values)
     */
    private static function convertToTypeSetWithNormalizedNullableBools(UnionTypeBuilder $builder)
    {
        static $true_type = null;
        static $false_type = null;
        static $bool_type = null;
        if ($bool_type === null) {
            $true_type = TrueType::instance(true);
            $false_type = FalseType::instance(true);
            $bool_type = BoolType::instance(true);
        }
        if (!$builder->isEmpty()) {
            $builder->removeType($true_type);
            $builder->removeType($false_type);
        }

        $builder->addType($bool_type);
    }

    public static function createBuilderFromTypeList(array $type_list) : UnionTypeBuilder
    {
        return new UnionTypeBuilder(\count($type_list) <= 1 ? $type_list : self::getUniqueTypes($type_list));
    }

    /**
     * Generates a variable length string identifier that uniquely identifies the Type instances in this UnionType.
     * int|string will generate the same id as string|int.
     */
    public function generateUniqueId() : string
    {
        /** @var array<int,int> $ids */
        $ids = [];
        foreach ($this->type_set as $type) {
            $ids[] = \spl_object_id($type);
        }
        // Sort the unique identifiers of Type instances so that int|string generates the same id as string|int
        \sort($ids);
        return \implode(',', $ids);
    }

    public function hasTopLevelArrayShapeTypeInstances() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type instanceof ArrayShapeType) {
                return true;
            }
        }
        return false;
    }

    public function hasArrayShapeTypeInstances() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->hasArrayShapeTypeInstances()) {
                return true;
            }
        }
        return false;
    }

    public function withFlattenedArrayShapeTypeInstances() : UnionType
    {
        if (!$this->hasArrayShapeTypeInstances()) {
            return $this;
        }

        $result = new UnionTypeBuilder();
        foreach ($this->type_set as $type) {
            if ($type->hasArrayShapeTypeInstances()) {
                foreach ($type->withFlattenedArrayShapeTypeInstances() as $type_part) {
                    $result->addType($type_part);
                }
            } else {
                $result->addType($type);
            }
        }
        return $result->getUnionType();
    }
}

UnionType::init();
