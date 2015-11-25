<?php
declare(strict_types=1);
namespace Phan;

use \Phan\CodeBase\File;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Element, Method};
use \Phan\Language\FQSEN;
use \Phan\Persistent\Column;
use \Phan\Persistent\Database;
use \Phan\Persistent\ModelAssociation;
use \Phan\Persistent\ModelOne;
use \Phan\Persistent\Schema;

/**
 * A CodeBase represents the known state of a code base
 * we're analyzing.
 *
 * In order to understand internal classes, interfaces,
 * traits and functions, a CodeBase needs to be
 * initialized with the list of those elements begotten
 * before any classes are loaded.
 *
 * # Example
 * ```
 * // Grab these before we define our own classes
 * $internal_class_name_list = get_declared_classes();
 * $internal_interface_name_list = get_declared_interfaces();
 * $internal_trait_name_list = get_declared_traits();
 * $internal_function_name_list = get_defined_functions()['internal'];
 *
 * // Load any required code ...
 *
 * $code_base = new CodeBase(
 *     $internal_class_name_list,
 *     $internal_interface_name_list,
 *     $internal_trait_name_list,
 *     $internal_function_name_list
 *  );
 *
 *  // Do stuff ...
 * ```
 */
class CodeBase extends ModelOne {
    use \Phan\CodeBase\ClassMap;
    use \Phan\CodeBase\MethodMap;
    use \Phan\CodeBase\ConstantMap;
    use \Phan\CodeBase\PropertyMap;
    use \Phan\CodeBase\GlobalVariableMap;
    use \Phan\CodeBase\FileMap;

    /**
     * Set a version on this class so that we can
     * error out when reading old versions of serialized
     * files
     */
    const CODE_BASE_VERSION = 2;
    private $code_base_version;

    public function __construct(
        array $internal_class_name_list,
        array $internal_interface_name_list,
        array $internal_trait_name_list,
        array $internal_function_name_list
    ) {
        $this->addClassesByNames($internal_class_name_list);
        $this->addClassesByNames($internal_interface_name_list);
        $this->addClassesByNames($internal_trait_name_list);
        $this->addFunctionsByNames($internal_function_name_list);

        // Set a version on this class so that we can
        // error out when reading old versions of serialized
        // files
        $this->code_base_version =
            CodeBase::CODE_BASE_VERSION;
    }

    /**
     * @param string[] $function_name_list
     * A list of function names to load type information for
     */
    private function addFunctionsByNames(array $function_name_list) {
        foreach ($function_name_list as $i => $function_name) {
            foreach (Method::methodListFromFunctionName($this, $function_name)
                as $method
            ) {
                $this->addMethod($method);
            }
        }
    }

    /**
     * @return int
     * The version number of this code base
     */
    public function getVersion() : int {
        return $this->code_base_version ?? -1;
    }

    /**
     * @return bool
     * True if a serialized code base exists and can be read
     * else false
     */
    public static function storedCodeBaseExists() : bool {
        return (
            Config::get()->serialized_code_base_file
            && file_exists(Config::get()->serialized_code_base_file)
        );
    }

    /**
     * Store the given code base to the location defined in the
     * configuration (serialized_code_base_file).
     *
     * @return int|bool
     * This function returns the number of bytes that were written
     * to the file, or FALSE on failure.
     */
    public function toDisk() {
        if (!Config::get()->serialized_code_base_file) {
            return;
        }

        $this->write(Database::get());
    }

    /**
     * @return CodeBase|bool
     * A stored code base if its successful or false if
     * unserialize fucks up
     */
    public static function fromDisk() : CodeBase {
        if (!self::storedCodeBaseExists()) {
            return;
        }

        return CodeBase::read(Database::get(), 1);
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema('CodeBase', [
            new Column('pk', 'INTEGER', true),
            new Column('version', 'INTEGER'),
        ]);

        // File map
        $schema->addAssociation(new ModelAssociation(
            'CodeBase_File', '\Phan\CodeBase\File',
            function (CodeBase $code_base, array $file_map) {
                $code_base->setFileMap($file_map);
            },
            function (CodeBase $code_base) {
                return $code_base->getFileMap();
            }
        ));

        // Class map
        $schema->addAssociation(new ModelAssociation(
            'CodeBase_Clazz', '\Phan\Language\Element\Clazz',
            function (CodeBase $code_base, array $map) {
                $code_base->setClassMap($map);
            },
            function (CodeBase $code_base) {
                return $code_base->getClassMap();
            }
        ));

        // Method map
        $schema->addAssociation(new ModelAssociation(
            'CodeBase_Method', '\Phan\Language\Element\Method',
            function (CodeBase $code_base, array $map) {
                $code_base->setMethodMap($map);
            },
            function (CodeBase $code_base) {
                return $code_base->getMethodMap();
            }
        ));

        return $schema;
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return [
            'pk' => 1,
            'version' => $this->getVersion()
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return CodeBase
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : CodeBase {
        return new CodeBase([], [], [], []);
    }

    /**
     * There is only one CodeBase per database
     */
    public function primaryKeyValue() : int {
        return 1;
    }
}
