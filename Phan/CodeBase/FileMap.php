<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Database;
use \Phan\Exception\NotFoundException;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Model\File as FileModel;

/**
 * Information pertaining to PHP code files that we've read
 */
trait FileMap {

    /**
     * Implementing class must have a method for removing
     * all details of methods with the given scope and
     * name
     *
     * @return null
     */
    abstract protected function flushMethodWithScopeAndName(
        string $scope,
        string $name
    );

    /**
     * Implementing class must have a method for removing
     * all details of methods with the given scope and
     * name
     *
     * @return null
     */
    abstract protected function flushClassWithFQSEN(
        FullyQualifiedClassName $fqsen
    );

    /**
     * @var File[]
     * A map from file name to info such as its last
     * modification date used to determine if a file
     * needs to be re-parsed
     */
    private $file_map = [];

    /**
     * Remove any objects we have associated with the
     * given file so that we can re-read it
     *
     * @return null
     */
    public function flushDependenciesForFile(string $file_path) {
        $code_file = $this->getFileByPath($file_path);

        // Flush all classes from the file
        foreach ($code_file->getClassFQSENList() as $fqsen) {
            unset($this->class_map[(string)$fqsen]);
        }

        // Flush all methods from the file
        foreach ($code_file->getMethodFQSENList() as $fqsen) {
            // Remove it from memory
            $this->flushMethodWithScopeAndName(
                (string)$fqsen->getFullyQualifiedClassName(),
                $fqsen->getName()
            );

            // Remove it from the file's depdendency list
            $code_file->flushMethodWithFQSEN($fqsen);

            // TODO: remove it from the database?
        }

        // TODO: Flush global constants
    }

    /**
     * @return bool
     * True if the given file is up to date within this
     * code base, else false
     */
    public function isParseUpToDateForFile(string $file_path) : bool {
        return $this->getFileByPath($file_path)
            ->isParseUpToDate();
    }

    /**
     * Mark the file at the given path as up to date so
     * that we know if its changed on subsequent runs
     *
     * @return null
     */
    public function setParseUpToDateForFile(string $file_path) {
        return $this->getFileByPath($file_path)
            ->setParseUpToDate();
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
     * @param string $file_path
     * A path to a file name
     *
     * @return File
     * An object tracking state for the given $file_path
     */
    protected function getFileByPath(string $file_path) : File {
        if (empty($this->file_map[$file_path])) {

            if (Database::isEnabled()) {
                try {
                    $file_model =
                        FileModel::read(Database::get(), $file_path);

                    $file = $file_model->getFile();
                } catch (NotFoundException $exception) {
                    // Create the file
                    $file = new File($file_path);

                    // Write it to the database immediately
                    (new FileModel($file))->write(Database::get());
                }
            } else {
                $file = new File($file_path);
            }

            // Save it in memory
            $this->file_map[$file_path] = $file;
        }

        return $this->file_map[$file_path];
    }

}
