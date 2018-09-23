<?php declare(strict_types=1);
namespace Phan\Language;

use Closure;
use Generator;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\RecursionDepthException;
use Phan\Issue;
use Phan\Language\AnnotatedUnionType;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayInterface;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralIntType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\LiteralTypeInterface;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\MultiType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\TrueType;
use Serializable;

if (!\function_exists('spl_object_id')) {
    require_once __DIR__ . '/../../spl_object_id.php';
}

/**
 * Phan's internal representation of union types, and methods for working with union types.
 *
 * This representation is immutable.
 * Phan represents union types as a list of unique `Type`s
 * (This was the most efficient representation, since most union types have 0, 1, or 2 unique types in practice)
 * To add/remove a type to a UnionType, you replace it with a UnionType that had that type added.
 *
 * @see AnnotatedUnionType for the way Phan represents extra information about types
 * @see https://github.com/phan/phan/wiki/About-Union-Types
 *
 * > Union types can be any native type such as int, string, bool, or array, any class such as DateTime,
 * > arrays of types such as string[], DateTime[],
 * > or a union of any other types such as string|int|null|DateTime|DateTime[],
 * > and many other types
 *
 */
class UnionType implements Serializable
{
    /**
     * @var string
     * A list of one or more types delimited by the '|'
     * character (e.g. 'int|DateTime|string[]')
     */
    // phpcs:ignore Generic.NamingConventions.UpperCaseConstantName
    const union_type_regex =
        Type::type_regex
        . '(\s*\|\s*' . Type::type_regex . ')*';

    /**
     * @var string
     * A list of one or more types delimited by the '|'
     * character (e.g. 'int|DateTime|string[]' or 'null|$this')
     * This may be used for return types.
     *
     * TODO: Equivalent variants with no capturing? (May not improve performance much)
     */
    // phpcs:ignore Generic.NamingConventions.UpperCaseConstantName
    const union_type_regex_or_this =
        Type::type_regex_or_this
        . '(\s*\|\s*' . Type::type_regex_or_this . ')*';

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

    /** @return UnionType */
    protected static function ofUniqueTypes(array $type_list)
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
            $types = \array_map(function (string $type_name) : Type {
                return Type::fromFullyQualifiedString($type_name);
            }, self::extractTypeParts($fully_qualified_string));

            $unique_types = self::getUniqueTypes(self::normalizeMultiTypes($types));
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
     * @param ?CodeBase $code_base
     * May be provided to resolve 'parent' in the context
     * (e.g. if parsing complex phpdoc).
     * Unnecessary in most use cases.
     *
     * @return UnionType
     */
    public static function fromStringInContext(
        string $type_string,
        Context $context,
        int $source,
        CodeBase $code_base = null
    ) : UnionType {
        if ($type_string === '') {
            // NOTE: '0' is a valid LiteralIntType
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
                $source,
                $code_base
            );
        }
        return UnionType::of(self::normalizeMultiTypes($types));
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
        if (!\preg_match('/[<({]/', $type_string)) {
            return $parts;
        }
        return self::mergeTypeParts($parts);
    }

    /**
     * Expands any GenericMultiArrayType and ScalarRawType instances in $types if necessary.
     *
     * @param array<int,Type> $types
     * @return array<int,Type>
     * @suppress PhanPartialTypeMismatchReturn TODO: why?
     */
    public static function normalizeMultiTypes(array $types) : array
    {
        foreach ($types as $i => $type) {
            if ($type instanceof MultiType) {
                foreach ($type->asIndividualTypeInstances() as $new_type) {
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
                $delta += \substr_count($part, '<') + \substr_count($part, '(') + \substr_count($part, '{') - \substr_count($part, '>') - \substr_count($part, ')') - \substr_count($part, '}');
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
            $bracket_count = \substr_count($part, '<') + \substr_count($part, '(') + \substr_count($part, '{');
            if ($bracket_count === 0) {
                $results[] = $part;
                continue;
            }
            $delta = $bracket_count - \substr_count($part, '>') - \substr_count($part, ')') - \substr_count($part, '}');
            if ($delta === 0) {
                $results[] = $part;
            } elseif ($delta > 0) {
                $prev_parts[] = $part;
            }  // otherwise ignore unparseable data such as ">" (should be impossible)
        }
        return $results;
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
     * @return array<string,string>
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
            $map_raw = require(__DIR__ . '/Internal/PropertyMap.php');
            foreach ($map_raw as $key => $value) {
                $map[\strtolower($key)] = $value;
            }

            // Merge in an empty type for dynamic properties on any
            // classes listed as supporting them.
            foreach (require(__DIR__ . '/Internal/DynamicPropertyMap.php') as $class_name) {
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
        $map = self::internalFunctionSignatureMap(Config::get_closest_target_php_version_id());

        if ($function_fqsen instanceof FullyQualifiedMethodName) {
            $class_fqsen =
                $function_fqsen->getFullyQualifiedClassName();
            $class_name = $class_fqsen->getNamespacedName();
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
                $context = new Context();
                $result = UnionType::fromStringInContext($type_name, $context, Type::FROM_PHPDOC);
                $internal_fn_cache[$type_name] = $result;
            }
            return $result;
        };

        $configurations = [];
        while (isset($map[$function_name])) {
            // Get some static data about the function
            $type_name_struct = $map[$function_name];

            // Figure out the return type
            $return_type_name = $type_name_struct[0];
            $return_type = $get_for_global_context($return_type_name);

            $parameter_name_type_map = [];

            foreach ($type_name_struct as $name => $type_name) {
                if (\is_int($name)) {
                    // Integer key names are reserved for metadata in the future.
                    continue;
                }
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
     * Returns the types for which is_bool($x) would be true.
     *
     * @return UnionType
     * A UnionType with known bool types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getTypesInBoolFamily() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->getIsInBoolFamily();
        });
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
            /** @return array<string,UnionType> */
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
        $has_template = false;
        $concrete_type_list = [];
        foreach ($this->type_set as $type) {
            // TODO: This should check GenericArray and ArrayShape as well!
            if ($type instanceof TemplateType
                && isset($template_parameter_type_map[$type->getName()])
            ) {
                $has_template = true;
                $union_type =
                    $template_parameter_type_map[$type->getName()];

                foreach ($union_type->type_set as $concrete_type) {
                    $concrete_type_list[] = $concrete_type;
                }
            } else {
                $concrete_type_list[] = $type;
            }
        }

        return $has_template ? UnionType::of($concrete_type_list) : $this;
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
     * True if this union type has any types that have generic
     * types
     */
    public function hasTemplateParameterTypes() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->hasTemplateParameterTypes();
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

    public function withoutSubclassesOf(CodeBase $code_base, Type $object_type) : UnionType
    {
        $is_nullable = $this->containsNullable();
        $new_variable_type = $this;

        foreach ($this->type_set as $type) {
            if ($type->asExpandedTypes($code_base)->hasType($object_type)) {
                $new_variable_type = $new_variable_type->withoutType($type);
            }
        }
        if ($is_nullable) {
            if ($new_variable_type->isEmpty()) {
                // There was a null somewhere in the old union type.
                return NullType::instance(false)->asUnionType();
            }
            return $new_variable_type->nullableClone();
        }
        return $new_variable_type;
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

    /**
     * @return bool - True if not empty, not possibly undefined, and at least one type is NullType or nullable.
     */
    public function containsNullableOrUndefined() : bool
    {
        return $this->containsNullable();
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

    /**
     * Returns true if this contains at least one non-null IntType or LiteralIntType
     */
    public function hasNonNullIntType() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type instanceof IntType && !$type->getIsNullable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if this is exclusively non-null IntType or LiteralIntType
     */
    public function isNonNullIntType() : bool
    {
        if (\count($this->type_set) === 0) {
            return false;
        }
        foreach ($this->type_set as $type) {
            if (!($type instanceof IntType) || $type->getIsNullable()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if this is exclusively non-null IntType or FloatType or subclasses
     */
    public function isNonNullNumberType() : bool
    {
        if (\count($this->type_set) === 0) {
            return false;
        }
        foreach ($this->type_set as $type) {
            if (!($type instanceof IntType || $type instanceof FloatType) || $type->getIsNullable()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if this contains at least one non-null StringType or LiteralStringType
     */
    public function hasStringType() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type instanceof StringType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if this contains at least one non-null StringType or LiteralStringType
     */
    public function hasNonNullStringType() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type instanceof StringType && !$type->getIsNullable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if this is exclusively non-null StringType or LiteralStringType
     */
    public function isNonNullStringType() : bool
    {
        if (\count($this->type_set) === 0) {
            return false;
        }
        foreach ($this->type_set as $type) {
            if (!($type instanceof StringType) || $type->getIsNullable()) {
                return false;
            }
        }
        return true;
    }

    public function hasLiterals() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type instanceof LiteralTypeInterface) {
                return true;
            }
        }
        return false;
    }

    public function asNonLiteralType() : UnionType
    {
        if (!$this->hasLiterals()) {
            return $this;
        }
        $result = UnionType::empty();
        foreach ($this->type_set as $type) {
            $result = $result->withType($type->asNonLiteralType());
        }
        return $result;
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
        // determine what's actually being referred
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
            if ($source_type->canCastToAnyTypeInSet($target_type_set)) {
                return true;
            }
        }

        // Allow casting ?T to T|null for any type T. Check if null is part of this type first.
        if (\in_array($null_type, $target_type_set, true)) {
            foreach ($type_set as $source_type) {
                // Only redo this check for the nullable types, we already failed the checks for non-nullable types.
                if ($source_type->getIsNullable()) {
                    return $source_type->withIsNullable(false)->canCastToAnyTypeInSet($target_type_set);
                }
            }
        }

        // Only if no source types can be cast to any target
        // types do we say that we cannot perform the cast
        return false;
    }

    /**
     * @param UnionType $target
     * A type to check to see if this can cast to it.
     *
     * Every single type in this type must be able to cast to a type in $target (Empty types can cast to empty)
     *
     * @return bool
     * True if this type is allowed to cast to the given type
     * i.e. int->float is allowed  while float->int is not.
     *
     * @suppress PhanUnreferencedPublicMethod may be used elsewhere in the future
     */
    public function canStrictCastToUnionType(UnionType $target) : bool
    {
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

        // every single type in T overlaps with T, a future call to Type->canCastToType will pass.
        $matches = true;
        foreach ($type_set as $type) {
            if (!\in_array($type, $target_type_set)) {
                $matches = false;
                break;
            }
        }
        if ($matches) {
            return true;
        }
        static $null_type;
        if ($null_type === null) {
            $null_type  = NullType::instance(false);
        }

        // Check conversion on the cross product of all
        // type combinations and see if any can cast to
        // any.
        $matches = true;
        foreach ($type_set as $source_type) {
            if (!$source_type->canCastToAnyTypeInSet($target_type_set)) {
                $matches = false;
                break;
            }
        }
        if ($matches) {
            return true;
        }

        // Allow casting ?T to T|null for any type T. Check if null is part of this type first.
        if (\in_array($null_type, $target_type_set, true)) {
            foreach ($type_set as $source_type) {
                // Only redo this check for the nullable types, we already failed the checks for non-nullable types.
                if (!$source_type->withIsNullable(false)->canCastToAnyTypeInSet($target_type_set)) {
                    return false;
                }
            }
            return true;
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
     * True if any types in this union are a printable scalar, or this is the empty union type
     * @internal
     */
    public function hasPrintableScalar() : bool
    {
        if ($this->isEmpty()) {
            return true;
        }

        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->isPrintableScalar();
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
     * True if this union has array-like types (is of type array,
     * or is a generic array)
     */
    public function hasArray() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type instanceof ArrayType;
        });
    }

    /**
     * @return bool
     * True if this union has array-like types (is of type array, is
     * a generic array, is an array shape, or implements ArrayAccess).
     */
    public function hasGenericArray() : bool
    {
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type instanceof GenericArrayInterface;
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
     * @return UnionType
     */
    public function asArrayOrArrayAccessSubTypes(CodeBase $code_base) : UnionType
    {
        $result = UnionType::empty();
        foreach ($this->type_set as $type) {
            if ($type->isArrayOrArrayAccessSubType($code_base)) {
                $result = $result->withType($type);
            }
        }
        return $result;
    }

    /**
     * @return bool
     * True if this union contains the Traversable type.
     * (Call asExpandedTypes() first to check for subclasses of Traversable)
     * @suppress PhanUnreferencedPublicMethod not used right now.
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
            return !($type instanceof ArrayType) || $type->getIsNullable();
        });
    }

    /**
     * @return UnionType
     * Get the subset of types which are not native
     */
    public function nonNativeTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return !$type->isNativeType();
        });
    }

    /**
     * A memory efficient way to create a UnionType from a filter operation.
     * If this the filter preserves everything, returns $this instead
     */
    public function makeFromFilter(Closure $cb) : UnionType
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
     * @return Generator
     * @phan-return Generator<FullyQualifiedClassName>
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
     * @return Generator
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
            return !($type instanceof GenericArrayInterface);
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
            return $type instanceof GenericArrayInterface;
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
     * Takes "MyClass|int|array|?object" and returns "MyClass"
     *
     * @return UnionType
     * A UnionType with known object types with known FQSENs kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     */
    public function objectTypesWithKnownFQSENs() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->isObjectWithKnownFQSEN();
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
     * @suppress PhanUnreferencedPublicMethod
     */
    public function callableTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            return $type->isCallable();
        });
    }

    /**
     * Returns the types for which is_int($x) would be true.
     *
     * @return UnionType
     * A UnionType with known int types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @suppress PhanUnreferencedPublicMethod
     */
    public function intTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            // IntType and LiteralType
            return $type instanceof IntType;
        });
    }

    /**
     * Returns the types for which is_string($x) would be true.
     *
     * @return UnionType
     * A UnionType with known string types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @suppress PhanUnreferencedPublicMethod
     */
    public function stringTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            // IntType and LiteralStringType
            return $type instanceof StringType;
        });
    }

    /**
     * Returns the types for which is_numeric($x) is possibly true.
     *
     * @return UnionType
     * A UnionType with known numeric types kept, other types filtered out.
     *
     * @see nonGenericArrayTypes
     * @suppress PhanUnreferencedPublicMethod
     */
    public function numericTypes() : UnionType
    {
        return $this->makeFromFilter(function (Type $type) : bool {
            // IntType and LiteralStringType
            return $type->getIsPossiblyNumeric();
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
     * @suppress PhanUnreferencedPublicMethod not used right now.
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
                return !($type instanceof ArrayType);
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
            return !($type instanceof GenericArrayInterface);
        });
    }

    /**
     * @return bool
     * True if any of the types in this UnionType made $matcher_callback return true
     */
    public function hasTypeMatchingCallback(Closure $matcher_callback) : bool
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
    public function findTypeMatchingCallback(Closure $matcher_callback)
    {
        foreach ($this->type_set as $type) {
            if ($matcher_callback($type)) {
                return $type;
            }
        }
        return false;
    }

    /**
     * Takes "a|b[]|c|d[]|e|Traversable<f,g>" and returns "int|string|f"
     *
     * Takes "array{field:int,other:stdClass}" and returns "string"
     *
     * @param CodeBase $code_base (for detecting the iterable value types of `class MyIterator extends Iterator`)
     *
     * @return UnionType
     */
    public function iterableKeyUnionType(CodeBase $code_base) : UnionType
    {
        // This is frequently called, and has been optimized
        $builder = new UnionTypeBuilder();
        $type_set = $this->type_set;
        foreach ($type_set as $type) {
            $element_type = $type->iterableKeyUnionType($code_base);
            if ($element_type === null) {
                // Does not have iterable values
                continue;
            }
            $builder->addUnionType($element_type);
        }

        return $builder->getUnionType();
    }

    /**
     * Takes "a|b[]|c|d[]|e|Traversable<f,g>" and returns "b|d|g"
     *
     * Takes "array{field:int,other:string}" and returns "int|string"
     *
     * @param CodeBase $code_base (for detecting the iterable value types of `class MyIterator extends Iterator`)
     *
     * @return UnionType
     */
    public function iterableValueUnionType(CodeBase $code_base) : UnionType
    {
        // This is frequently called, and has been optimized
        $builder = new UnionTypeBuilder();
        $type_set = $this->type_set;
        foreach ($type_set as $type) {
            $element_type = $type->iterableValueUnionType($code_base);
            if ($element_type === null) {
                // Does not have iterable values
                continue;
            }
            $builder->addUnionType($element_type);
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
            || \in_array($array_type_nullable, $type_set, true)
        ) {
            // Same for mixed
            $builder->addType($mixed_type);
        }

        return $builder->getUnionType();
    }

    /**
     * Takes "a|b[]|c|d[]|e" and returns "b|d"
     * Takes "array{field:int,other:string}" and returns "int|string"
     *
     * @return UnionType
     */
    public function genericArrayElementTypes() : UnionType
    {
        // This is frequently called, and has been optimized
        $builder = new UnionTypeBuilder();
        $type_set = $this->type_set;
        foreach ($type_set as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof GenericArrayType) {
                    $builder->addType($type->genericArrayElementType());
                } else {
                    $builder->addUnionType($type->genericArrayElementUnionType());
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
            || \in_array($array_type_nullable, $type_set, true)
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
     *
     * TODO: Add a variant that will convert mixed to array<int,mixed> instead of array?
     */
    public function elementTypesToGenericArray(int $key_type) : UnionType
    {
        $parts = \array_map(function (Type $type) use ($key_type) : Type {
            if ($type instanceof MixedType) {
                return ArrayType::instance(false);
            }
            return GenericArrayType::fromElementType($type, false, $key_type);
        }, $this->type_set);
        if (\count($parts) <= 1) {
            return \count($parts) === 1 ? \reset($parts)->asUnionType() : self::$empty_instance;
        }
        return new UnionType($parts);
    }

    /**
     * @param Closure $closure
     * A closure mapping `Type` to `Type`
     *
     * @return UnionType
     * A new UnionType with each type mapped through the
     * given closure
     */
    public function asMappedUnionType(Closure $closure) : UnionType
    {
        $parts = \array_map($closure, $this->type_set);
        if (\count($parts) <= 1) {
            return \count($parts) === 1 ? \reset($parts)->asUnionType() : self::$empty_instance;
        }
        return new UnionType($parts);
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
     * @param CodeBase $code_base
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
        if ($recursion_depth >= 12) {
            throw new RecursionDepthException("Recursion has gotten out of hand");
        }

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
     * @return array<string,array<int|string,string>>
     * A map from builtin function name to type information
     *
     * @see \Phan\Language\Internal\FunctionSignatureMap
     */
    public static function internalFunctionSignatureMap(int $target_php_version) : array
    {
        static $php72_map = [];

        if (!$php72_map) {
            $php72_map = self::computeLatestFunctionSignatureMap();
        }
        if ($target_php_version >= 70300) {
            static $php73_map = [];
            if (!$php73_map) {
                $php73_map = self::computePHP73FunctionSignatureMap($php72_map);
            }
            return $php73_map;
        }
        if ($target_php_version >= 70200) {
            return $php72_map;
        }
        static $php71_map = [];
        if (!$php71_map) {
            $php71_map = self::computePHP71FunctionSignatureMap($php72_map);
        }
        if ($target_php_version >= 70100) {
            return $php71_map;
        }
        static $php70_map = [];
        if (!$php70_map) {
            $php70_map = self::computePHP70FunctionSignatureMap($php71_map);
        }
        return $php70_map;
    }

    private static function computeLatestFunctionSignatureMap() : array
    {
        $map = [];
        $map_raw = require(__DIR__ . '/Internal/FunctionSignatureMap.php');
        foreach ($map_raw as $key => $value) {
            $map[\strtolower($key)] = $value;
        }
        return $map;
    }

    /**
     * @param array<string,array<int|string,string>> $php72_map
     * @return array<string,array<int|string,string>>
     */
    private static function computePHP73FunctionSignatureMap(array $php72_map) : array
    {
        $delta_raw = require(__DIR__ . '/Internal/FunctionSignatureMap_php73_delta.php');
        return self::applyDeltaToGetNewerSignatures($php72_map, $delta_raw);
    }

    /**
     * @param array<string,array<int|string,string>> $php72_map
     * @return array<string,array<int|string,string>>
     */
    private static function computePHP71FunctionSignatureMap(array $php72_map) : array
    {
        $delta_raw = require(__DIR__ . '/Internal/FunctionSignatureMap_php72_delta.php');
        return self::applyDeltaToGetOlderSignatures($php72_map, $delta_raw);
    }

    /**
     * @param array<string,array<int|string,string>> $php71_map
     * @return array<string,array<int|string,string>>
     */
    private static function computePHP70FunctionSignatureMap(array $php71_map) : array
    {
        $delta_raw = require(__DIR__ . '/Internal/FunctionSignatureMap_php71_delta.php');
        return self::applyDeltaToGetOlderSignatures($php71_map, $delta_raw);
    }

    /**
     * @param array<string,array<int|string,string>> $older_map
     * @param array{new:array<string,array<int|string,string>>,old:array<string,array<int|string,string>>} $delta
     * @return array<string,array<int|string,string>>
     *
     * @see applyDeltaToGetOlderSignatures - This is doing the exact same thing in reverse.
     */
    private static function applyDeltaToGetNewerSignatures(array $older_map, array $delta) : array
    {
        return self::applyDeltaToGetOlderSignatures($older_map, [
            'old' => $delta['new'],
            'new' => $delta['old'],
        ]);
    }

    /**
     * @param array<string,array<int|string,string>> $newer_map
     * @param array{new:array<string,array<int|string,string>>,old:array<string,array<int|string,string>>} $delta
     * @return array<string,array<int|string,string>>
     */
    private static function applyDeltaToGetOlderSignatures(array $newer_map, array $delta) : array
    {
        foreach ($delta['new'] as $key => $unused_signature) {
            // Would also unset alternates, but that step isn't necessary yet.
            unset($newer_map[\strtolower($key)]);
        }
        foreach ($delta['old'] as $key => $signature) {
            // Would also unset alternates, but that step isn't necessary yet.
            $newer_map[\strtolower($key)] = $signature;
        }

        // Return the newer map after modifying it to become the older map.
        return $newer_map;
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
        // TODO: Convert array|array{} to array?
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

    public function hasTopLevelNonArrayShapeTypeInstances() : bool
    {
        foreach ($this->type_set as $type) {
            if (!($type instanceof ArrayShapeType)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @suppress PhanUnreferencedPublicMethod
     */
    public function hasArrayShapeTypeInstances() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->hasArrayShapeTypeInstances()) {
                return true;
            }
        }
        return false;
    }

    public function hasArrayShapeOrLiteralTypeInstances() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type->hasArrayShapeOrLiteralTypeInstances()) {
                return true;
            }
        }
        return false;
    }

    public function hasMixedType() : bool
    {
        foreach ($this->type_set as $type) {
            if ($type instanceof MixedType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Flatten literals in keys and values into non-literal types
     * E.g. convert array{2:3} to array<int,string>
     */
    public function withFlattenedArrayShapeOrLiteralTypeInstances() : UnionType
    {
        if (!$this->hasArrayShapeOrLiteralTypeInstances()) {
            return $this;
        }

        $result = new UnionTypeBuilder();
        foreach ($this->type_set as $type) {
            if ($type->hasArrayShapeOrLiteralTypeInstances()) {
                foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                    $result->addType($type_part);
                }
            } else {
                $result->addType($type);
            }
        }
        return $result->getUnionType();
    }

    public function shouldBeReplacedBySpecificTypes() : bool
    {
        if ($this->isEmpty()) {
            return true;
        }
        return $this->hasTypeMatchingCallback(function (Type $type) : bool {
            return $type->shouldBeReplacedBySpecificTypes();
        });
    }

    /**
     * @param int|string|float|bool $field_key
     */
    public function withoutArrayShapeField($field_key) : UnionType
    {
        $types = $this->type_set;
        foreach ($types as $i => $type) {
            if ($type instanceof ArrayShapeType) {
                $types[$i] = $type->withoutField($field_key);
            }
        }
        if ($types === $this->type_set) {
            return $this;
        }
        return UnionType::of($types);
    }

    /**
     * Base implementation. Overridden by AnnotatedUnionType.
     */
    public function withIsPossiblyUndefined(bool $is_possibly_undefined) : UnionType
    {
        if ($is_possibly_undefined === false) {
            return $this;
        }
        $result = new AnnotatedUnionType($this->getTypeSet(), true);
        $result->is_possibly_undefined = $is_possibly_undefined;
        return $result;
    }

    /**
     * Base implementation. Overridden by AnnotatedUnionType.
     * Used for fields of array shapes.
     *
     * This is distinct from null - The array shape offset potentially doesn't exist at all, which is different from existing and being null.
     */
    public function getIsPossiblyUndefined() : bool
    {
        return false;
    }

    // Assumes this was already expanded
    public function hasClassWithToStringMethod(CodeBase $code_base, Context $context) : bool
    {
        try {
            foreach ($this->asClassList($code_base, $context) as $clazz) {
                if ($clazz->hasMethodWithName($code_base, "__toString")) {
                    return true;
                }
            }
        } catch (CodeBaseException $_) {
            // Swallow "Cannot find class", go on to emit issue
        }
        return false;
    }

    public function asGeneratorTemplateType() : Type
    {
        $fallback_values = UnionType::empty();
        $fallback_keys = UnionType::empty();

        foreach ($this->getTypeSet() as $type) {
            if ($type->isGenerator()) {
                if ($type->hasTemplateParameterTypes()) {
                    return $type;
                }
            }
            // TODO: support Iterator<T> or Traversable<T> or iterable<T>
            if ($type instanceof GenericArrayType) {
                $fallback_values = $fallback_values->withType($type->genericArrayElementType());
                $key_type = $type->getKeyType();
                if ($key_type === GenericArrayType::KEY_INT) {
                    $fallback_keys = $fallback_keys->withType(IntType::instance(false));
                } elseif ($key_type === GenericArrayType::KEY_STRING) {
                    $fallback_keys = $fallback_keys->withType(StringType::instance(false));
                }
            } elseif ($type instanceof ArrayShapeType && $type->isNotEmptyArrayShape()) {
                $fallback_values = $fallback_values->withUnionType($type->genericArrayElementUnionType());
                $fallback_keys = $fallback_keys->withUnionType(GenericArrayType::unionTypeForKeyType($type->getKeyType()));
            }
        }

        $result = Type::fromFullyQualifiedString('\Generator');
        if ($fallback_keys->typeCount() > 0 || $fallback_values->typeCount() > 0) {
            $template_types = $fallback_keys->typeCount() > 0 ? [$fallback_keys, $fallback_values] : [$fallback_values];
            $result = $result->fromType($result, $template_types);
        }
        return $result;
    }

    /**
     * @return Generator<Type,Type> ($outer_type => $inner_type)
     *
     * This includes classes, StaticType (and "self"), and TemplateType.
     * This includes duplicate definitions
     * TODO: Warn about Closure Declarations with invalid parameters...
     *
     * TODO: Use different helper for GoToDefinitionRequest
     */
    public function getReferencedClasses() : Generator
    {
        foreach ($this->withFlattenedArrayShapeOrLiteralTypeInstances()->getTypeSet() as $outer_type) {
            $type = $outer_type;

            while ($type instanceof GenericArrayType) {
                $type = $type->genericArrayElementType();
            }
            if ($type->isNativeType()) {
                continue;
            }
            yield $outer_type => $type;
        }
    }

    public function applyUnaryMinusOperator() : UnionType
    {
        // TODO: Extend to LiteralFloatType
        /** @param int|float $value */
        return $this->applyNumericOperation(function ($value) : ScalarType {
            $result = -$value;
            if (\is_int($result)) {
                return LiteralIntType::instanceForValue($result, false);
            }
            // -INT_MIN is a float.
            return FloatType::instance(false);
        }, true);
    }

    public function applyUnaryBitwiseNotOperator() : UnionType
    {
        if ($this->isEmpty()) {
            return IntType::instance(false)->asUnionType();
        }
        $added_fallbacks = false;
        $type_set = UnionType::empty();
        foreach ($this->type_set as $type) {
            if ($type instanceof LiteralIntType) {
                $type_set = $type_set->withType(LiteralIntType::instanceForValue(~$type->getValue(), false));
                if ($type->getIsNullable()) {
                    $type_set = $type_set->withType(LiteralIntType::instanceForValue(0, false));
                }
            } elseif ($type instanceof StringType) {
                // Not going to bother being more specific (this applies bitwise not to each character for LiteralStringType)
                $type_set = $type_set->withType(StringType::instance(false));
            } else {
                if ($added_fallbacks) {
                    continue;
                }
                $type_set = $type_set->withType(IntType::instance(false));
                $added_fallbacks = true;
            }
        }
        return $type_set;
    }

    public function applyUnaryPlusOperator() : UnionType
    {
        /** @param int|float $value */
        return $this->applyNumericOperation(function ($value) : ScalarType {
            $result = -$value;
            if (\is_int($result)) {
                return LiteralIntType::instanceForValue($result, false);
            }
            // -INT_MIN is a float.
            return FloatType::instance(false);
        }, true);
    }

    /**
     * @param Closure(int): ScalarType $operation
     */
    private function applyNumericOperation(Closure $operation, bool $can_be_float) : UnionType
    {
        $added_fallbacks = false;
        $type_set = UnionType::empty();
        foreach ($this->type_set as $type) {
            if ($type instanceof LiteralIntType) {
                $type_set = $type_set->withType($operation($type->getValue()));
                if ($type->getIsNullable()) {
                    $type_set = $type_set->withType(LiteralIntType::instanceForValue(0, false));
                }
            } else {
                if ($added_fallbacks) {
                    continue;
                }
                if ($can_be_float) {
                    if (!($type instanceof IntType)) {
                        $type_set = $type_set->withType(FloatType::instance(false));
                        if (!($type instanceof FloatType)) {
                            $type_set = $type_set->withType(IntType::instance(false));
                        }
                        $added_fallbacks = true;
                    } else {
                        $type_set = $type_set->withType(IntType::instance(false));
                        // Keep added_fallbacks false in case this needs to add FloatType
                    }
                } else {
                    $type_set = $type_set->withType(IntType::instance(false));
                    $added_fallbacks = true;
                }
            }
        }
        return $type_set;
    }

    /**
     * @return ?string|?float|?int|bool|null
     * If this union type can be represented by a single scalar value,
     * then this returns that scalar value.
     *
     * Otherwise, this returns null.
     */
    public function asSingleScalarValueOrNull()
    {
        $type_set = $this->type_set;
        if (\count($type_set) !== 1) {
            return null;
        }
        $type = \reset($type_set);
        switch (\get_class($type)) {
            case LiteralIntType::class:
                '@phan-var LiteralIntType $type';  // TODO: support switches
                return $type->getIsNullable() ? null : $type->getValue();
            case LiteralStringType::class:
                '@phan-var LiteralStringType $type';  // TODO: support switches
                return $type->getIsNullable() ? null : $type->getValue();
            case FalseType::class:
                return false;
            case TrueType::class:
                return true;
            // case NullType::class:
            default:
                return null;
        }
    }
}

UnionType::init();
