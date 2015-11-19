<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class ModelMap extends ModelList {

    /**
     * @var string
     */
    protected $table_name;

    /**
     * @var ModelOne
     */
    protected $source_model;

    /**
     * @var \Closure
     */
    protected $closure_read_data;

    /**
     * @var \Closure
     */
    protected $closure_write_data;

    /**
     * Create a model that maps one model to another
     *
     * @param string $table_name
     * The name of this association table
     *
     * @param ModelOne $source_model
     * The schema we're mapping from
     *
     * @param \Closure $closure_read_data
     * A closure that when executed returns
     * a map from string to Model
     *
     * @param \Closure $closure_write_data
     * A closure called when we have data to write to
     * the in-memory model
     */
    public function __construct(
        string $table_name,
        ModelOne $source_model,
        \Closure $closure_read_data,
        \Closure $closure_write_data
    ) {
        $this->table_name = $table_name;
        $this->source_model = $source_model;
        $this->closure_read_data = $closure_read_data;
        $this->closure_write_data = $closure_write_data;
    }

}
