<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * A schema for a persistent model
 */
class Schema {

    /**
     * @var string
     */
    private $table_name;

    /**
     * @var string[]
     * A map from primary key column name to SQLite
     * data type
     */
    private $primary_key;

    /**
     * @var string[];
     */
    private $column_def_map;

    /**
     * Create a schema
     *
     * @param string $table_name
     * The name of the table that this model is associated
     * with
     *
     * @param string[]
     * A map from primary key column name to SQLite
     * data type
     *
     * @param string[] $column_def_map
     * A map from column name to its SQLITE3 type
     */
    public function __construct(
        string $table_name,
        array $primary_key,
        array $column_def_map
    ) {
        assert(1 === ($count = count($primary_key)),
            "Primary key must have a single map. Have $count.");

        $this->table_name = $table_name;
        $this->primary_key = $primary_key;
        $this->column_def_map = $column_def_map;
    }

    /**
     * @return string
     * The SQLite data type for the primary key
     */
    public function primaryKeyType() : string {
        return array_values($this->primary_key)[0];
    }

    /**
     * @return string
     * A query string that creates this table if it doesn't
     * yet exist
     */
    public function queryForCreateTable() : string {
        $column_def_list = [];

        foreach ($this->primary_key as $name => $type) {
            $column_def_list[] = "$name $type PRIMARY KEY";
        }

        foreach ($this->column_def_map as $name => $type
        ) {
            $column_def_list[] = "$name $type";
        }

        return "CREATE TABLE IF NOT EXISTS {$this->table_name} "
            . '(' . implode(', ', $column_def_list) . ')';
    }

    /**
     * @param array
     * A map from column name to row value
     *
     * @return string
     * A query for inserting a row for this schema
     */
    public function queryForInsert(array $row_map) : string {
        return "INSERT INTO {$this->table_name} "
            . '(' . implode(', ', array_keys($row_map)) . ')'
            . ' values '
            . '(' . implode(', ', array_values($row_map)) . ')'
            ;
    }

    /**
     * @param string $primary_key
     * The primary key to get a select query for
     *
     * @return string
     * A query for getting all values for the row with the
     * given primary key
     */
    public function queryForSelect(string $primary_key) : string {
        return "SELECT * FROM {$this->table_name} "
            . "WHERE {$this->primary_key_column_name} = '$primary_key'"
            ;
    }
}
