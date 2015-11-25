<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\UnionType;

class Method extends ModelOne {

    /**
     * @var \Phan\Language\Element\Method
     */
    private $method;

    /**
     * @param \Phan\Language\Element\Method $method
     */
    public function __construct(\Phan\Language\Element\Method $method) {
        $this->method = $method;
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema('Method', [
            new Column('scope', Column::TYPE_STRING, true),
            new Column('fqsen', Column::TYPE_STRING, true),
            new Column('name', Column::TYPE_STRING),
            new Column('type', Column::TYPE_STRING),
            new Column('flags', Column::TYPE_INT),
            new Column('context', Column::TYPE_STRING),
            new Column('is_deprecated', Column::TYPE_BOOL),
            new Column('number_of_required_parameters', Column::TYPE_INT),
            new Column('number_of_optional_parameters', Column::TYPE_INT),
            new Column('is_dynamic', Column::TYPE_BOOL),
        ]);

        return $schema;
    }

    /**
     * @return string
     * The value of the primary key for this model
     */
    public function primaryKeyValue() {
        return (string)$this->clazz->getFQSEN();
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return [
            'name' => (string)$this->method->getName,
            'type' => (string)$this->method->getUnionType(),
            'flags' => $this->method->getFlags(),
            'fqsen' => (string)$this->method->getFQSEN(),
            'context' => base64_encode(serialize($this->method->getContext())),
            'is_deprecated' => $this->method->isDeprecated(),
            'number_of_required_parameters' =>
                $this->method->getNumberOfRequiredParameters(),
            'number_of_optional_parameters' =>
                $this->method->getNumberOfOptionalParameters(),
            'is_dynamic' => $this->method->isDynamic(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : \Phan\Language\Element\Method {
        return new \Phan\Language\Element\Method(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags'],
            $row['number_of_required_parameters'],
            $row['number_of_optional_parameters'],
            (bool)$row['is_dynamic']
        );
    }
}
