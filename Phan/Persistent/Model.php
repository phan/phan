<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class Model {
    use \Phan\Memoize;

    /**
     * @var Model[]
     * A list of associated models for this model
     */
    private $association_list = [];

    /**
     * @return Schema
     * Get the schema for this model
     */
    public function schema() : Schema {
        return $this->memoize(__METHOD__, function() {
            return $this->createSchema();
        });
    }

    /**
     *
     */
    public function initializeOnce(Database $database) {
        $this->memoize(__METHOD__, function() use ($database) {

            $query = $this->schema()->queryForCreateTable();

            // Make sure the table has been created
            $database->exec($query);

            return 1;
        });
    }

    /**
     * @return Schema
     * The schema for this model
     */
    abstract public function createSchema() : Schema;

    /**
     * @param Database $database
     * A database to write this model to
     *
     * @return null
     */
    public function write(Database $database) {
        // Ensure that we've initialized this model
        $this->initializeOnce($database);

        // Write each association
        foreach ($this->association_list as $model) {
            $model->write($database);
        }
    }

    /**
     * @param Model $model
     * A model to associate with this model
     *
     * @return null
     */
    public function addAssociation(Model $model) {
        $this->association_list[] = $model;
    }

}
