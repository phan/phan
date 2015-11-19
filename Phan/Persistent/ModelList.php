<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class ModelList extends Model implements ModelListInterface {

    /**
     * @param Database $database
     * A database to write this model to
     *
     * @return null
     */
    public function write(Database $database) {
        parent::write($database);

        foreach ($this->modelOneList() as $model) {
            $model->write($database);
        }
    }
}
