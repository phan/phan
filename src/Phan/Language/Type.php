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
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Library\Tuple4;
use ast\Node;

class Type
{
    use \Phan\Memoize;

    /**
     * @var string
     * A legal type identifier (e.g. 'int' or 'DateTime')
     */
    const simple_type_regex =
        '[a-zA-Z_\x7f-\xff\\\][a-zA-Z0-9_\x7f-\xff\\\]*';

    /**
     * @var string
     * A regex matching template parameter types such
     * as '<int,DateTime|null,string>'
     */
    const template_parameter_type_list_regex =
        '<'
        . '('
        . '(' . self::simple_type_regex . '(\[\])*' . ')'
        . '(' . '\s*,\s*'
        . '(' . self::simple_type_regex . '(\[\])*' . ')'
        . ')*'
        . ')'
        . '>';

    /**
     * @var string
     * A type with an optional template parameter list
     * such as 'Set<Datetime>', 'int' or 'Tuple2<int>'.
     */
    const simple_type_with_template_parameter_list_regex =
        '(' . self::simple_type_regex . ')'
        . '(' . self::template_parameter_type_list_regex . ')?';

    /**
     * @var string
     * A legal type identifier matching a type optionally with a []
     * indicating that its a generic typed array (e.g. 'int[]',
     * 'string' or 'Set<DateTime>')
     */
    const type_regex =
        self::simple_type_with_template_parameter_list_regex . '(\[\])*';

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
     * @var UnionType[]
     * A possibly empty list of concrete types that
     * act as parameters to this type if it is a templated
     * type.
     */
    protected $template_parameter_type_list = [];

    /**
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param UnionType[] $template_parameter_type_list
     * A (possibly empty) list of template parameter types
     */
    protected function __construct(
        string $namespace,
        string $name,
        $template_parameter_type_list
    ) {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->template_parameter_type_list = $template_parameter_type_list;
    }

    /**
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param UnionType[] $template_parameter_type_list
     * A (possibly empty) list of template parameter types
     *
     * @return Type
     * A single canonical instance of the given type.
     */
    protected static function make(
        string $namespace,
        string $type_name,
        $template_parameter_type_list
    ) : Type {

        $namespace = trim($namespace);

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
                substr($type_name, 0, $pos),
                $template_parameter_type_list
            ));
        }

        assert(
            $namespace && 0 === strpos($namespace, '\\'),
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
            !empty($type_name),
            "Type name cannot be empty"
        );

        assert(
            false === strpos(
                $type_name,
                '|'
            ),
            "Type name may not contain a pipe."
        );

        // Create a canonical representation of the
        // namespace and name
        $namespace = $namespace ?: '\\';

        if ('\\' === $namespace) {
            $type_name = self::canonicalNameFromName($type_name);
        }

        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_map = [];

        $key = $namespace . '\\' . $type_name;

        if ($template_parameter_type_list) {
            $key .= '<' . implode(',', array_map(function (UnionType $union_type) {
                return (string)$union_type;
            }, $template_parameter_type_list)) . '>';
        }

        $key = strtolower($key);

        if (empty($canonical_object_map[$key])) {
            $canonical_object_map[$key] =
                new static($namespace, $type_name, $template_parameter_type_list);
        }

        return $canonical_object_map[$key];
    }

    /**
     * @param Type $type
     * The base type of this generic type referencing a
     * generic class
     *
     * @param UnionType[] $template_parameter_type_list
     * A map from a template type identifier to a
     * concrete union type
     */
    public static function fromType(
        Type $type,
        $template_parameter_type_list
    ) : Type {
        return self::make(
            $type->getNamespace(),
            $type->getName(),
            $template_parameter_type_list
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
     * @param string $type_name
     * The name of the internal type such as 'int'
     *
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
     * @param string $namespace
     * A fully qualified namespace
     *
     * @param string $type_name
     * The name of the type
     *
     * @return Type
     * A type representing the given namespace and type
     * name.
     */
    public static function fromNamespaceAndName(
        string $namespace,
        string $type_name
    ) : Type {
        return self::make($namespace, $type_name, []);
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

        $tuple = self::typeStringComponents($fully_qualified_string);

        $namespace = $tuple->_0;
        $relative_namespace = $tuple->_1;
        $type_name = $tuple->_2;
        $template_parameter_type_name_list = $tuple->_3;

        // Map the names of the types to actual types in the
        // template parameter type list
        $template_parameter_type_list = array_map(function (string $type_name) {
            return Type::fromFullyQualifiedString($type_name)->asUnionType();
        }, $template_parameter_type_name_list);

        if (0 !== strpos($namespace, '\\')) {
            $namespace = '\\' . $namespace;
        }

        assert(
            !empty($namespace) && !empty($type_name),
            "Type was not fully qualified"
        );

        return self::make(
            $namespace,
            $type_name,
            $template_parameter_type_list
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

        // Extract the namespace, type and parameter type name list
        $tuple = self::typeStringComponents($string);

        $namespace = $tuple->_0;
        $relative_namespace = $tuple->_1;
        $type_name = $tuple->_2;
        $template_parameter_type_name_list = $tuple->_3;

        // Map the names of the types to actual types in the
        // template parameter type list
        $template_parameter_type_list =
            array_map(function (string $type_name) use ($context) {
                return Type::fromStringInContext($type_name, $context)->asUnionType();
            }, $template_parameter_type_name_list);


        if ($relative_namespace
            && $context->hasNamespaceMapFor(
                T_CLASS,
                $relative_namespace
            )
        ) {
            $fqsen = $context->getNamespaceMapFor(
                T_CLASS,
                $relative_namespace
            );

            $namespace = implode('\\', array_merge(
                array_filter(explode('\\', (string)$fqsen)),
                array_slice(array_filter(explode('\\', $namespace)), 1)
            ));

            // Force it to be fully qualified
            if ($namespace[0] != '\\') {
                $namespace = '\\' . $namespace;
            }
        }

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
        $non_generic_partially_qualified_array_type_name =
            $non_generic_array_type_name;
        if ($namespace) {
            $non_generic_partially_qualified_array_type_name =
                $namespace . '\\' . $non_generic_partially_qualified_array_type_name;
        }
        if ($context->hasNamespaceMapFor(
            T_CLASS,
            $non_generic_partially_qualified_array_type_name
        )) {
            $fqsen =
                $context->getNamespaceMapFor(
                    T_CLASS,
                    $non_generic_partially_qualified_array_type_name
                );

            if ($is_generic_array_type) {
                return GenericArrayType::fromElementType(Type::make(
                    $fqsen->getNamespace(),
                    $fqsen->getName(),
                    $template_parameter_type_list
                ));
            }

            return Type::make(
                $fqsen->getNamespace(),
                $fqsen->getName(),
                $template_parameter_type_list
            );
        }

        else {
            // print "Not found for $non_generic_array_type_name from $string with $namespace\n";
        }

        // If this was a fully qualified type, we're all
        // set
        if (!empty($namespace) && 0 === strpos($namespace, '\\')) {
            return self::make(
                $namespace,
                $type_name,
                $template_parameter_type_list
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

        // Merge the current namespace with the given relative
        // namespace
        if (!empty($context->getNamespace()) && !empty($namespace)) {
            $namespace = $context->getNamespace() . '\\' . $namespace;
        } else if (!empty($context->getNamespace())) {
            $namespace = $context->getNamespace();
        } else {
            $namespace = '\\' . $namespace;
        }

        // Attach the context's namespace to the type name
        return self::make($namespace, $type_name, $template_parameter_type_list);
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
        return false;
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
            Type::make('\\', 'ArrayAccess', []);

        return (
            $this === ArrayType::instance()
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
        return self::isGenericArrayString($this->getName());
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

        if (($pos = strrpos($this->getName(), '[]')) !== false) {
            assert(
                $this->getName() !== '[]' && $this->getName() !== 'array',
                "Non-generic type requested to be non-generic"
            );

            return Type::make(
                $this->getNamespace(),
                substr($this->getName(), 0, $pos),
                $this->template_parameter_type_list
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
        if ($this->getName() == 'array'
            || $this->getName() == 'mixed'
            || strpos($this->getName(), '[]') !== false
        ) {
            return ArrayType::instance();
        }

        return GenericArrayType::fromElementType($this);
    }

    /**
     * @return bool
     * True if this type has any template parameter types
     */
    public function hasTemplateParameterTypes() : bool
    {
        return !empty($this->template_parameter_type_list);
    }

    /**
     * @return UnionType[]
     * The set of types filling in template parameter types defined
     * on the class specified by this type.
     */
    public function getTemplateParameterTypeList()
    {
        return $this->template_parameter_type_list;
    }

    /**
     * @param CodeBase $code_base
     * The code base to look up classes against
     *
     * @return Type[]
     * A map from template type identifier to a concrete type
     */
    public function getTemplateParameterTypeMap(CodeBase $code_base) {
        return $this->memoize(__METHOD__, function () use ($code_base) {
            $fqsen = $this->asFQSEN();

            if (!($fqsen instanceof FullyQualifiedClassName)) {
                return [];
            }

            assert($fqsen instanceof FullyQualifiedClassName);

            if (!$code_base->hasClassWithFQSEN($fqsen)) {
                return [];
            }

            $class = $code_base->getClassByFQSEN($fqsen);

            $class_template_type_list = $class->getTemplateTypeMap();

            $template_parameter_type_list =
                $this->getTemplateParameterTypeList();

            $map = [];
            foreach (array_keys($class->getTemplateTypeMap()) as $i => $identifier) {
                if (isset($template_parameter_type_list[$i])) {
                    $map[$identifier] = $template_parameter_type_list[$i];
                }
            }

            return $map;
        });
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

            assert($class_fqsen instanceof FullyQualifiedClassName);

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
        $fqsen = $this->asFQSEN();
        assert($fqsen instanceof FullyQualifiedClassName);

        $this_clazz = $code_base->getClassByFQSEN(
            $fqsen
        );

        $parent_fqsen = $parent->asFQSEN();
        assert($parent_fqsen instanceof FullyQualifiedClassName);

        $parent_clazz = $code_base->getClassByFQSEN(
            $parent_fqsen
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
     * A string representation of this type in FQSEN form.
     */
    public function asFQSENString() : string
    {
        return $this->memoize(__METHOD__, function() {
            if (!$this->hasNamespace()) {
                return $this->getName();
            }

            if ('\\' === $this->getNamespace()) {
                return '\\' . $this->getName();
            }

            return "{$this->getNamespace()}\\{$this->getName()}";
        });
    }

    /**
     * @return string
     * A human readable representation of this type
     */
    public function __toString()
    {
        $string = $this->asFQSENString();

        $template_parameter_string =
            implode(',', array_map(function (UnionType $type) {
                return (string)$type;
            }, $this->template_parameter_type_list));

        if (!empty($template_parameter_string)) {
            $string .= '<' . $template_parameter_string . '>';
        }

        return $string;
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
     * @param string $type_string
     * Any type string such as 'int' or 'Set<int>'
     *
     * @return Tuple4<string,string|null,string,array>
     * A pair with the 0th element being the namespace and the first
     * element being the type name.
     */
    private static function typeStringComponents(
        string $type_string
    ) {
        // Check to see if we have template parameter types
        $template_parameter_type_name_list = [];

        $match = [];
        if (preg_match('/' . self::type_regex. '/', $type_string, $match)) {
            $type_string = $match[1];

            // If we have a generic array symbol '[]', append that back
            // on to the type string
            if (isset($match[7])) {

                // Figure out the dimensionality of the type array
                $gmatch = [];
                if (preg_match('/\[[\]\[]*\]/', $match[0], $gmatch)) {
                    $type_string .= $gmatch[0];
                }
            }

            $template_parameter_type_name_list = !empty($match[3])
                ?  preg_split('/\s*,\s*/', $match[3])
                : [];
        }

        // Determine if the type name is fully qualified
        // (as specified by a leading backslash).
        $is_fully_qualified = (0 === strpos($type_string, '\\'));

        $fq_class_name_elements =
            array_filter(explode('\\', $type_string));

        $class_name =
            (string)array_pop($fq_class_name_elements);

        $namespace = ($is_fully_qualified ? '\\' : '')
            . implode('\\', array_filter(
                $fq_class_name_elements
            ));

        // Get the 0th element of the namespace if it exists
        // so we can look it up against any using clauses that
        // define a relative namespace.
        $relative_namespace = null;
        if (isset($fq_class_name_elements[0])) {
            $relative_namespace = $fq_class_name_elements[0];
        }

        return new Tuple4(
            $namespace,
            $relative_namespace,
            $class_name,
            $template_parameter_type_name_list
        );
    }
}
