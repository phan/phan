<?php declare(strict_types=1);
namespace Phan\Database;

use \Phan\Database;
use \SQLite3;

/**
 * A schema for a persistent model
 */
class Schema
{
    use \Phan\Memoize;

    /**
     * @var string
     */
    private $table_name;

    /**
     * @var string
     * The name of the primary key column
     */
    private $primary_key_name;

    /**
     * @var Column[];
     */
    private $column_def_map;

    /**
     * @var Association[]
     * A list of associated models for this model
     */
    private $association_list = [];

    /**
     * @var string[]
     * A list of queries to execute at table creation time
     */
    private $create_query_list = [];

    /**
     * Create a schema
     *
     * @param string $table_name
     * The name of the table that this model is associated
     * with
     *
     * @param Column[] $column_list
     * A list of columns on the table
     */
    public function __construct(
        string $table_name,
        $column_list
    ) {
        $this->table_name = $table_name;

        foreach ($column_list as $column) {
            $this->column_def_map[$column->name()] = $column;
            if ($column->isPrimaryKey()) {
                $this->primary_key_name = $column->name();
            }
        }

        assert(
            !empty($this->primary_key_name),
            "There must be a primary key column. None given for $table_name."
        );
    }

    /**
     * @param string $query
     * A query to execute at table creation time, such as a query
     * to create a key.
     */
    public function addCreateQuery(string $query)
    {
        $this->create_query_list[] = $query;
    }

    /**
     * @param Association $model
     * A model to associate with this model
     *
     * @return null
     */
    public function addAssociation(Association $model)
    {
        $this->association_list[] = $model;
    }

    /**
     * @return Association[]
     */
    public function getAssociationList() : array
    {
        return $this->association_list;
    }

    /**
     * @return string
     * The name of the PK column
     */
    public function primaryKeyName() : string
    {
        return $this->primary_key_name;
    }

    /**
     * Initialize this table in the given database the first
     * time the method is called and never again.
     *
     * @return null
     */
    public function initializeOnce(Database $database)
    {
        $this->memoize(__METHOD__, function () use ($database) {
            $query = $this->queryForCreateTable();

            // Make sure the table has been created
            $database->exec($query);

            // Execute each creation query to add additional
            // table constraints, etc.
            foreach ($this->create_query_list as $query) {
                $database->exec($query);
            }

            return 1;
        });
    }

    /**
     * @return string
     * A query string that creates this table if it doesn't
     * yet exist
     */
    public function queryForCreateTable() : string
    {
        $column_def_list = array_map(function (Column $column) {
            return (string)$column;
        }, $this->column_def_map);


        $has_autoincrement =
            array_reduce(
                $this->column_def_map,
                function (bool $carry, Column $column) {
                    return ($carry || $column->isAutoIncrement());
                },
                false
            );

        $primary_key_list = '';
        if (!$has_autoincrement) {
            // Get the list of primary keys for the table
            $primary_key_list =
                array_map(function (Column $column) {
                    return $column->name();
                }, array_filter($this->column_def_map, function (Column $column) {
                    return $column->isPrimaryKey();
                }));

                $primary_key_list =
                ', PRIMARY KEY (' . implode(', ', $primary_key_list) . ')';

        }

        return "CREATE TABLE IF NOT EXISTS {$this->table_name} "
            . '('
            . implode(', ', $column_def_list)
            . $primary_key_list
            . ')'
            ;
    }

    /**
     * @param array
     * A map from column name to row value
     *
     * @return string
     * A query for inserting a row for this schema
     */
    public function queryForInsert(array $row_map) : string
    {
        return "REPLACE INTO {$this->table_name} "
            . '(' . implode(', ', $this->columnList($row_map)) . ')'
            . ' values '
            . '(' . implode(', ', $this->valueList($row_map)) . ')'
            ;
    }

    private function columnList(array $row_map) : array
    {
        $list = [];
        foreach ($row_map as $name => $value) {
            $list[] = $name;
        }
        return $list;
    }

    private function valueList(array $row_map) : array
    {
        $value_list = [];
        foreach ($row_map as $name => $value) {
            if (empty($this->column_def_map[$name])) {
                print_r($this);
                print "$name\n";
            }

            $column_type =
                $this->column_def_map[$name]->sqlType();

            if ($column_type == 'STRING') {
                $value_list[] =
                    '"' . SQLite3::escapeString((string)$value) . '"';
            } elseif ($column_type == 'BOOL') {
                $value_list[] = ($value ? 1 : 0);
            } else {
                $value_list[] = $value;
            }

        }

        return $value_list;
    }

    /**
     * @param string $primary_key_value
     * The primary key to get a select query for
     *
     * @return string
     * A query for getting all values for the row with the
     * given primary key
     */
    public function queryForSelect($primary_key_value) : string
    {
        return $this->queryForSelectColumnValue(
            $this->primaryKeyName(),
            $primary_key_value
        );
    }

    /**
     * @param string $primary_key
     * The primary key to get a select query for
     *
     * @return string
     * A query for getting all values for the row with the
     * given primary key
     */
    public function queryForSelectColumnValue(string $column, $value) : string
    {

        if ($this->column_def_map[$column]->sqlType() == 'STRING') {
            $value = '"' . SQLite3::escapeString((string)$value) . '"';
        }

        return "SELECT * FROM {$this->table_name} "
            . "WHERE $column = $value";
    }

    /**
     * @param string|array $primary_key_value
     * The primary key to get a select query for
     *
     * @return string
     * A query for deleting all values for the row with the
     * given primary key
     */
    public function queryForDelete($primary_key_value) : string
    {
        return $this->queryForDeleteColumnValue(
            $this->primaryKeyName(),
            $primary_key_value
        );
    }

    /**
     * @param string|array $value
     * The primary key to get a select query for
     *
     * @return string
     * A query for deleting all values for the row with the
     * given primary key
     */
    public function queryForDeleteColumnValue(string $column, $value) : string
    {

        if ($this->column_def_map[$column]->sqlType() == 'STRING') {
            $value = '"' . SQLite3::escapeString((string)$value) . '"';
        }

        return "DELETE FROM {$this->table_name} "
            . "WHERE $column = $value";
    }
}
