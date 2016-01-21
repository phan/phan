<?php declare(strict_types=1);
namespace Phan\Database;

use \Phan\Database;

/**
 * An association from a model to a list of strings
 */
class ModelListAssociation extends Association {
    use \Phan\Memoize;

    /** @var string */
    private $target_model_name;

    /**
     * @param string $table_name
     * The table storing the primary key association
     *
     * @param string $target_model_name
     * The name of the target model class
     *
     * @param \Closure $read_closure
     * A closure that accepts a map from keys to models
     * that are to be handled by the source model
     *
     * @param \Closure $write_closure
     * A closure that returns an array mapping string keys
     * to model objects that will be written.
     */
    public function __construct(
        string $table_name,
        string $target_model_name,
        \Closure $read_closure,
        \Closure $write_closure
    ) {
        $schema = new Schema($table_name, [
            new Column('id', Column::TYPE_INT, true, true),
            new Column('source_pk', 'STRING'),
            new Column('target_pk', 'STRING')
        ]);

        /*
        $schema->addCreateQuery(
            "CREATE UNIQUE INDEX IF NOT EXISTS {$table_name}_source_pk_value ON `$table_name` "
            . " (source_pk, value)"
        );
        */

        parent::__construct($schema, $read_closure, $write_closure);
        $this->target_model_name = $target_model_name;
    }

    /**
     * @param Database $database
     * The database to read from
     *
     * @param ModelOne $model
     * The source model of the association
     *
     * @return Model
     * Read a model from the database with the given pk
     */
    public function read(Database $database, ModelOne $model) {
        $read_closure = $this->read_closure;

        // Select all rows for this PK from the
        // association table
        $select_query =
            $this->schema->queryForSelectColumnValue(
                'source_pk',
                $model->primaryKeyValue()
            );

        $result =
            $database->query($select_query);

        if (!$result) {
            $read_closure($model, []);
            return;
        }

        $columns = [];
        $target_model_name = $this->target_model_name;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $target_model_name::read(
                $database,
                (int)$row['target_pk']
            );
        }

        // Write the map to the model
        $read_closure($model, $columns);
    }

    /**
     * @param Database $database
     * The database to write to
     *
     * @param ModelOne $model
     * The source model of the association
     *
     * @return null
     */
    public function write(Database $database, ModelOne $model) {
        // Ensure that we've initialized this model
        $this->schema->initializeOnce($database);

        $write_closure = $this->write_closure;

        $source_pk = $model->primaryKeyValue();

        foreach ($write_closure($model) as $key => $target) {

            // Write the target out
            $target->write($database);

            // Write the association
            $query =
                $this->schema->queryForInsert([
                    'source_pk' => $source_pk,
                    'target_pk' => (string)$target->primaryKeyValue(),
                ]);

            $database->exec($query);
        }
    }

    /**
     * @param Database $database
     * The database to read from
     *
     * @param string|array $pirmary_key_value
     * The PKID of the the row to delete
     */
    public function delete(
        Database $database,
        $primary_key_value
    ) {
        // Ensure that we've initialized this model
        $this->schema->initializeOnce($database);

        $query = $this->schema->queryForDeleteColumnValue(
            'source_pk', $primary_key_value
        );

        $database->exec($query);
    }

}
