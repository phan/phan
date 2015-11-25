<?php declare(strict_types=1);
namespace Phan\Database;

use \Phan\Database;

abstract class Association {

    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var \Closure
     */
    protected $read_closure;

    /**
     * @var \Closure
     */
    protected $write_closure;

    /**
     * @param Schema $schema
     * The schema for the table backing this association
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
        Schema $schema,
        \Closure $read_closure,
        \Closure $write_closure
    ) {
        $this->schema = $schema;
        $this->read_closure = $read_closure;
        $this->write_closure = $write_closure;
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
    abstract public function read(Database $database, ModelOne $model);

    /**
     * @param Database $database
     * The database to write to
     *
     * @param ModelOne $model
     * The source model of the association
     *
     * @return null
     */
    abstract public function write(Database $database, ModelOne $model);
}
