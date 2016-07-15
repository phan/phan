<?php declare(strict_types=1);
namespace Phan\Language;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NodeTypeKindVisitor;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\VoidType;
use Phan\Language\Type\StaticType;
use ast\Node;

class Type
{
    use \Phan\Memoize;

    /**
     * @var string
     * The namespace of this type such as '\' or
     * '\Phan\Language'
     */
    protected $namespace = null;

    /**
     * @var string
     * The name of this type such as 'int' or 'MyClass'
     */
    protected $name = null;

    /**
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     */
    protected function __construct(
        string $namespace,
        string $name
    ) {
        $this->namespace = $namespace;
        $this->name = $name;
    }

    /**
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     */
    protected static function make(
        string $namespace,
        string $name
    ) : Type {
        assert(
            $namespace && 0 === strpos(
                $namespace,
                '\\'
            ),
            "Namespace must be fully qualified"
        );

        assert(
            !empty($namespace),
            "Namespace cannot be empty"
        );

        assert(
            '\\' === $namespace[0],
            "Namespace must be fully qualified"
        );

        assert(
            !empty($name),
            "Type name cannot be empty"
        );

        assert(
            false === strpos(
                $name,
                '|'
            ),
            "Type name may not contain a pipe."
        );

        // Create a canonical representation of the
        // namespace and name
        $namespace = $namespace ?: '\\';

        if ('\\' === $namespace) {
            $name = self::canonicalNameFromName($name);
        }

        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_map = [];
        $key = $namespace . '\\' . $name;
        if (empty($canonical_object_map[strtolower($key)])) {
            $canonical_object_map[strtolower($key)] =
                new static($namespace, $name);
        }

        return $canonical_object_map[strtolower($key)];
    }

    /**
     * This is the base level constructor for types
     *
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @return Type
     * A type representing the given path is returned. Note
     * that we return cached types. Don't attempt to change
     * a type once you get it.
     */
    public static function fromNamespaceAndName(
        string $namespace,
        string $type_name
    ) : Type {
        $namespace = trim($namespace);

        return self::memoizeStatic(
            $namespace . '\\' . $type_name,
            function () use ($namespace, $type_name) : Type {
                // Only if we're in the root namespace can we
                // canonicalize native types.
                if ('\\' === $namespace) {
                    $type_name = self::canonicalNameFromName($type_name);
                }

                // If this looks like a generic type string, explicitly
                // make it as such
                if (self::isGenericArrayString($type_name)
                    && ($pos = strrpos($type_name, '[]')) !== false
                ) {
                    return GenericArrayType::fromElementType(Type::make(
                        $namespace,
                        substr($type_name, 0, $pos)
                    ));
                }

                // If we have a namespace, we're all set
                return Type::make($namespace, $type_name);
            }
        );
    }

    /**
     * @return Type
     * Get a type for the given object
     */
    public static function fromObject($object) : Type
    {
        return Type::fromInternalTypeName(gettype($object));
    }

    /**
     * @return Type
     * Get a type for the given type name
     */
    public static function fromInternalTypeName(
        string $type_name
    ) : Type {
        // If this is a generic type (like int[]), return
        // a generic of internal types.
        if (false !== ($pos = strrpos($type_name, '[]'))) {
            return GenericArrayType::fromElementType(
                self::fromInternalTypeName(
                    substr($type_name, 0, $pos)
                )
            );
        }

        $type_name =
            self::canonicalNameFromName($type_name);

        switch (strtolower($type_name)) {
            case 'array':
                return ArrayType::instance();
            case 'bool':
                return BoolType::instance();
            case 'callable':
                return CallableType::instance();
            case 'float':
                return FloatType::instance();
            case 'int':
                return IntType::instance();
            case 'mixed':
                return MixedType::instance();
            case 'null':
                return NullType::instance();
            case 'object':
                return ObjectType::instance();
            case 'resource':
                return ResourceType::instance();
            case 'string':
                return StringType::instance();
            case 'void':
                return VoidType::instance();
            case 'static':
                return StaticType::instance();
        }

        assert(
            false,
            "No internal type with name $type_name"
        );
    }

    /**
     * @param string $fully_qualified_string
     * A fully qualified type name
     *
     * @param Context $context
     * The context in which the type string was
     * found
     *
     * @return UnionType
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) : Type {
        assert(
            !empty($fully_qualified_string),
            "Type cannot be empty"
        );

        if (0 !== strpos($fully_qualified_string, '\\')) {
            return self::fromInternalTypeName($fully_qualified_string);
        }

        list($namespace, $type_name) =
            self::namespaceAndTypeFromString(
                $fully_qualified_string
            );

        assert(
            !empty($namespace) && !empty($type_name),
            "Type was not fully qualified"
        );

        return self::fromNamespaceAndName(
            $namespace,
            $type_name
        );
    }

    /**
     * @param string $string
     * A string representing a type
     *
     * @param Context $context
     * The context in which the type string was
     * found
     *
     * @return Type
     * Parse a type from the given string
     */
    public static function fromStringInContext(
        string $string,
        Context $context
    ) : Type {

        assert(
            $string !== '',
            "Type cannot be empty"
        );

        $namespace = null;

        // Extract the namespace if the type string is
        // fully-qualified
        if ('\\' === $string[0]) {
            list($namespace, $string) =
                self::namespaceAndTypeFromString($string);
        }

        $type_name = $string;

        // @var bool
        // True if this type name if of the form 'C[]'
        $is_generic_array_type =
            self::isGenericArrayString($type_name);

        // If this is a generic array type, get the name of
        // the type of each element
        $non_generic_array_type_name = $type_name;
        if ($is_generic_array_type
           && false !== ($pos = strrpos($type_name, '[]'))
        ) {
            $non_generic_array_type_name =
                substr($type_name, 0, $pos);
        }

        // Check to see if the type name is mapped via
        // a using clause.
        //
        // Gotta check this before checking for native types
        // because there are monsters out there that will
        // remap the names via things like `use \Foo\String`.
        if ($context->hasNamespaceMapFor(
            T_CLASS,
            $non_generic_array_type_name
        )) {
            $fqsen =
                $context->getNamespaceMapFor(
                    T_CLASS,
                    $non_generic_array_type_name
                );

            if ($is_generic_array_type) {
                return GenericArrayType::fromElementType(Type::make(
                    $fqsen->getNamespace(),
                    $fqsen->getName()
                ));
            }

            return Type::make(
                $fqsen->getNamespace(),
                $fqsen->getName()
            );
        }

        // If this was a fully qualified type, we're all
        // set
        if (!empty($namespace)) {
            return self::fromNamespaceAndName(
                $namespace,
                $type_name
            );
        }

        if ($is_generic_array_type
           && self::isNativeTypeString($type_name)
        ) {
            return self::fromInternalTypeName($type_name);
        } else {
            // Check to see if its a builtin type
            switch (strtolower(self::canonicalNameFromName($type_name))) {
                case 'array':
                    return \Phan\Language\Type\ArrayType::instance();
                case 'bool':
                    return \Phan\Language\Type\BoolType::instance();
                case 'callable':
                    return \Phan\Language\Type\CallableType::instance();
                case 'float':
                    return \Phan\Language\Type\FloatType::instance();
                case 'int':
                    return \Phan\Language\Type\IntType::instance();
                case 'mixed':
                    return \Phan\Language\Type\MixedType::instance();
                case 'null':
                    return \Phan\Language\Type\NullType::instance();
                case 'object':
                    return \Phan\Language\Type\ObjectType::instance();
                case 'resource':
                    return \Phan\Language\Type\ResourceType::instance();
                case 'string':
                    return \Phan\Language\Type\StringType::instance();
                case 'void':
                    return \Phan\Language\Type\VoidType::instance();
                case 'static':
                    return \Phan\Language\Type\StaticType::instance();

            }
        }

        // Things like `self[]` or `$this[]`
        if ($is_generic_array_type
            && self::isSelfTypeString($non_generic_array_type_name)
            && $context->isInClassScope()
        ) {
            // Callers of this method should be checking on their own
            // to see if this type is a reference to 'parent' and
            // dealing with it there. We don't want to have this
            // method be dependent on the code base
            assert(
                'parent' !== $non_generic_array_type_name,
                __METHOD__ . " does not know how to handle the type name 'parent'"
            );

            return GenericArrayType::fromElementType(
                static::fromFullyQualifiedString(
                    (string)$context->getClassFQSEN()
                )
            );
        }

        // If this is a type referencing the current class
        // in scope such as 'self' or 'static', return that.
        if (self::isSelfTypeString($type_name)
            && $context->isInClassScope()
        ) {
            // Callers of this method should be checking on their own
            // to see if this type is a reference to 'parent' and
            // dealing with it there. We don't want to have this
            // method be dependent on the code base
            assert(
                'parent' !== $type_name,
                __METHOD__ . " does not know how to handle the type name 'parent'"
            );

            return static::fromFullyQualifiedString(
                (string)$context->getClassFQSEN()
            );
        }

        // Attach the context's namespace to the type name
        return self::fromNamespaceAndName(
            $context->getNamespace() ?: '\\',
            $type_name
        );
    }

    /**
     * @return UnionType
     * A UnionType representing this and only this type
     */
    public function asUnionType() : UnionType
    {
        return new UnionType([$this]);
    }

    /**
     * @return FQSEN
     * A fully-qualified structural element name derived
     * from this type
     */
    public function asFQSEN() : FQSEN
    {
        return FullyQualifiedClassName::fromType($this);
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return bool
     * True if this namespace is defined
     */
    public function hasNamespace() : bool
    {
        return !empty($this->namespace);
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace() : string
    {
        return $this->namespace;
    }

    /**
     * @return bool
     * True if this is a native type (like int, string, etc.)
     *
     */
    public function isNativeType() : bool
    {
        return self::isNativeTypeString((string)$this);
    }

    /**
     * @return bool
     * True if this is a native type (like int, string, etc.)
     *
     * @see \Phan\Deprecated\Util::is_native_type
     * Formerly `function is_native_type`
     */
    private static function isNativeTypeString(string $type_name) : bool
    {
        return in_array(
            str_replace('[]', '', strtolower($type_name)),
            [
                'int',
                'float',
                'bool',
                'true',
                'string',
                'closure',
                'callable',
                'array',
                'null',
                'object',
                'resource',
                'mixed',
                'void'
            ]
        );
    }

    /**
     * @return bool
     * True if this type is a type referencing the
     * class context in which it exists such as 'static'
     * or 'self'.
     */
    public function isSelfType() : bool
    {
        return self::isSelfTypeString((string)$this);
    }

    /**
     * @return bool
     * True if this type is a type referencing the
     * class context 'static'.
     */
    public function isStaticType() : bool
    {
        return ('static' === (string)$this || '\\static' === (string)$this);
    }

    /**
     * @param string $type_string
     * A string defining a type such as 'self' or 'int'.
     *
     * @return bool
     * True if the given type references the class context
     * in which it exists such as 'static' or 'self'.
     */
    public static function isSelfTypeString(
        string $type_string
    ) : bool {
        return in_array(strtolower($type_string), [
            'self', '$this', 'parent',
            '\self', '\$this', '\parent'
        ]);
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
        return in_array((string)$this, [
            'int',
            'float',
            'bool',
            'true',
            'string',
            'null'
        ]);
    }

    /**
     * @return bool
     * True if this type is array-like (is of type array, is
     * a generic array, or implements ArrayAccess).
     */
    public function isArrayLike() : bool
    {
        $array_access_type =
            Type::fromNamespaceAndName('\\', 'ArrayAccess');

        return (
            $this == ArrayType::instance()
            || $this->isGenericArray()
            || $this === $array_access_type
        );
    }

    /**
     * @return bool
     * True if this is a generic type such as 'int[]' or
     * 'string[]'.
     */
    public function isGenericArray() : bool
    {
        return self::isGenericArrayString($this->name);
    }

    /**
     * @param string $type_name
     * A non-namespaced type name like 'int[]'
     *
     * @return bool
     * True if this is a generic type such as 'int[]' or
     * 'string[]'.
     */
    private static function isGenericArrayString(string $type_name) : bool
    {
        if (in_array($type_name, ['[]', 'array'])) {
            return false;
        }
        return (strpos($type_name, '[]') !== false);
    }

    /**
     * @return Type
     * A variation of this type that is not generic.
     * i.e. 'int[]' becomes 'int'.
     */
    public function genericArrayElementType() : Type
    {
        assert(
            $this->isGenericArray(),
            "Cannot call genericArrayElementType on non-generic array"
        );

        if (($pos = strrpos($this->name, '[]')) !== false) {
            assert(
                $this->name !== '[]' && $this->name !== 'array',
                "Non-generic type requested to be non-generic"
            );

            return Type::make(
                $this->getNamespace(),
                substr($this->name, 0, $pos)
            );
        }

        return $this;
    }

    /**
     * @return Type
     * Get a new type which is the generic array version of
     * this type. For instance, 'int' will produce 'int[]'.
     */
    public function asGenericArrayType() : Type
    {
        if ($this->name == 'array'
            || $this->name == 'mixed'
            || strpos($this->name, '[]') !== false
        ) {
            return ArrayType::instance();
        }

        return GenericArrayType::fromElementType($this);
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
     * Expands class types to all inherited classes returning
     * a superset of this type.
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ) : UnionType {
        return $this->memoize(__METHOD__, function () use (
            $code_base,
            $recursion_depth
        ) : UnionType {

            // We're going to assume that if the type hierarchy
            // is taller than some value we probably messed up
            // and should bail out.
            assert(
                $recursion_depth < 20,
                "Recursion has gotten out of hand"
            );

            if ($this->isNativeType() && !$this->isGenericArray()) {
                return $this->asUnionType();
            }

            $union_type = $this->asUnionType();

            $class_fqsen = $this->isGenericArray()
                ? $this->genericArrayElementType()->asFQSEN()
                : $this->asFQSEN();

            if (!($class_fqsen instanceof FullyQualifiedClassName)) {
                return $union_type;
            }

            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                return $union_type;
            }

            $clazz = $code_base->getClassByFQSEN($class_fqsen);

            $union_type->addUnionType(
                $this->isGenericArray()
                    ?  $clazz->getUnionType()->asGenericArrayTypes()
                    : $clazz->getUnionType()
            );

            // Resurse up the tree to include all types
            $recursive_union_type = new UnionType();
            foreach ($union_type->getTypeSet() as $clazz_type) {
                if ((string)$clazz_type != (string)$this) {
                    $recursive_union_type->addUnionType(
                        $clazz_type->asExpandedTypes(
                            $code_base,
                            $recursion_depth + 1
                        )
                    );
                } else {
                    $recursive_union_type->addType($clazz_type);
                }
            }

            return $recursive_union_type;
        });
    }

    public function isSubclassOf(CodeBase $code_base, Type $parent)
    {
        $this_clazz = $code_base->getClassByFQSEN(
            $this->asFQSEN()
        );

        $parent_clazz = $code_base->getClassByFQSEN(
            $parent->asFQSEN()
        );

        return $this_clazz->isSubclassOf($code_base, $parent_clazz);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToType(Type $type) : bool
    {
        if ($this === $type) {
            return true;
        }

        $s = strtolower((string)$this);
        $d = strtolower((string)$type);

        if ($s[0]=='\\') {
            $s = substr($s, 1);
        }

        if ($d[0]=='\\') {
            $d = substr($d, 1);
        }

        if ($s===$d) {
            return true;
        }

        if (Config::get()->scalar_implicit_cast) {
            if ($type->isScalar() && $this->isScalar()) {
                return true;
            }
        }

        if ($s==='int' && $d==='float') {
            return true; // int->float is ok
        }

        if (($s==='array'
            || $s==='string'
            || (strpos($s, '[]')!==false)
            || $s==='closure')
            && $d==='callable'
        ) {
            return true;
        }

        if ($s === 'object'
            && !$type->isScalar()
            && $d!=='array'
        ) {
            return true;
        }

        if ($d === 'object' &&
            !$this->isScalar()
            && $s!=='array'
        ) {
            return true;
        }

        if (strpos($s, '[]') !== false
            && ($d == 'array' || $d == '\ArrayAccess')
        ) {
            return true;
        }

        if (strpos($d, '[]') !== false
            && $s==='array'
        ) {
            return true;
        }

        if ($s === 'callable' && $d === 'closure') {
            return true;
        }

        if (($pos = strrpos($d, '\\')) !== false) {
            if ('\\' !== $this->getNamespace()) {
                if (trim(
                    $this->getNamespace().'\\'.$s,
                    '\\'
                ) == $d
                ) {
                    return true;
                }
            } else {
                if (substr($d, $pos+1) === $s) {
                    return true; // Lazy hack, but...
                }
            }
        }

        if (($pos = strrpos($s, '\\')) !== false) {
            if ('\\' !== $type->getNamespace()) {
                if (trim(
                    $type->getNamespace().'\\'.$d,
                    '\\'
                ) == $s
                ) {
                    return true;
                }
            } else {
                if (substr($s, $pos+1) === $d) {
                    return true; // Lazy hack, but...
                }
            }
        }

        return false;
    }

    /**
     * @return string
     * A human readable representation of this type
     */
    public function __toString()
    {
        if (!$this->hasNamespace()) {
            return $this->name;
        }

        if ('\\' === $this->namespace) {
            return '\\' . $this->name;
        }

        return "{$this->namespace}\\{$this->name}";
    }

    /**
     * @param string $type_name
     * Any type name
     *
     * @return string
     * A canonical name for the given type name
     */
    private static function canonicalNameFromName(
        string $name
    ) : string {
        static $map = [
            'NULL'     => 'null',
            'boolean'  => 'bool',
            'callback' => 'callable',
            'double'   => 'float',
            'false'    => 'bool',
            'true'     => 'bool',
            'integer'  => 'int',
        ];

        return $map[strtolower($name)] ?? $name;
    }

    /**
     * @return string[]
     * A pair with the 0th element being the namespace and the first
     * element being the type name.
     */
    private static function namespaceAndTypeFromString(
        string $type_name
    ) : array {
        $fq_class_name_elements =
            array_filter(explode('\\', $type_name));

        $class_name =
            array_pop($fq_class_name_elements);

        $namespace =
            '\\' . implode('\\', array_filter(
                $fq_class_name_elements
            ));

        return [$namespace, $class_name];
    }
}
