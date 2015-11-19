<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
abstract class ModelOne extends Model implements ModelOneInterface {

    public function write(Database $database) {
        parent::write($database);

        // Write the data for his model
        $insert_query =
            $this->schema()->queryForInsert(
                $this->columnNameRowValueMap()
            );

        // Write the model's data
        $database->exec($insert_query);
    }

}
