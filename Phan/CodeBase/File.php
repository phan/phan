<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\Element\{Clazz, Element, Method};

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
    public function __construct(string $file_path) {
        $this->file_path = $file_path;
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
     * @param FQSEN $fqsen
     * A method FQSEN associated with this file
     *
     * @return null
     */
    public function addMethodFQSEN(FQSEN $fqsen) {
        $this->method_fqsen_list[] = $fqsen;
    }

}
