<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;

/**
 * Information pertaining to PHP code files that we've read
 */
trait FileMap {

    /**
     * @var File[]
     * A map from file name to info such as its last
     * modification date used to determine if a file
     * needs to be re-parsed
     */
    private $file_map = [];

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
            $this->file_map[$file_path] = new File($file_path);
        }

        return $this->file_map[$file_path];
    }


}
