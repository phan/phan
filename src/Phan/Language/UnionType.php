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
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\TemplateType;
use Phan\Library\Set;
use ast\Node;

class UnionType implements \Serializable
{
    use \Phan\Memoize;

    /**
     * @var string
     * A list of one or more types delimited by the '|'
     * character (e.g. 'int|DateTime|string[]')
     */
    const union_type_regex =
        Type::type_regex
        . '(\|' . Type::type_regex . ')*';

    /**
     * @var Set
     */
    private $type_set;

    /**
     * @param Type[]|\Iterator $type_list
     * An optional list of types represented by this union
     */
    public function __construct($type_list = null)
    {
        $this->type_set = new Set($type_list);
    }

    /**
     * After a clone is called on this object, clone our
     * deep objects.
     *
     * @return null
     */
    public function __clone()
    {
        $set = new Set();
        $set->addAll($this->type_set);
        $this->type_set = $set;
    }

    /**
     * @param string $fully_qualified_string
     * A '|' delimited string representing a type in the form
     * 'int|string|null|ClassName'.
     *
     * @param Context $context
     * The context in which the type string was
     * found
     *
     * @return UnionType
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) : UnionType {
        if ($fully_qualified_string === '') {
            return new UnionType();
        }

        return new UnionType(
            array_map(function (string $type_name) {
                return Type::fromFullyQualifiedString($type_name);
            }, explode('|', $fully_qualified_string))
        );
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
     * @param bool $is_phpdoc_type
     * True if $type_string was extracted from a doc comment.
     *
     * @return UnionType
     */
    public static function fromStringInContext(
        string $type_string,
        Context $context,
        bool $is_phpdoc_type
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

        return new UnionType(
            array_map(function (string $type_name) use ($context, $type_string, $is_phpdoc_type) {
                assert($type_name !== '', "Type cannot be empty.");
                return Type::fromStringInContext(
                    $type_name,
                    $context,
                    $is_phpdoc_type
                );
            }, array_filter(array_map(function (string $type_name) {
                return trim($type_name);
            }, explode('|', $type_string))))
        );
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
     * @return string[]
     * Get a map from property name to its type for the given
     * class name.
     */
    public static function internalPropertyMapForClassName(
        string $class_name
    ) : array {
        $map = self::internalPropertyMap();

        $canonical_class_name = strtolower($class_name);

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
                $map[strtolower($key)] = $value;
            }

            // Merge in an empty type for dynamic properties on any
            // classes listed as supporting them.
            foreach (require(__DIR__.'/Internal/DynamicPropertyMap.php') as $class_name) {
                $map[strtolower($class_name)]['*'] = '';
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
        $context = new Context;

        $map = self::internalFunctionSignatureMap();

        if ($function_fqsen instanceof FullyQualifiedMethodName) {
            $class_fqsen =
                $function_fqsen->getFullyQualifiedClassName();
            $class_name = $class_fqsen->getName();
            $function_name =
                $class_name . '::' . $function_fqsen->getName();
        } else {
            $function_name = $function_fqsen->getName();
        }

        $function_name = strtolower($function_name);

        $function_name_original = $function_name;
        $alternate_id = 0;

        $configurations = [];
        while (isset($map[$function_name])) {


            // Get some static data about the function
            $type_name_struct = $map[$function_name];
            if (empty($type_name_struct)) {
                continue;
            }

            // Figure out the return type
            $return_type_name = array_shift($type_name_struct);
            $return_type = $return_type_name
                ? UnionType::fromStringInContext($return_type_name, $context, false)
                : null;

            $name_type_name_map = $type_name_struct;
            $property_name_type_map = [];

            foreach ($name_type_name_map as $name => $type_name) {
                $property_name_type_map[$name] = empty($type_name)
                    ? new UnionType()
                    : UnionType::fromStringInContext($type_name, $context, false);
            }

            $configurations[] = [
                'return_type' => $return_type,
                'property_name_type_map' => $property_name_type_map,
            ];

            $function_name =
                $function_name_original . '\'' . (++$alternate_id);
        }

        return $configurations;
    }

    /**
     * @return Set
     * The set of simple types associated with this
     * union type.
     */
    public function getTypeSet() : Set
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
        $this->type_set->attach($type);
    }

    /**
     * Remove a type name to the list of types
     *
     * @return void
     */
    public function removeType(Type $type)
    {
        $this->type_set->detach($type);
    }

    /**
     * @return bool
     * True if this union type contains the given named
     * type.
     */
    public function hasType(Type $type) : bool
    {
        return $this->type_set->contains($type);
    }

    /**
     * Add the given types to this type
     *
     * @return void
     */
    public function addUnionType(UnionType $union_type)
    {
        $this->type_set->addAll(
            $union_type->getTypeSet()
        );
    }

    /**
     * @return bool
     * True if this type has a type referencing the
     * class context in which it exists such as 'self'
     * or '$this'
     */
    public function hasSelfType() : bool
    {
        return (false !==
            $this->type_set->find(function (Type $type) : bool {
                return $type->isSelfType();
            })
        );
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

        return array_reduce($this->getTypeSet()->toArray(),
            function (array $map, Type $type) {
                return array_merge(
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

        return array_reduce($this->getTypeSet()->toArray(),
            function (array $map, Type $type) use ($code_base) {
                return array_merge(
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
        foreach ($this->getTypeSet() as $i => $type) {
            if ($type instanceof TemplateType
                && isset($template_parameter_type_map[$type->getName()])
            ) {
                $union_type =
                    $template_parameter_type_map[$type->getName()];

                foreach ($union_type->getTypeSet() as $concrete_type) {
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
        return (false !==
            $this->type_set->find(function (Type $type) : bool {
                return ($type instanceof TemplateType);
            })
        );
    }

    /**
     * @return bool
     * True if this type has a type referencing the
     * class context 'static'.
     */
    public function hasStaticType() : bool
    {
        return (false !==
            $this->type_set->find(function (Type $type) : bool {
                return $type->isStaticType();
            })
        );
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

        // Find the static type on the list
        $static_type = $this->getTypeSet()->find(function (Type $type) : bool {
            return $type->isStaticType();
        });

        // If we don't actually have a static type, we're all set
        if (!$static_type) {
            return $this;
        }

        // Get a copy of this UnionType to avoid having to know
        // who has copies of it out in the wild and what they're
        // hoping for.
        $union_type = clone($this);

        // Remove the static type
        $union_type->removeType($static_type);

        // Add in the class in scope
        $union_type->addType($context->getClassFQSEN()->asType());

        return $union_type;
    }

    /**
     * @return bool
     * True if and only if this UnionType contains
     * the given type and no others.
     */
    public function isType(Type $type) : bool
    {
        if ($this->typeCount() != 1) {
            return false;
        }

        return $this->type_set->contains($type);
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

        return (false ===
            $this->type_set->find(function (Type $type) : bool {
                return !$type->isNativeType();
            })
        );
    }

    /**
     * @return bool
     * True iff this union contains the exact set of types
     * represented in the given union type.
     */
    public function isEqualTo(UnionType $union_type) : bool
    {
        return ((string)$this === (string)$union_type);
    }

    /**
     * @return bool - True if not empty and at least one type is NullType or nullable.
     */
    public function containsNullable() : bool
    {
        foreach ($this->getTypeSet() as $type) {
            if ($type->getIsNullable()) {
                return true;
            }
        }
        return false;
    }

    public function nonNullableClone() : UnionType
    {
        $result = new UnionType();
        foreach ($this->getTypeSet() as $type) {
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
    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $context,
        CodeBase $code_base
    ) : bool {

        // Special rule: anything can cast to nothing
        if ($union_type->isEmpty()) {
            return true;
        }

        // Check to see if the types are equivalent
        if ($this->isEqualTo($union_type)) {
            return true;
        }

        // Resolve 'static' for the given context to
        // determine whats actually being referred
        // to in concrete terms.
        $union_type =
            $union_type->withStaticResolvedInContext($context);

        // Convert this type to an array of resolved
        // types.
        $type_set =
            $this->withStaticResolvedInContext($context)
            ->getTypeSet()->toArray();

        // Test to see if every single type in this union
        // type can cast to the given union type.
        return array_reduce($type_set,
            function (bool $can_cast, Type $type) use($union_type, $code_base) : bool {
                return (
                    $can_cast
                    && $type->asUnionType()->asExpandedTypes($code_base)->canCastToUnionType(
                        $union_type
                    )
                );
            }, true);
    }

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
        return $this->type_set->containsAny($type_list);
    }

    /**
     * @return int
     * The number of types in this union type
     */
    public function typeCount() : int
    {
        return $this->type_set->count();
    }

    /**
     * @return bool
     * True if this Union has no types
     */
    public function isEmpty() : bool
    {
        return ($this->typeCount() < 1);
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
     *
     * @see \Phan\Deprecated\Pass2::type_check
     * Formerly 'function type_check'
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

        // T === T
        if ($this->isEqualTo($target)) {
            return true;
        }

        if (Config::get()->null_casts_as_any_type) {
            // null <-> null
            if ($this->isType(NullType::instance(false))
                || $target->isType(NullType::instance(false))
            ) {
                return true;
            }
        }

        // mixed <-> mixed
        if ($target->hasType(MixedType::instance(false))
            || $this->hasType(MixedType::instance(false))
        ) {
            return true;
        }

        // int -> float
        if ($this->isType(IntType::instance(false))
            && $target->isType(FloatType::instance(false))
        ) {
            return true;
        }

        // Check conversion on the cross product of all
        // type combinations and see if any can cast to
        // any.
        foreach ($this->getTypeSet() as $source_type) {
            if (empty($source_type)) {
                continue;
            }

            foreach ($target->getTypeSet() as $target_type) {
                if (empty($target_type)) {
                    continue;
                }

                if ($source_type->canCastToType($target_type)) {
                    return true;
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
     *
     * @see \Phan\Deprecated\Util::type_scalar
     * Formerly `function type_scalar`
     */
    public function isScalar() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return (false ===
            $this->type_set->find(function (Type $type) : bool {
                return !$type->isScalar();
            })
        );
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

        return (false ===
            $this->type_set->find(function (Type $type) : bool {
                return !$type->isArrayLike();
            })
        );
    }

    /**
     * @return bool
     * True if this union type represents types that are
     * array-like, and nothing else.
     */
    public function isExclusivelyArrayLike() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return array_reduce($this->getTypeSet()->toArray(),
            function (bool $is_exclusively_array, Type $type) : bool {
                return (
                    $is_exclusively_array
                    && $type->isArrayLike()
                );
            }, true);
    }

    /**
     * @return bool
     * True if this union type represents types that are arrays
     * or generic arrays, but nothing else.
     */
    public function isExclusivelyArray() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return array_reduce($this->getTypeSet()->toArray(),
            function (bool $is_exclusively_array, Type $type) : bool {
                return (
                    $is_exclusively_array
                    && (
                        $type === ArrayType::instance(false)
                        || $type->isGenericArray()
                    )
                );
            }, true);
    }

    /**
     * @return UnionType
     * Get the subset of types which are not native
     */
    public function nonNativeTypes() : UnionType
    {
        return new UnionType(
            $this->type_set->filter(function (Type $type) {
                return !$type->isNativeType();
            })
        );
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
     */
    public function asClassList(
        CodeBase $code_base,
        Context $context
    ) {
        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($this->nonNativeTypes()->getTypeSet() as $class_type) {

            // Get the class FQSEN
            $class_fqsen = $class_type->asFQSEN();

            if ($class_type->isStaticType()) {
                if (!$context->isInClassScope()) {
                    throw new IssueException(
                        Issue::fromType(Issue::ContextNotObject)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                                (string)$class_type
                            ]
                        )
                    );

                }
                yield $context->getClassInScope($code_base);
            } else {
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
     * A UnionType with generic types filtered out
     *
     * @see \Phan\Deprecated\Pass2::nongenerics
     * Formerly `function nongenerics`
     */
    public function nonGenericArrayTypes() : UnionType
    {
        return new UnionType(
            $this->type_set->filter(
                function (Type $type) : bool {
                    return !$type->isGenericArray();
                }
            )
        );
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
        return new UnionType(
            $this->type_set->filter(
                function (Type $type) : bool {
                    return !$type->isGenericArray()
                        && $type !== ArrayType::instance(false);
                }
            )
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

        return (false ===
            $this->type_set->find(function (Type $type) : bool {
                return !$type->isGenericArray();
            })
        );
    }

    /**
     * @return bool
     * True if this type has any generic types
     */
    public function hasGenericArray() : bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return (false !==
            $this->type_set->find(function (Type $type) : bool {
                return $type->isGenericArray();
            })
        );
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
            $this->type_set->filter(function (Type $type) : bool {
                return $type->isGenericArray();
            })->map(function (Type $type) : Type {
                return $type->genericArrayElementType();
            })
        );

        // If array is in there, then it can be any type
        // Same for mixed
        if ($this->hasType(ArrayType::instance(false))
            || $this->hasType(MixedType::instance(false))
        ) {
            $union_type->addType(MixedType::instance(false));
        }

        if ($this->hasType(ArrayType::instance(false))) {
            $union_type->addType(NullType::instance(false));
        }

        return $union_type;
    }

    /**
     * @param Closure $closure
     * A closure mapping `Type` to `Type`
     *
     * @return UnionType
     * A new UnionType with each type mapped through the
     * given closure
     */
    public function asMappedUnionType(\Closure $closure) : UnionType
    {
        return new UnionType($this->type_set->map($closure));
    }

    /**
     * @return UnionType
     * Get a new type for each type in this union which is
     * the generic array version of this type. For instance,
     * 'int|float' will produce 'int[]|float[]'.
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
        assert(
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
        $this->type_set = new Set(
            array_map(function (string $type_name) {
                return Type::fromFullyQualifiedString($type_name);
            }, explode('|', $serialized ?? ''))
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
        $type_name_list =
            array_map(function (Type $type) : string {
                return (string)$type;
            }, $this->getTypeSet()->toArray());

        // Sort the types so that we get a stable
        // representation
        asort($type_name_list);

        // Join them with a pipe
        return implode('|', $type_name_list);
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
                $map[strtolower($key)] = $value;
            }
        }

        return $map;
    }
}
