<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\Element\Method as MethodElement;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\UnionType;

class Method extends ModelOne {

    /**
     * @var MethodElement
     */
    private $method;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var string
     */
    private $scope_name;

    /**
     * @param MethodElement $method
     * @param string $scope
     */
    public function __construct(
        MethodElement $method,
        string $scope,
        string $scope_name
    ) {
        $this->method = $method;
        $this->scope = $scope;
        $this->scope_name = $scope_name;
    }

    public function getMethod() : MethodElement {
        return $this->method;
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema('Method', [
            new Column('scope_name', Column::TYPE_STRING, true),
            new Column('fqsen', Column::TYPE_STRING),
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
            'scope_name' => $this->scope . '|' . $this->scope_name,
            'name' => (string)$this->method->getName(),
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
    public static function fromRow(array $row) : Method {
        list($scope, $name) = explode('|', $row['scope_name']);

        return new Method(new MethodElement(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags'],
            $row['number_of_required_parameters'],
            $row['number_of_optional_parameters'],
            (bool)$row['is_dynamic']
        ), $scope, $name);
    }
}
