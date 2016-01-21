<?php declare(strict_types=1);
namespace Phan\Database;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
interface ModelOneInterface
{

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    abstract public function toRow() : array;

    /**
     * @return string|array
     * The value of the primary key for this model
     */
    abstract public function primaryKeyValue();
}
