<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Element, Method};
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Persistent\Column;
use \Phan\Persistent\ListAssociation;
use \Phan\Persistent\Model;
use \Phan\Persistent\ModelOne;
use \Phan\Persistent\ModelStringListMap;
use \Phan\Persistent\Schema;

/**
 * Information pertaining to PHP code files that we've read
 */
class File extends ModelOne {

    /**
     * @var string
     * The path to the file we're holding information about
     */
    private $file_path;

    /**
     * @var int
     * The last known modification date of the file
     */
    private $modification_time = 0;

    /**
     * @var int
     * The last known modification date of the file when it
     * was last analyzed.
     */
    private $analysis_time = 0;

    /**
     * @var FQSEN[]
     * A list of class FQSENs associated with this file
     */
    private $class_fqsen_list = [];

    /**
     * @var FQSEN[]
     * A list of method FQSENs associated with this file
     */
    private $method_fqsen_list = [];

    /**
     * @param string $file_path
     * The path to the file we're tracking
     */
    public function __construct(
        string $file_path,
        int $modification_time = 0,
        int $analysis_time = 0
    ) {
        $this->file_path = $file_path;
        $this->modification_time = $modification_time;
        $this->analysis_time = $analysis_time;
    }

    /**
     * @return bool
     * True if the given file is up to date within this
     * code base, else false
     */
    public function isParseUpToDate() : bool {
        return (
            filemtime($this->file_path) <= $this->modification_time
        );
    }

    /**
     * Mark the file at the given path as up to date so
     * that we know if its changed on subsequent runs
     *
     * @return null
     */
    public function setParseUpToDate() {
        $this->modification_time = filemtime($this->file_path);
    }

    /**
     * @return bool
     * True if the given file is up to date within this
     * code base, else false
     */
    public function isAnalysisUpToDate() : bool {
        return (
            filemtime($this->file_path) <= $this->analysis_time
        );
    }

    /**
     * Mark the file at the given path as up to date so
     * that we know if its changed on subsequent runs
     *
     * @return null
     */
    public function setAnalysisUpToDate() {
        $this->analysis_time = filemtime($this->file_path);
    }

    /**
     * @return FQSEN[]
     * A list of class FQSENs associated with this file
     */
    public function getClassFQSENList() : array {
        return $this->class_fqsen_list;
    }

    /**
     * @param FQSEN[] $class_fqsen_list
     * The set of class FQSENs associated with this file
     *
     * @return null
     */
    private function setClassFQSENList(array $class_fqsen_list) {
        $this->class_fqsen_list = $class_fqsen_list;
    }

    /**
     * @param FQSEN $fqsen
     * A class FQSEN associated with this file
     *
     * @return null
     */
    public function addClassFQSEN(FQSEN $fqsen) {
        $this->class_fqsen_list[] = $fqsen;
    }

    /**
     * @return FQSEN[]
     * A list of method FQSENs associated with this file
     */
    public function getMethodFQSENList() : array {
        return $this->method_fqsen_list;
    }

    /**
     * @param FQSEN[] $method_fqsen_list
     * The set of method FQSENs associated with this file
     *
     * @return null
     */
    private function setMethodFQSENList(array $method_fqsen_list) {
        $this->method_fqsen_list = $method_fqsen_list;
    }

    /**
     * @param FQSEN $fqsen
     * A method FQSEN associated with this file
     *
     * @return null
     */
    public function addMethodFQSEN(FQSEN $fqsen) {
        $this->method_fqsen_list[] = $fqsen;
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema('File', [
            new Column('file_path', 'STRING', true),
            new Column('modification_time', 'INTEGER'),
            new Column('analysis_time', 'INTEGER'),
        ]);

        $schema->addAssociation(new ListAssociation(
            'File_class_fqsen_list', 'STRING',
            function (File $file, array $fqsen_list) {
                $file->setClassFQSENList(
                    array_map(function (string $fqsen_string) {
                        return FullyQualifiedClassName
                            ::fromFullyQualifiedString($fqsen_string);
                    }, $fqsen_list)
                );
            },
            function (File $file) {
                return $file->getClassFQSENList();
            }
        ));

        $schema->addAssociation(new ListAssociation(
            'File_method_fqsen_list', 'STRING',
            function (File $file, array $fqsen_list) {
                $file->setMethodFQSENList(
                    array_map(function (string $fqsen_string) {
                        return FullyQualifiedMethodName
                            ::fromFullyQualifiedString($fqsen_string);
                    }, $fqsen_list)
                );
            },
            function (File $file) {
                return $file->getMethodFQSENList();
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
            'file_path' => $this->file_path,
            'modification_time' => $this->modification_time,
            'analysis_time' => $this->analysis_time,
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return File
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : File {
        return new File(
            $row['file_path'],
            $row['modification_time'],
            $row['analysis_time']
        );
    }

    /**
     * @return string
     * The primary key of this model
     */
    public function primaryKeyValue() : string {
        return $this->file_path;
    }
}
