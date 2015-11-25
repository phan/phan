<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\Element\Clazz as ClazzElement;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;

class Clazz extends ModelOne {

    /**
     * @var ClazzElement
     */
    private $clazz;

    /**
     * @param ClazzElement $clazz
     */
    public function __construct(ClazzElement $clazz) {
        $this->clazz = $clazz;
    }

    public static function createSchema() : Schema {
        return new Schema('Clazz', [
            new Column('fqsen', Column::TYPE_STRING, true),
            new Column('name', Column::TYPE_STRING),
            new Column('type', Column::TYPE_STRING),
            new Column('flags', Column::TYPE_INT),
            new Column('context', Column::TYPE_STRING),
            new Column('is_deprecated', Column::TYPE_BOOL),
            new Column('parent_class_fqsen', Column::TYPE_STRING),
            new Column('interface_fqsen_list', Column::TYPE_STRING),
            new Column('trait_fqsen_list', Column::TYPE_STRING),
            new Column('is_parent_constructor_called', Column::TYPE_STRING),
        ]);
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

        $parent_class_fqsen = $this->clazz->hasParentClassFQSEN()
            ? (string)$this->clazz->getParentClassFQSEN()
            : null;

        $interface_fqsen_list_string =
            implode('|', array_map(function (FullyQualifiedClassName $fqsen) {
                return (string)$fqsen;
            }, $this->clazz->getInterfaceFQSENList()));

        $trait_fqsen_list_string =
            implode('|', array_map(function (FullyQualifiedClassName $fqsen) {
                return (string)$fqsen;
            }, $this->clazz->getInterfaceFQSENList()));

        return [
            'name' => (string)$this->clazz->getName(),
            'type' => (string)$this->clazz->getUnionType(),
            'flags' => $this->clazz->getFlags(),
            'fqsen' => (string)$this->clazz->getFQSEN(),
            'context' => base64_encode(serialize($this->clazz->getContext())),
            'is_deprecated' => $this->clazz->isDeprecated(),
            'parent_class_fqsen' => $parent_class_fqsen,
            'interface_fqsen_list' => $interface_fqsen_list_string,
            'trait_fqsen_list' => $trait_fqsen_list_string,
            'is_parent_constructor_called' =>
                $this->clazz->getIsParentConstructorCalled(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : ClazzElement {
        $parent_fqsen = $row['parent_class_fqsen']
            ? FullyQualifiedClassName::fromFullyQualifiedString($row['parent_class_fqsen'])
            : null;

        $interface_fqsen_list =
            array_map(function (string $fqsen_string) {
                return FullyQualifiedClassName::fromFullyQualifiedString(
                    $fqsen_string
                );
            }, array_filter(explode('|', $row['interface_fqsen_list'])));

        $trait_fqsen_list =
            array_map(function (string $fqsen_string) {
                return FullyQualifiedClassName::fromFullyQualifiedString(
                    $fqsen_string
                );
            }, array_filter(explode('|', $row['trait_fqsen_list'])));

        $clazz = new ClazzElement(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags'],
            $parent_fqsen,
            $interface_fqsen_list,
            $trait_fqsen_list
        );

        return $clazz;
    }

}
