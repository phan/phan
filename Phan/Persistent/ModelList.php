<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class ModelList extends Model implements ModelListInterface {

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
    public static function read(Database $database, $primary_key_value) {

        $select_query =
            static::schema()->queryForSelect($primary_key_value);

        $result = $database->querySingle($select_query, true);

        $model_class =
            static::elementModelClassName();

        $model_list = [];

        foreach($result->fetchArray as $row) {
            $model_list[] = $model_class::fromRow($row);
        }

        return $model_list;
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

        foreach ($this->modelOneList() as $model) {
            $model->write($database);
        }

        // Write the associations
        $this->writeAssociationList($database);
    }
}
