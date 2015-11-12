<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Type;
use \Phan\Language\Type\{
    ArrayType,
    FloatType,
    IntType,
    MixedType,
    NoneType,
    NullType,
    StringType,
    VoidType
};
use \Phan\Language\Type\NodeTypeKindVisitor;
use \ast\Node;

class UnionType extends \ArrayObject  {
    use \Phan\Language\AST;
    use \Phan\Memoize;

    /**
     * @param Type[] $type_list
     * An optional list of types represented by this union
     */
    public function __construct(array $type_list = []) {
        foreach ($type_list as $type) {
            $this->addType($type);
        }

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
        if (empty($fully_qualified_string)) {
            return new UnionType();
        }

        return new UnionType(
            array_map(function(string $type_name) {
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
     * @return UnionType
     */
    public static function fromStringInContext(
        string $type_string,
        Context $context
    ) : UnionType {
        if (empty($type_string)) {
            return new UnionType();
        }

        return new UnionType(
            array_map(function(string $type_name) use ($context, $type_string) {
                assert($type_name !== '',
                    "Type cannot be empty. Type '$type_name' given as part of the union type '$type_string' in $context.");
                return Type::fromStringInContext(
                    $type_name,
                    $context
                );
            }, array_filter(array_map(function (string $type_name) {
                return trim($type_name);
            }, explode('|', $type_string))))
        );
    }

    /**
     * ast_node_type() is for places where an actual type
     * name appears. This returns that type name. Use node_type()
     * instead to figure out the type of a node
     *
     * @param Context $context
     * @param null|string|Node $node
     *
     * @see \Phan\Deprecated\AST::ast_node_type
     */
    public static function fromSimpleNode(
        Context $context,
        $node
    ) : UnionType {
        return self::astUnionTypeFromSimpleNode($context, $node);
    }

    /**
     * @param Context $context
     * @param Node|string|null $node
     *
     * @return UnionType
     *
     * @see \Phan\Deprecated\Pass2::node_type
     * Formerly 'function node_type'
     */
    public static function fromNode(
        Context $context,
        $node
    ) : UnionType {
        if(!($node instanceof Node)) {
            if($node === null) {
                return new UnionType();
            }

            return Type::fromObject($node)->asUnionType();
        }

        return (new Element($node))->acceptKindVisitor(
            new NodeTypeKindVisitor($context)
        );
	}

    public static function builtinClassPropertyType(
        string $class_name,
        string $property_name
    ) : UnionType {
        $map = self::builtinClassTypeMap();

        $class_property_type_map =
            $map[strtolower($class_name)]['properties'];

        $property_type_name =
            $class_property_type_map[$property_name];

        return new UnionType([$property_type_name]);
    }

    /**
     * @return UnionType[]
     * A list of types for parameters associated with the
     * given builtin function with the given name
     *
     * @see internal_varargs_check
     * Formerly `function internal_varargs_check`
     */
    public static function builtinFunctionPropertyNameTypeMap(
        FQSEN $function_fqsen,
        CodeBase $code_base
    ) : array {
        $context = new Context($code_base);

        $map = self::builtinFunctionArgumentTypeMap();

        // Get the simple name of the function
        $function_name = $function_fqsen->getMethodName();

        // See if we have a mapping
        if (!isset($map[$function_name])) {
            return [];
        }

        $type_name_struct = $map[$function_name];
        if (empty($type_name_struct)) {
            return [];
        }

        // Figure out the return type
        $return_type_name = array_shift($type_name_struct);
        $return_type = $return_type_name
            ? UnionType::fromStringInContext($return_type_name, $context)
            : null;

        $name_type_name_map = $type_name_struct;

        $property_name_type_map = [];

        foreach ($name_type_name_map as $name => $type_name) {
            $property_name_type_map[$name] = empty($type_name)
                ? new UnionType()
                : UnionType::fromStringInContext($type_name, $context);
        }

        return [
            'return_type' => $return_type,
            'property_name_type_map' => $property_name_type_map,
        ];
    }

    /**
     * @return bool
     * True if a builtin with the given FQSEN exists, else
     * flase.
     */
    public static function builtinExists(FQSEN $fqsen) : bool {
        return !empty(
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[(string)$fqsen]
        );
    }

    /**
     * @return Type
     * Get the first type in this set
     */
    public function head() : Type {
        assert(count($this) > 0,
            'Cannot call head() on empty UnionType');

        return array_values($this->getArrayCopy())[0];
    }

    /**
     * Add a type name to the list of types
     *
     * @return null
     */
    public function addType(Type $type) {
        // Only allow unique elements
        $this[(string)$type] = $type;
    }

    /**
     * Add the given types to this type
     *
     * @return null
     */
    public function addUnionType(UnionType $union_type) {
        foreach ($union_type as $i => $type) {
            $this->addType($type);
        }
    }

    /**
     * @return bool
     * True if this union type contains the given named
     * type.
     */
    public function hasType(Type $type) : bool {
        return isset($this[(string)$type]);
    }

    /**
     * @return bool
     * True if this type has a type referencing the
     * class context in which it exists such as 'static'
     * or 'self'.
     */
    public function hasSelfType() : bool {
        return array_reduce($this->getArrayCopy(),
            function (bool $carry, Type $type) : bool {
                return ($carry || $type->isSelfType());
            }, false);
    }

    /**
     * @return bool
     * True if and only if this UnionType contains
     * the given type and no others.
     */
    public function isType(Type $type) : bool {
        if (count($this) != 1) {
            return false;
        }

        return ($this->head() === $type);
    }

    /**
     * @return bool
     * True if this UnionType is exclusively native
     * types
     */
    public function isNativeType() : bool {
        if (empty($this)) {
            return false;
        }

        return array_reduce($this->getArrayCopy(),
            function (bool $carry, Type $type) : bool {
                return ($carry && $type->isNativeType());
            }, true);
    }

    /**
     * @return bool
     * True iff this union contains the exact set of types
     * represented in the given union type.
     */
    public function isEqualTo(UnionType $union_type) : bool {
        return ((string)$this === (string)$union_type);
    }

    /**
     * @param Type[] $type_list
     * A list of types
     *
     * @return bool
     * True if this union type contains any of the given
     * named types
     */
    public function hasAnyType(array $type_list) : bool {
        return array_reduce($type_list,
            function(bool $carry, Type $type)  {
                return ($carry || $this->hasType($type));
            }, false);
    }

    /**
     * @return int
     * The number of types in this union type
     */
    public function typeCount() : int {
        return count($this);
    }

    /**
     * @return bool
     * True if this Union has no types
     */
    public function isEmpty() : bool {
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
            $this_expanded->canCastToUnionType($target_expanded);
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
        if($this->isEmpty() || $target->isEmpty()) {
            return true;
        }

        // T === T
        if ($this->isEqualTo($target)) {
            return true;
        }

        // null <-> null
        if ($this->isType(NullType::instance())
            || $target->isType(NullType::instance())
        ) {
            return true;
        }

        // mixed <-> mixed
        if ($target->hasType(MixedType::instance())
            || $this->hasType(MixedType::instance())
        ) {
            return true;
        }

        // int -> float
        if ($this->isType(IntType::instance())
            && $target->isType(FloatType::instance())
        ) {
            return true;
        }

        // Check conversion on the cross product of all
        // type combinations and see if any can cast to
        // any.
        foreach($this as $source_type) {
            if(empty($source_type)) {
                continue;
            }
            foreach($target as $target_type) {
                if(empty($target_type)) {
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
    public function isScalar() : bool {
        if ($this->isEmpty() || count($this) > 1) {
            return false;
        }

        return $this->head()->isScalar();
    }

    /**
     * Takes "a|b[]|c|d[]|e" and returns "b|d"
     *
     * @return UnionType
     * The subset of types in this
     */
    public function asNonGenericTypes() : UnionType {
        // If array is in there, then it can be any type
        // Same for |mixed|
        if ($this->hasType(ArrayType::instance())
            || $this->hasType(MixedType::instance())
        ) {
            return MixedType::instance()->asUnionType();
        }

        if ($this->hasType(ArrayType::instance())) {
            return NoneType::instance()->toUnionType();
        }

        return new UnionType(array_filter(array_map(
            function(Type $type) {
                if (!$type->isGeneric()) {
                    return null;
                }
                return $type->asNonGenericType();
            }, $this->getArrayCopy()))
        );
    }

    /**
     * @return UnionType
     * Get the subset of types which are not native
     */
    public function nonNativeTypes() : UnionType {
        return new UnionType(
            array_filter($this->getArrayCopy(), function(Type $type) {
                return !$type->isNativeType();
            })
        );
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
    public function nonGenericTypes() : UnionType {
        return new UnionType(
            array_filter($this->getArrayCopy(), function(Type $type) {
                return !$type->isGeneric();
            })
        );
    }

    /**
     * @return bool
     * True if this is exclusively generic types
     */
    public function isGeneric() : bool {
        if ($this->isEmpty()) {
            return false;
        }

        return array_reduce($this->getArrayCopy(),
            function (bool $carry, Type $type) : bool {
                return ($carry && $type->isGeneric());
            }, true);
    }

    /**
     * @return UnionType
     * Get a new type for each type in this union which is
     * the generic array version of this type. For instance,
     * 'int|float' will produce 'int[]|float[]'.
     */
    public function asGenericTypes() : UnionType {
        return new UnionType(
            array_map(function (Type $type) : Type {
                return $type->asGenericType();
            }, $this->getArrayCopy())
        );
    }

    /**
     * @param CodeBase
     * The code base to use in order to find super classes, etc.
     *
     * @return UnionType
     * Expands all class types to all inherited classes returning
     * a superset of this type.
     */
    public function asExpandedTypes(CodeBase $code_base) : UnionType {
        $union_type = clone($this);

        foreach ($this as $type) {
            $union_type->addUnionType(
                $type->asExpandedTypes($code_base)
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
    public function serialize() : string {
        return (string)$this;
    }

    /**
     * As per the Serializable interface
     *
     * @param string $serialized
     * A serialized UnionType
     *
     * @return UnionType
     * A UnionType representing the given serialized form
     *
     * @see \Serializable
     */
    public function unserialize($serialized) {
        return self::fromFullyQualifiedString($serialized);
    }

    /**
     * @return string
     * A human-readable string representation of this union
     * type
     */
    public function __toString() : string {
        // Sort the types so that we get a stable
        // representation
        self::natsort();

        // Delimit by '|'
        return implode('|', array_map(function(Type $type) : string {
            return (string)$type;
        }, $this->getArrayCopy()));
    }

    /**
     * @return array
     * A map from builtin function name to type information
     *
     * @see \Phan\Language\Type\BuiltinFunctionArgumentTypes
     */
    private static function builtinFunctionArgumentTypeMap() {
        static $map = false;
        return $map ?:
            ($map = require(__DIR__.'/Type/BuiltinFunctionArgumentTypes.php'));
    }

    /**
     * @return array
     * A map from builtin class names to type information
     *
     * @see \Phan\Language\Type\BuiltinFunctionArgumentTypes
     */
    private static function builtinClassTypeMap() {
        static $map = false;
        return $map ?:
            ($map = require(__DIR__.'/Type/BuiltinClassTypes.php'));
    }

}
