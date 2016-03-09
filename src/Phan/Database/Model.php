<?php declare(strict_types=1);
namespace Phan\Database;

use Phan\Database;
use SQLite3;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class Model
{
    use \Phan\Memoize;

    /**
     * @return Schema
     * The schema for this model
     */
    abstract public static function createSchema() : Schema;

    /**
     * @return Schema
     * Get the schema for this model
     */
    public static function schema() : Schema
    {
        return self::memoizeStatic(get_called_class(). '::' . __METHOD__, function () {
            return static::createSchema();
        });
    }

    /**
     * @param Database $database
     * The database to read from
     *
     * @param string $pirmary_key_value
     * The PKID of the the value to read
     *
     * @return Model
     * Read a model from the database with the given pk
     */
    abstract public static function read(
        Database $database,
        $primary_key_value
    );

    /**
     * @param Database $database
     * A database to write this model to
     *
     * @return null
     */
    abstract public function write(Database $database);

    /**
     * @param Database $database
     * The database to read from
     *
     * @param string|array $pirmary_key_value
     * The PKID of the the row to delete
     */
    abstract public static function delete(
        Database $database,
        $primary_key_value
    );

    /**
     *
     * @param Database $database
     * A database to write this model to
     *
     * @return null
     */
    public function writeAssociationList(Database $database)
    {
        foreach (static::schema()->getAssociationList() as $key => $association) {
            $association->write(
                $database,
                $this
            );
        }
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    abstract public static function fromRow(array $row);
}
