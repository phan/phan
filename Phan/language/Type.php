<?php
declare(strict_types=1);
namespace phan\language;

require_once(__DIR__.'/FQSEN.php');

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
        }, $type_name_list);
    }

    public function __toString() : string {
        return implode('|', $this->type_name_list);
    }

    /**
     * Get a Type specifying that there are no
     * known types on a thing.
     */
    public static function none() : Type {
        return new Type([]);
    }

    /**
     * @return Type
     * A Type for the given object
     */
    public static function typeForObject($object) {
        return new Type([gettype($object)]);
    }

    public static function builtinClassPropertyType(
        string $class_name,
        string $property_name
    ) : Type {
        $class_property_type_map =
            $BUILTIN_CLASS_TYPES[strtolower($class_name)]['properties'];

        $property_type_name =
            $class_property_type_map[$property_name];

        return new Type($property_type_name);
    }

    /**
     * @return Type[]
     * A list of types for parameters associated with the
     * given builtin function with the given name
     */
    public static function builtinFunctionPropertyNameTypeMap(
        FQSEN $function_fqsen
    ) : array {
        $type_name_struct =
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$function_fqsen->__toString()];

        if (!$type_name_struct) {
            return [];
        }

        $type_return = array_shift($type_name_struct);
        $name_type_name_map = $type_name_struct;

        $property_name_type_map = [];

        foreach ($name_type_name_map as $name => $type_name) {
            $property_name_type_map[$name] =
                new Type($type_name);
        }

        return $property_name_type_map;
    }

    /**
     * @return bool
     * True if a builtin with the given FQSEN exists, else
     * flase.
     */
    public static function builtinExists(FQSEN $fqsen) : bool {
        return !empty(
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$fqsen->__toString()]
        );
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
            $type_name
        );
    }
}
