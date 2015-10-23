<?php
declare(strict_types=1);
namespace phan\language;

/**
 * Static data defining type names for builtin classes
 */
$BUILTIN_CLASS_TYPES =
    require(__DIR__.'/type/BuiltinClassTypes.php');

/**
 * Static data defining types for builtin functions
 */
$BUILTIN_FUNCTION_ARGUMENT_TYPES =
    require(__DIR__.'/type/BuiltinFunctionArgumentTypes.php');

class Type {

    /**
     * @var string[]
     * A list of type names
     */
    private $type_name_list = [];

    /**
     * @param string[] $type_name_list
     * A list of type names
     */
    public function __construct(array $type_name_list) {
        $this->type_name_list = array_map(function(string $type_name) {
            return $this->toCanonicalName($type_name);
        }, $type_name_list;
    }

    public function __toString() : string {
        return implode('|', $this->type_name_list);
    }

    public static function typeForBuiltinClassProperty(
        string $class_name,
        string $property_name
    ) : Type {

        $class_property_type_map =
            $BUILTIN_CLASS_TYPES[strtolower($class_name)]['properties'];

        $property_type_name =
            $class_property_type_map[$property_name];


    }


    /**
     * @return Type[]
     * A list of types for parameters associated with the
     * given builtin function with the given name
     */
    public static function typeListForParametersForBuiltinFunctionWithName(
        string $function_name
    ) : array {

        $type_name_list =
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$function_name];

        return array_map(function(string $type_name) {
            return new Type($type_name);
        }, $type_name_list);
    }

    /**
     * @param string $type_name
     * Any type name
     *
     * @return string
     * A canonical name for the given type name
     */
    private static function toCanonicalName(string $type_name) : string {
        static $repmaps = [
            [
                'integer',
                'double',
                'boolean',
                'false',
                'true',
                'callback',
                'closure',
                'NULL'
            ],
            [
                'int',
                'float',
                'bool',
                'bool',
                'bool',
                'callable',
                'callable',
                'null'
            ]
        ];

        return str_replace(
            $repmaps[0],
            $repmaps[1],
            $type
        );
    }
}
