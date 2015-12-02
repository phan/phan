<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\Element\Constant as ConstantElement;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Language\UnionType;

class Constant extends ModelOne {

    /**
     * @var ConstantElement
     */
    private $constant;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var string
     */
    private $scope_name;

    /**
     * @param ConstantElement $constant
     * @param string $scope
     * @param string $scope_name
     */
    public function __construct(
        ConstantElement $constant,
        string $scope,
        string $scope_name
    ) {
        $this->constant = $constant;
        $this->scope = $scope;
        $this->scope_name = $scope_name;
    }

    public static function createSchema() : Schema {
        return new Schema('Constant', [
            new Column('scope_name', Column::TYPE_STRING, true),
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
            'fqsen' => (string)$this->constant->getFQSEN(),
            'name' => (string)$this->constant->getName(),
            'type' => (string)$this->constant->getUnionType(),
            'flags' => $this->constant->getFlags(),
            'context' => base64_encode(serialize($this->constant->getContext())),
            'is_deprecated' => $this->constant->isDeprecated(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Constant
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : Constant {
        list($scope, $name) = explode('|', $row['scope_name']);

        $constant = new Constant(new ConstantElement(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags']
        ), $scope, $name);

        return $constant;
    }

}
