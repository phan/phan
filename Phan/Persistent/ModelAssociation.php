<?php declare(strict_types=1);
namespace Phan\Persistent;

class ModelAssociation extends Association {
    use \Phan\Memoize;

    /**
     * @var string
     */
    private $associated_class_name;

    /**
     * @param string $table_name
     * The table we'll be interacting with
     *
     * @param string $associated_class_name
     * The name of hte class we're associating with
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
        string $associated_class_name,
        \Closure $read_closure,
        \Closure $write_closure
    ) {
        $schema = new Schema($table_name, [
            new Column('id', 'INTEGER', true, true),
            new Column('source_pk', 'STRING'),
            new Column('key', 'STRING'),
            new Column('target_pk', 'STRING'),
        ]);

        parent::__construct($schema, $read_closure, $write_closure);

        $this->associated_class_name = $associated_class_name;
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

        // Select all rows for this PK from the
        // association table
        $select_query =
            $this->schema->queryForSelectColumnValue(
                'source_pk',
                $model->primaryKeyValue()
            );

        $result = $database->query($select_query);

        if (!$result) {
            return;
        }

        $associated_class = $this->associated_class_name;

        // Hydrate each association row to the associated
        // object
        $map = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $key = $row['key'];
            $target_pk = $row['target_pk'];

            $map[$key] =
                $associated_class::read($database, $target_pk);
        }

        // Write the map to the model
        $read_closure = $this->read_closure;
        $read_closure($model, $map);
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

        $primary_key_value =
            $model->primaryKeyValue();

        foreach ($write_closure($model) as $key => $target_model) {

            $associated_class = $this->associated_class_name;
            $target_schema = $associated_class::schema();

            // Write the model
            $target_model->write($database);

            // Write the association
            $query =
                $this->schema->queryForInsert([
                    'source_pk' => $primary_key_value,
                    'key' => $key,
                    'target_pk' => $target_model->primaryKeyValue(),
                ]);

            $database->exec($query);
        }
    }

}
