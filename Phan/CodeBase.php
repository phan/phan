<?php
declare(strict_types=1);
namespace Phan;

use \Phan\CodeBase\File;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Element, Method};
use \Phan\Language\FQSEN;
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

    /**
     * Set a version on this class so that we can
     * error out when reading old versions of serialized
     * files
     */
    const CODE_BASE_VERSION = 2;
    private $code_base_version;

    /**
     * @var File[]
     * A map from file name to info such as its last
     * modification date used to determine if a file
     * needs to be re-parsed
     */
    private $file_map = [];

    /**
     * @var Class[]
     * A map from fqsen string to the class it
     * represents
     */
    private $class_map = [];

    /**
     * @var Method[]
     * A map from fqsen string to the method it
     * represents
     */
    private $method_map = [];

    /**
     * @var int[]
     * Summary information about the code base
     */
    private $summary = [];

    public function __construct(
        array $internal_class_name_list,
        array $internal_interface_name_list,
        array $internal_trait_name_list,
        array $internal_function_name_list
    ) {
        $this->resetSummary();
        $this->addClassesByNames($internal_class_name_list);
        $this->addClassesByNames($internal_interface_name_list);
        $this->addClassesByNames($internal_trait_name_list);
        $this->addFunctionsByNames($internal_function_name_list);
        $this->resetSummary();

        // Set a version on this class so that we can
        // error out when reading old versions of serialized
        // files
        $this->code_base_version =
            CodeBase::CODE_BASE_VERSION;
    }

    /**
     * @return File[]
     * A map from file path to File
     */
    protected function getFileMap() : array {
        return $this->file_map;
    }

    /**
     * @param File[] $file_map
     * A map from file path to File
     *
     * @return null
     */
    protected function setFileMap(array $file_map) {
        $this->file_map = $file_map;
    }

    /**
     * Reset summary statistics
     *
     * @return null
     */
    private function resetSummary() {
        $this->summary = [
            'conditionals' => 0,
            'classes'      => 0,
            'traits'       => 0,
            'methods'      => 0,
            'functions'    => 0,
            'closures'     => 0,
        ];

    }

    /**
     * Add a class to the code base
     *
     * @return null
     */
    public function addClass(Clazz $class) {
        $this->class_map[(string)$class->getFQSEN()]
            = $class;

        if (!$class->getContext()->isInternal()) {
            $this->file_map[$class->getContext()->getFile()]
                ->addClassFQSEN($class->getFQSEN());
        }

        $this->incrementClasses();
    }

    /**
     * Get a map from FQSEN strings to the class it
     * represents for all known classes.
     *
     * @return Clazz[]
     * A map from FQSEN string to Clazz
     */
    public function getClassMap() : array {
        return $this->class_map;
    }

    /**
     * @return Clazz
     * A class with the given FQSEN
     */
    public function getClassByFQSEN(FQSEN $fqsen) : Clazz {
        assert(isset($this->class_map[(string)$fqsen]),
            "Class with fqsen $fqsen not found");

        return $this->class_map[(string)$fqsen];
    }

    /**
     * @return bool
     * True if the exlass exists else false
     */
    public function hasClassWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->class_map[$fqsen->__toString()]);
    }

    /**
     * Get a map from FQSEN strings to the method it
     * represents for all known methods.
     */
    public function getMethodMap() : array {
        return $this->method_map;
    }

    /**
     * @param Method $method
     * A method to add to the code base
     */
    private function addMethodCommon(Method $method) {
        $this->method_map[(string)$method->getFQSEN()] = $method;

        if (!$method->getContext()->isInternal()) {
            $this->file_map[$method->getContext()->getFile()]
                ->addMethodFQSEN($method->getFQSEN());
        }
    }

    /**
     * @param Method $method
     * A method to add to the code base
     */
    public function addMethod(Method $method) {
        $this->addMethodCommon($method);
        $this->incrementMethods();
    }

    /**
     * @param Method $method
     * A method to add to the code base
     */
    public function addFunction(Method $method) {
        $this->addMethodCommon($method);
        $this->incrementFunctions();
    }


    /**
     * @param Method $method
     * A method to add to the code base
     */
    public function addClosure(Method $method) {
        $this->addMethodCommon($method);
        $this->incrementClosures();
    }

    /**
     * @return bool
     * True if a method exists with the given FQSEN
     */
    public function hasMethodWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->method_map[(string)$fqsen]);
    }

    /**
     * @return Method
     * Get the method with the given FQSEN
     */
    public function getMethodByFQSEN(FQSEN $fqsen) : Method {
        return $this->method_map[(string)$fqsen];
    }

    /**
     * @param string[] $class_name_list
     * A list of class names to load type information for
     *
     * @return null
     */
    private function addClassesByNames(array $class_name_list) {
        foreach ($class_name_list as $i => $class_name) {
            $clazz = Clazz::fromClassName($this, $class_name);
            $this->class_map[(string)$clazz->getFQSEN()] = $clazz;
        }
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

    public function incrementConditionals() {
        $this->summary['conditionals']++;
    }

    public function incrementClasses() {
        $this->summary['classes']++;
    }

    public function incrementTraits() {
        $this->summary['traits']++;
    }

    public function incrementMethods() {
        $this->summary['methods']++;
    }

    public function incrementFunctions() {
        $this->summary['functions']++;
    }

    public function incrementClosures() {
        $this->summary['closures']++;
    }

    /**
     * @param string $file_path
     * A path to a file name
     *
     * @return File
     * An object tracking state for the given $file_path
     */
    private function getFileForFile(string $file_path) : File {
        if (empty($this->file_map[$file_path])) {
            $this->file_map[$file_path] = new File($file_path);
        }

        return $this->file_map[$file_path];
    }

    /**
     * Remove any objects we have associated with the
     * given file so that we can re-read it
     *
     * @return null
     */
    public function flushDependenciesForFile(string $file_path) {
        $code_file = $this->getFileForFile($file_path);

        // Flush all classes from the file
        foreach ($code_file->getClassFQSENList() as $fqsen) {
            unset($this->class_map[(string)$fqsen]);
        }

        // Flush all methods from the file
        foreach ($code_file->getMethodFQSENList() as $fqsen) {
            unset($this->method_map[(string)$fqsen]);
        }
    }

    /**
     * @return bool
     * True if the given file is up to date within this
     * code base, else false
     */
    public function isParseUpToDateForFile(string $file_path) : bool {
        return $this->getFileForFile($file_path)
            ->isParseUpToDate();
    }

    /**
     * Mark the file at the given path as up to date so
     * that we know if its changed on subsequent runs
     *
     * @return null
     */
    public function setParseUpToDateForFile(string $file_path) {
        return $this->getFileForFile($file_path)
            ->setParseUpToDate();
    }

    /**
     * @return bool
     * True if the given file is up to date within this
     * code base, else false
     */
    public function isAnalysisUpToDateForFile(string $file_path) : bool {
        return $this->getFileForFile($file_path)
            ->isAnalysisUpToDate();
    }

    /**
     * Mark the file at the given path as up to date so
     * that we know if its changed on subsequent runs
     *
     * @return null
     */
    public function setAnalysisUpToDateForFile(string $file_path) {
        return $this->getFileForFile($file_path)
            ->setAnalysisUpToDate();
    }

    /**
     * @return int
     * The version number of this code base
     */
    public function getVersion() : int {
        return $this->code_base_version ?? -1;
    }

    /**
     * Store the given code base to the location defined in the
     * configuration (serialized_code_base_file).
     *
     * @return int|bool
     * This function returns the number of bytes that were written
     * to the file, or FALSE on failure.
     */
    public function store() {
        if (Config::get()->serialized_code_base_file) {
            $this->write(Database::get());
        }
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
     * @return CodeBase|bool
     * A stored code base if its successful or false if
     * unserialize fucks up
     */
    public static function fromStoredCodeBase() : CodeBase {
        if (!self::storedCodeBaseExists()) {
            throw new \Exception("No serialized_code_base_file defined");
        }

        return CodeBase::read(Database::get(), 1);
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema(
            'CodeBase', [ 'pk' => 'INT' ], [
                'version' => 'INTEGER'
            ]
        );

        $schema->addAssociation(new ModelAssociation(
            'CodeBase_File', '\Phan\CodeBase\File',
            function (CodeBase $code_base, array $file_map) {
                $code_base->setFileMap($file_map);
            },
            function (CodeBase $code_base) {
                return $code_base->getFileMap();
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
