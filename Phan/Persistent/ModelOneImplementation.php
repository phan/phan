<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
class ModelOneImplementation extends ModelOne {

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var array
     * A map of values for the schema column
     * names
     */
    private $column_name_row_value_map = [];

    /**
     * @var mixed
     * The value of the primary key for this
     * row
     */
    private $primary_key_value = null;

    /**
     * @param Schema $schema
     *
     * @param array $column_name_row_value_map
     * A map of values for the schema column
     * names
     *
     * @param mixed $primary_key_value
     * The value of the primary key for this
     * row
     */
    public function __construct(
        Schema $schema,
        array $column_name_row_value_map,
        $primary_key_value
    ) {
        $this->schema = $schema;
        $this->column_name_row_value_map = $column_name_row_value_map;
        $this->primary_key_value = $primary_key_value;
    }

    public function createSchema() : Schema {
        return $this->schema;
    }

    public function primaryKeyValue() {
        return $this->primary_key_value;
    }

    public function columnNameRowValueMap() : array {
        return $this->column_name_row_value_map;
    }

}
