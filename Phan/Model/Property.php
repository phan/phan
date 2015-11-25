<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedPropertyName;
use \Phan\Language\UnionType;

class Property extends ModelOne {

    /**
     * @var \Phan\Language\Element\Property
     */
    private $property;

    /**
     * @param \Phan\Language\Element\Property $property
     */
    public function __construct(
        \Phan\Language\Element\Property $property
    ) {
        $this->property = $property;
    }

    public static function createSchema() : Schema {
        return new Schema('Property', [
            new Column('fqsen', Column::TYPE_STRING, true),
            new Column('name', Column::TYPE_STRING),
            new Column('type', Column::TYPE_STRING),
            new Column('flags', Column::TYPE_INT),
            new Column('context', Column::TYPE_STRING),
            new Column('is_deprecated', Column::TYPE_BOOL),
        ]);
    }

    /**
     * @return string
     * The value of the primary key for this model
     */
    public function primaryKeyValue() {
        return (string)$this->property->getFQSEN();
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return [
            'name' => (string)$this->property->getName,
            'type' => (string)$this->property->getUnionType(),
            'flags' => $this->property->getFlags(),
            'fqsen' => (string)$this->property->getFQSEN(),
            'context' => base64_encode(serialize($this->property->getContext())),
            'is_deprecated' => $this->property->isDeprecated(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : \Phan\Language\Element\Property {
        $property = new \Phan\Language\Element\Property(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags']
        );

        return $property;
    }

}
