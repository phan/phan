<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Database;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Element, Method};
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Model\File as FileModel;

/**
 * Information pertaining to PHP code files that we've read
 */
class File {

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
        int $modification_time = 0
    ) {
        $this->file_path = $file_path;
        $this->modification_time = $modification_time;
    }

    /**
     * @return string
     * The file path
     */
    public function getFilePath() : string {
        return $this->file_path;
    }

    /**
     * @return int
     * The time of the last known modification of this
     * file
     */
    public function getModificationTime() : int {
        return $this->modification_time;
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

        if (Database::isEnabled()) {
            // Write it to disk
            (new FileModel($this))->write(Database::get());
        }
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
    public function setClassFQSENList(array $class_fqsen_list) {
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
    public function setMethodFQSENList(array $method_fqsen_list) {
        $this->method_fqsen_list = $method_fqsen_list;
    }

    /**
     * @param FQSEN $fqsen
     * A method FQSEN associated with this file
     *
     * @return null
     */
    public function addMethodFQSEN(FQSEN $fqsen) {
        $this->method_fqsen_list[(string)$fqsen] = $fqsen;
    }

    /**
     * Remove the method with the given FQSEN from our
     * list of associated methods
     */
    public function flushMethodWithFQSEN(FQSEN $fqsen) {
        unset($this->method_fqsen_list[(string)$fqsen]);
    }

    /**
     * Remove the class with the given FQSEN from our
     * list of associated classes
     */
    public function flushClassWithFQSEN(FullyQualifiedClassName $fqsen) {
        unset($this->class_fqsen_list[(string)$fqsen]);
    }

}
