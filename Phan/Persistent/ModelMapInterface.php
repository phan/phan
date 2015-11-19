<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
interface ModelMapInterface {

    /**
     * @return ModelOne[]
     * A map from string to a model for which we create
     * a mapping from this schema to the models provided
     * by this method
     */
    abstract public function modelOneMap() : array;
}
