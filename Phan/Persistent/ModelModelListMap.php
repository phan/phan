<?php declare(strict_types=1);
namespace Phan\Persistent;

/**
 * Objects implementing this interface can be
 * read from and written to a SQLite3 database
 */
class ModelModelListMap extends ModelMap {

    /**
     * @return Schema
     * The schema for this model
     */
    public function createSchema() : Schema {
        $schema = new Schema(
            $this->table_name, [ 'id' => 'INT' ], [
                'source_pk' => $this->source_model->schema()->primaryKeyType(),
                'key' => 'STRING',
                'target_pk' => 'STRING',
            ]);

        return $schema;
    }

    /**
     * @return ModelOne[]
     * Get a list of models to write
     */
    public function modelOneList() : array {
        $model_one_list = [];

        $closure = $this->closure_read_data;
        foreach ($closure() as $key => $model) {

            // Write the model itself
            $model_one_list[] = $model;

            // Write a mapping to that model
            $model_one_list[] = new ModelOneImplementation(
                $this->schema(),
                [
                    'source_pk' => "'{$this->source_model->primaryKeyValue()}'",
                    'key' => "'$key'",
                    'target_pk' => "'{$model->primarykeyValue()}'",
                ],
                -1
            );
        }

        return $model_one_list;
    }

}
