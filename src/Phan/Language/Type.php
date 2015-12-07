<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;
use \Phan\Language\AST;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\Type\NodeTypeKindVisitor;
use \Phan\Language\Type\{
    ArrayType,
    BoolType,
    CallableType,
    FloatType,
    GenericArrayType,
    IntType,
    MixedType,
    NativeType,
    NullType,
    ObjectType,
    ResourceType,
    ScalarType,
    StringType,
    VoidType
};
use \Phan\Language\UnionType;
use \ast\Node;

class Type {
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
        assert($namespace && 0 === strpos($namespace, '\\'),
            "Namespace must be fully qualified");

        assert(!empty($namespace),
            "Namespace cannot be empty");

        assert('\\' === $namespace[0],
            "Namespace must be fully qualified");

        if (empty($name)) {
            debug_print_backtrace(3);
        }

        assert(!empty($name),
            "Type name cannot be empty");

        assert(false === strpos($name, '|'),
            "Type name '{$name}' may not contain a pipe.");

        $this->namespace = $namespace ?: '\\';

        if ('\\' === $this->namespace) {
            $this->name = self::canonicalNameFromName($name);
        } else {
            $this->name = strtolower($name);
        }
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
        $type_name = strtolower(trim($type_name));
        $namespace = trim($namespace);

        return self::memoizeStatic($namespace . '\\' . $type_name,
            function() use ($namespace, $type_name) : Type {
                // Only if we're in the root namespace can we
                // canonicalize native types.
                if ('\\' === $namespace) {
                    $type_name = self::canonicalNameFromName($type_name);
                }

                // If this looks like a generic type string, explicitly
                // make it as such
                if (self::isGenericString($type_name)
                    && ($pos = strpos($type_name, '[]')) !== false
                ) {
                    return new GenericArrayType(new Type(
                        $namespace,
                        substr($type_name, 0, $pos)
                    ));
                }

                // If we have a namespace, we're all set
                return new Type($namespace, $type_name);
            });
    }

    /**
     * @return Type
     * Get a type for the given object
     */
    public static function fromObject($object) : Type {
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
        if (false !== ($pos = strpos($type_name, '[]'))) {
            return new GenericArrayType(
                self::fromInternalTypeName(
                    substr($type_name, 0, $pos)
                )
            );
        }

        $type_name =
            self::canonicalNameFromName($type_name);

        switch ($type_name) {
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
        }

        assert(false,
            "No internal type with name $type_name");
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
        assert(!empty($fully_qualified_string),
            "Type cannot be empty");

        if (0 !== strpos($fully_qualified_string, '\\')) {
            return self::fromInternalTypeName($fully_qualified_string);
        }

        list($namespace, $type_name) =
            self::namespaceAndTypeFromString(
                $fully_qualified_string
            );

        assert(!empty($namespace) && !empty($type_name),
            "Type '$fully_qualified_string' was not fully qualified");

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

        assert($string !== '' ,
            "Type cannot be empty in $context");

        $namespace = null;

        // Extract the namespace if the type string is
        // fully-qualified
        if ('\\' === $string[0]) {
            list($namespace, $string) =
                self::namespaceAndTypeFromString($string);
        }

        $type_name = strtolower($string);

        // Check to see if the type name is mapped via
        // a using clause.
        //
        // Gotta check this before checking for native types
        // because there are monsters out there that will
        // remap the names via things like `use \Foo\String`.
        if ($context->hasNamespaceMapFor(T_CLASS, $type_name)) {
            $fqsen =
                $context->getNamespaceMapFor(T_CLASS, $type_name);

            return new Type(
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

        // Check to see if its a builtin type
        switch (self::canonicalNameFromName($type_name)) {
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
    public function asUnionType() : UnionType {
        return new UnionType([$this]);
    }

    /**
     * @return FQSEN
     * A fully-qualified structural element name derived
     * from this type
     */
    public function asFQSEN() : FQSEN {
        return FullyQualifiedClassName::fromType($this);
    }

    /**
     * @return Type
     * Get a new type which is the generic array version of
     * this type. For instance, 'int' will produce 'int[]'.
     */
    public function asGenericType() : Type {
        if ($this->name == 'array'
            || $this->name == 'mixed'
            || strpos($this->name, '[]') !== false
        ) {
            return ArrayType::instance();
        }

        return new \Phan\Language\Type\GenericArrayType($this);
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * @return bool
     * True if this namespace is defined
     */
    public function hasNamespace() : bool {
        return !empty($this->namespace);
    }

    /**
     * @return bool
     * True if this namespace is defined
     */
    public function isFullyQualified() : bool {
        if (!$this->hasNamespace()) {
            return false;
        }

        // Check to see if our namespace is fully
        // qualified
        return (0 !== strpos('\\', $this->getNamespace()));
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace() : string {
        return $this->namespace;
    }

    /**
     * @return bool
     * True if this is a native type (like int, string, etc.)
     *
     * @see \Phan\Deprecated\Util::is_native_type
     * Formerly `function is_native_type`
     */
    public function isNativeType() : bool {
        return in_array(
            str_replace('[]', '', (string)$this), [
                '\\int',
                '\\float',
                '\\bool',
                '\\true',
                '\\string',
                '\\callable',
                '\\array',
                '\\null',
                '\\object',
                '\\resource',
                '\\mixed',
                '\\void'
            ]
        );
    }

    /**
     * @return bool
     * True if this type is a type referencing the
     * class context in which it exists such as 'static'
     * or 'self'.
     */
    public function isSelfType() : bool {
        return in_array((string)$this, [
            'static', 'self', '$this',
            '\static', '\self', '\$this'
        ]);
    }

    /**
     * @return bool
     * True if all types in this union are scalars
     *
     * @see \Phan\Deprecated\Util::type_scalar
     * Formerly `function type_scalar`
     */
    public function isScalar() : bool {
        return in_array((string)$this, [
            '\\int',
            '\\float',
            '\\bool',
            '\\true',
            '\\string',
            '\\null'
        ]);
    }

    /**
     * @return bool
     * True if this is a generic type such as 'int[]' or
     * 'string[]'.
     */
    public function isGeneric() : bool {
        return self::isGenericString($this->name);
    }

    /**
     * @param string $type_name
     * A non-namespaced type name like 'int[]'
     *
     * @return bool
     * True if this is a generic type such as 'int[]' or
     * 'string[]'.
     */
    private static function isGenericString(string $type_name) : bool {
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
    public function asNonGenericType() : Type {
        if (($pos = strpos($this->name, '[]')) !== false) {
            assert($this->name !== '[]' && $this->name !== 'array',
                "Non-generic type '{$this->name}' requested to be non-generic");

            return new Type(
                $this->getNamespace(),
                substr($this->name, 0, $pos)
            );
        }

        return $this;
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
        return $this->memoize(__METHOD__, function() use(
            $code_base, $recursion_depth
        ) : UnionType {

            assert($recursion_depth < 10,
                "Recursion has gotten out of hand for type $this");

            if ($this->isNativeType()) {
                return $this->asUnionType();
            }

            $union_type = $this->asUnionType();

            $class_fqsen = $this->isGeneric()
                ? $this->asNonGenericType()->asFQSEN()
                : $this->asFQSEN();

            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                return $union_type;
            }

            $clazz = $code_base->getClassByFQSEN($class_fqsen);

            $union_type->addUnionType(
                $this->isGeneric()
                    ?  $clazz->getUnionType()->asGenericTypes()
                    : $clazz->getUnionType()
            );

            // Resurse up the tree to include all types
            $recursive_union_type = new UnionType();
            foreach ($union_type->getTypeList() as $clazz_type) {
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

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToType(Type $type) : bool {
        if ($this === $type) {
            return true;
        }

        /*
        if(substr($source,0,9) == 'callable:') {
            $s = 'callable';
        }

        if(substr($d,0,9)=='callable:') {
            $d = 'callable';
        }
        */

        $s = (string)$this;
        $d = (string)$type;

        if($s[0]=='\\') {
            $s = substr($s,1);
        }

        if($d[0]=='\\') {
            $d = substr($d,1);
        }

        if($s===$d) {
            return true;
        }

        if($s==='int' && $d==='float') {
            return true; // int->float is ok
        }

        if(($s==='array'
            || $s==='string'
            || (strpos($s,'[]')!==false))
            && $d==='callable'
        ) {
            return true;
        }

        if($s === 'object'
            && !$type->isScalar()
            && $d!=='array'
        ) {
            return true;
        }

        if($d === 'object' &&
            !$this->isScalar()
            && $s!=='array'
        ) {
            return true;
        }

        if(strpos($s,'[]') !== false
            && $d==='array'
        ) {
            return true;
        }

        if(strpos($d,'[]') !== false
            && $s==='array'
        ) {
            return true;
        }

        if(($pos = strrpos($d, '\\')) !== false) {
            if ('\\' !== $this->getNamespace()) {
                if(trim($this->getNamespace().'\\'.$s,
                    '\\') == $d
                ) {
                    return true;
                }
            } else {
                if(substr($d, $pos+1) === $s) {
                    return true; // Lazy hack, but...
                }
            }
        }

        if(($pos = strrpos($s,'\\')) !== false) {
            if ('\\' !== $type->getNamespace()) {
                if(trim($type->getNamespace().'\\'.$d,
                    '\\') == $s
                ) {
                    return true;
                }
            } else {
                if(substr($s, $pos+1) === $d) {
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
    public function __toString() {
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
        static $repmaps = [
            ['integer', 'double', 'boolean', 'false',
            'true', 'callback', 'closure', 'NULL'],
            ['int', 'float', 'bool', 'bool', 'bool',
            'callable', 'callable', 'null']
        ];

        if (empty($name)) {
            return $name;
        }

        return str_replace(
            $repmaps[0], $repmaps[1], strtolower($name)
        );
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
