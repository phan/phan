<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Language\UnionType;

class Constant extends ModelOne {

    /**
     * @var \Phan\Language\Element\Constant
     */
    private $constant;

    /**
     * @param \Phan\Language\Element\Constant $constant
     */
    public function __construct(
        \Phan\Language\Element\Constant $constant
    ) {
        $this->constant = $constant;
    }

    public static function createSchema() : Schema {
        return new Schema('Constant', [
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
        return (string)$this->constant->getFQSEN();
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return [
            'name' => (string)$this->constant->getName(),
            'type' => (string)$this->constant->getUnionType(),
            'flags' => $this->constant->getFlags(),
            'fqsen' => (string)$this->constant->getFQSEN(),
            'context' => base64_encode(serialize($this->constant->getContext())),
            'is_deprecated' => $this->constant->isDeprecated(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : \Phan\Language\Element\Constant {
        $constant = new \Phan\Language\Element\Constant(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags']
        );

        return $constant;
    }

}
