<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database;
use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\Element\Property as PropertyElement;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedPropertyName;
use \Phan\Language\UnionType;

class Property extends ModelOne {

    /**
     * @var PropertyElement
     */
    private $property;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var string
     */
    private $scope_name;


    /**
     * @param PropertyElement $property
     * @param string $scope
     * @param string $scope_name
     */
    public function __construct(
        PropertyElement $property,
        string $scope,
        string $scope_name
    ) {
        $this->property = $property;
        $this->scope = $scope;
        $this->scope_name = $scope_name;
    }

    public static function createSchema() : Schema {
        return new Schema('Property', [
            new Column('scope_name', Column::TYPE_STRING, true),
            new Column('fqsen', Column::TYPE_STRING),
            new Column('name', Column::TYPE_STRING),
            new Column('type', Column::TYPE_STRING),
            new Column('flags', Column::TYPE_INT),
            new Column('context', Column::TYPE_STRING),
            new Column('is_deprecated', Column::TYPE_BOOL),
        ]);
    }

    /**
     * @return PropertyElement
     */
    public function getProperty() : PropertyElement {
        return $this->property;
    }

    /**
     * We include this method in order to narrow the return
     * type
     */
    public static function read(
        Database $database,
        $primary_key_value
    ) : Property {
        return parent::read($database, $primary_key_value);
    }

    /**
     * @return string
     * The value of the primary key for this model
     */
    public function primaryKeyValue() {
        return $this->scope . '|' . $this->scope_name;
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return [
            'scope_name' => $this->primaryKeyValue(),
            'fqsen' => (string)$this->property->getFQSEN(),
            'name' => (string)$this->property->getName(),
            'type' => (string)$this->property->getUnionType(),
            'flags' => $this->property->getFlags(),
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
    public static function fromRow(array $row) : Property {
        list($scope, $name) = explode('|', $row['scope_name']);

        $property = new Property(new PropertyElement(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags']
        ), $scope, $name);

        return $property;
    }

}
