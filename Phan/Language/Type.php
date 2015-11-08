<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Type\NodeTypeKindVisitor;
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
        $this->name = $name;
        $this->namespace = $namespace;
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
    private static function toCanonicalName(
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


}
