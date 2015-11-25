<?php declare(strict_types=1);
namespace Phan\Database;

use \Phan\Database;
use \Phan\Exception\NotFoundException;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class ModelOne extends Model implements ModelOneInterface {

    /**
     * @param Database $database
     * The database to read from
     *
     * @param mixed $pirmary_key_value
     * The PKID of the the value to read
     *
     * @return Model
     * Read a model from the database with the given pk
     */
    public static function read(Database $database, $primary_key_value) {
        // Ensure that we've initialized this model
        static::schema()->initializeOnce($database);

        $select_query =
            static::schema()->queryForSelect($primary_key_value);

        $row = $database->querySingle($select_query, true);

        if (empty($row)) {
            throw new NotFoundException(
                "No row found for query $select_query"
            );
        }

        $model = static::fromRow((array)$row);

        // Write each association
        foreach (static::schema()->getAssociationList() as $key => $association) {
            $association->read($database, $model);
        }

        return $model;
    }

    /**
     * @param Database $database
     * A database to write this model to
     *
     * @return null
     */
    public function write(Database $database) {
        // Ensure that we've initialized this model
        static::schema()->initializeOnce($database);

        // Write the data for his model
        $insert_query =
            $this->schema()->queryForInsert($this->toRow());

        // Write the model's data
        $database->exec($insert_query);

        // Write the associations
        $this->writeAssociationList($database);
    }

}
