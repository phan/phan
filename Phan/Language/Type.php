<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Type\NodeTypeKindVisitor;
use \Phan\Language\UnionType;
use \ast\Node;

/**
 * Static data defining type names for builtin classes
 */
$BUILTIN_CLASS_TYPES =
    require(__DIR__.'/Type/BuiltinClassTypes.php');

/**
 * Static data defining types for builtin functions
 */
$BUILTIN_FUNCTION_ARGUMENT_TYPES =
    require(__DIR__.'/Type/BuiltinFunctionArgumentTypes.php');

class Type {
    use \Phan\Language\AST;

    /**
     * @var string
     * The name of this type such as 'int' or 'MyClass'
     */
    protected $name = null;

    /**
     * @var string
     * The namespace of this type such as '\' or
     * '\Phan\Language'
     */
    protected $namespace = '\\';

    /**
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     */
    public function __construct(
        string $name,
        string $namespace = '\\'
    ) {
        assert(!empty($name), "Type name cannot be empty");

        $this->name = self::canonicalNameFromName($name);
        $this->namespace = $namespace;
    }

    /**
     * @return Type
     * Get a type for the given object
     */
    public static function fromObject($object) : Type {
        return Type::fromString(gettype($object));
    }

    /**
     * @return Type
     * Parse a type from the given string
     */
    public static function fromString(string $string) : Type {
        assert(!empty($string), "Type cannot be empty");

        $string = self::canonicalNameFromName($string);

        switch ($string) {
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

        // TODO: look for a namespace
        return new Type($string);
    }

    /**
     * @return UnionType
     * A UnionType representing this and only this type
     */
    public function asUnionType() : UnionType {
        return new UnionType([$this]);
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
        return in_array((string)$this, ['static', 'self', '$this']);
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
        return (strpos((string)$this, '[]') !== false);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToType(Type $type) : bool {

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
            && !$d_type->isScalar()
            && $d!=='array'
        ) {
            return true;
        }

        if($d === 'object' &&
            !$s_type->isScalar()
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
                if(trim(strtolower($this->getNamespace().'\\'.$s),
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
                if(trim(strtolower($type->getNamespace().'\\'.$d),
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
        if ('\\' == $this->namespace) {
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
            'true', 'callback', 'closure', 'NULL' ],
            ['int', 'float', 'bool', 'bool', 'bool',
            'callable', 'callable', 'null' ]
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
    private static function namespaceAndUnionTypeFromType(
        string $type_name
    ) : array {
        $fq_class_name_elements =
            array_filter(explode('\\', $type_name));

        $class_name =
            array_pop($fq_class_name_elements);

        $namespace =
            '\\' . implode('\\', $fq_class_name_elements);

        return [$namespace, $class_name];
    }
}
