<?php declare(strict_types=1);
namespace Phan\Database;

use Phan\Database;

/**
 * An association from a model to a list of strings
 */
class ListAssociation extends Association
{
    use \Phan\Memoize;

    /**
     * @param string $table_name
     * The table we'll be interacting with
     *
     * @param string $item_sql_type
     * The type of items being stored
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
        string $item_sql_type,
        \Closure $read_closure,
        \Closure $write_closure
    ) {
        $schema = new Schema($table_name, [
            new Column('id', Column::TYPE_INT, true, true),
            new Column('source_pk', 'STRING'),
            new Column('value', $item_sql_type)
        ]);

        $schema->addCreateQuery(
            "CREATE UNIQUE INDEX IF NOT EXISTS {$table_name}_source_pk_value ON `$table_name` "
            . " (source_pk, value)"
        );

        parent::__construct($schema, $read_closure, $write_closure);
    }

    /**
     * @param Database $database
     * The database to read from
     *
     * @param ModelOne $model
     * The source model of the association
     *
     * @return void
     * Read a model from the database with the given pk
     */
    public function read(Database $database, ModelOne $model)
    {
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

        $column = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $column[] = $row['value'];
        }

        // Write the map to the model
        $read_closure($model, $column);
    }

    /**
     * @param Database $database
     * The database to write to
     *
     * @param ModelOne $model
     * The source model of the association
     *
     * @return void
     */
    public function write(Database $database, ModelOne $model)
    {
        // Ensure that we've initialized this model
        $this->schema->initializeOnce($database);

        $write_closure = $this->write_closure;

        $primary_key_value =
            $model->primaryKeyValue();

        foreach ($write_closure($model) as $key => $value) {

            // Write the association
            $query =
                $this->schema->queryForInsert([
                    'source_pk' => $primary_key_value,
                    'value' => $value,
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
            'source_pk',
            $primary_key_value
        );

        $database->exec($query);
    }
}
