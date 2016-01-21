<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Config;
use \Phan\Database;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz,
    Element,
    Method
};
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\FQSEN\FullyQualifiedPropertyName;
use \Phan\Model\File as FileModel;

/**
 * Information pertaining to PHP code files that we've read
 */
class File
{

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
     * @var FullyQualifiedClassName[]
     * A list of class FQSENs associated with this file
     */
    private $class_fqsen_list = [];

    /**
     * @var FullyQualifiedMethodName[]
     * A list of method FQSENs associated with this file
     */
    private $method_fqsen_list = [];

    /**
     * @var FullyQualifiedPropertyName[]
     * A list of property FQSENs associated with this file
     */
    private $property_fqsen_list = [];

    /**
     * @var FullyQualifiedConstantName[]
     * A list of constant FQSENs associated with this file
     */
    private $constant_fqsen_list = [];

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
    public function getFilePath() : string
    {
        return $this->file_path;
    }

    /**
     * @return string
     * The path of the file relative to the project
     * root directory
     */
    public function getProjectRelativePath() : string
    {
        return self::projectRelativePathFromCWDRelativePath(
            $this->file_path
        );
    }

    /**
     * @param string $cwd_relative_path
     * A path relative to the current working directory of
     * the phan executable.
     *
     * @return string
     * A path relative to the project root directory. For example
     * if cwd is 'src/Phan/' and phan is executed as
     * `phan -d ../../ Debug.php`, and we wanted to get the
     * project relative path for 'Debug.php', you'd get
     * 'src/Phan/Debug.php'.
     */
    public static function projectRelativePathFromCWDRelativePath(
        string $cwd_relative_path
    ) : string {

        // Get a path relative to the project root
        $path = str_replace(
            Config::get()->getProjectRootDirectory(),
            '',
            realpath($cwd_relative_path) ?: $cwd_relative_path
        );

        // Strip any beginning directory separators
        if (0 === ($pos = strpos($path, DIRECTORY_SEPARATOR))) {
            $path = substr($path, $pos + 1);
        }

        return $path;
    }

    /**
     * @return int
     * The time of the last known modification of this
     * file
     */
    public function getModificationTime() : int
    {
        return $this->modification_time;
    }

    /**
     * @return bool
     * True if the given file is up to date within this
     * code base, else false
     */
    public function isParseUpToDate() : bool
    {
        $real_path = realpath($this->file_path);

        // If the file no longer exists, its probably
        // not up to date
        if (false === $real_path) {
            return false;
        }

        return (
            filemtime($real_path) <= $this->modification_time
        );
    }

    /**
     * Mark the file at the given path as up to date so
     * that we know if its changed on subsequent runs
     *
     * @return null
     */
    public function setParseUpToDate()
    {
        $this->modification_time = filemtime(realpath($this->file_path));

        if (Database::isEnabled()) {
            // Write it to disk
            (new FileModel($this))->write(Database::get());
        }
    }

    /**
     * @return FQSEN[]
     * A list of class FQSENs associated with this file
     */
    public function getClassFQSENList() : array
    {
        return $this->class_fqsen_list;
    }

    /**
     * @param FQSEN[] $class_fqsen_list
     * The set of class FQSENs associated with this file
     *
     * @return null
     */
    public function setClassFQSENList(array $class_fqsen_list)
    {
        $this->class_fqsen_list = $class_fqsen_list;
    }

    /**
     * @param FQSEN $fqsen
     * A class FQSEN associated with this file
     *
     * @return null
     */
    public function addClassFQSEN(FQSEN $fqsen)
    {
        $this->class_fqsen_list[] = $fqsen;
    }

    /**
     * Remove the class with the given FQSEN from our
     * list of associated classes
     */
    public function flushClassWithFQSEN(FullyQualifiedClassName $fqsen)
    {
        unset($this->class_fqsen_list[(string)$fqsen]);
    }

    /**
     * @return FullyQualifiedMethodName[]|FullyQualifiedFunctionName[]
     * A list of method FQSENs associated with this file
     */
    public function getMethodFQSENList() : array
    {
        return $this->method_fqsen_list;
    }

    /**
     * @param FQSEN[] $method_fqsen_list
     * The set of method FQSENs associated with this file
     *
     * @return null
     */
    public function setMethodFQSENList(array $method_fqsen_list)
    {
        $this->method_fqsen_list = $method_fqsen_list;
    }

    /**
     * @param FQSEN $fqsen
     * A method FQSEN associated with this file
     *
     * @return null
     */
    public function addMethodFQSEN(FQSEN $fqsen)
    {
        $this->method_fqsen_list[(string)$fqsen] = $fqsen;
    }

    /**
     * Remove the method with the given FQSEN from our
     * list of associated methods
     */
    public function flushMethodWithFQSEN(FQSEN $fqsen)
    {
        unset($this->method_fqsen_list[(string)$fqsen]);
    }

    /**
     * @return FullyQualifiedPropertyName[]
     * A list of property FQSENs associated with this file
     */
    public function getPropertyFQSENList() : array
    {
        return $this->property_fqsen_list;
    }

    /**
     * @param FullyQualifiedPropertyName[] $property_fqsen_list
     * The set of property FQSENs associated with this file
     *
     * @return null
     */
    public function setPropertyFQSENList(array $property_fqsen_list)
    {
        $this->property_fqsen_list = $property_fqsen_list;
    }

    /**
     * @param FullyQualifiedPropertyName $fqsen
     * A property FQSEN associated with this file
     *
     * @return null
     */
    public function addPropertyFQSEN(FullyQualifiedPropertyName $fqsen)
    {
        $this->property_fqsen_list[(string)$fqsen] = $fqsen;
    }

    /**
     * Remove the property with the given FQSEN from our
     * list of associated properties
     */
    public function flushPropertyWithFQSEN(FullyQualifiedPropertyName $fqsen)
    {
        unset($this->property_fqsen_list[(string)$fqsen]);
    }

    /**
     * @return FullyQualifiedConstantName[]
     * A list of constant FQSENs associated with this file
     */
    public function getConstantFQSENList() : array
    {
        return $this->constant_fqsen_list;
    }

    /**
     * @param FullyQualifiedConstantName[] $constant_fqsen_list
     * The set of constant FQSENs associated with this file
     *
     * @return null
     */
    public function setConstantFQSENList(array $constant_fqsen_list)
    {
        $this->constant_fqsen_list = $constant_fqsen_list;
    }

    /**
     * @param FullyQualifiedConstantName $fqsen
     * A constant FQSEN associated with this file
     *
     * @return null
     *
     * @suppress PhanUnreferencedMethod
     */
    public function addConstantFQSEN(FullyQualifiedConstantName $fqsen)
    {
        $this->constant_fqsen_list[(string)$fqsen] = $fqsen;
    }

    /**
     * Remove the constant with the given FQSEN from our
     * list of associated constants
     */
    public function flushConstantWithFQSEN(FQSEN $fqsen)
    {
        unset($this->constant_fqsen_list[(string)$fqsen]);
    }

    public function __toString() : string
    {
        return $this->file_path;
    }
}
