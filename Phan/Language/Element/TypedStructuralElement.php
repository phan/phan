<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Persistent\Column;
use \Phan\Persistent\Schema;

/**
 * Any PHP structural element that also has a type and is
 * addressable such as a class, method, closure, property,
 * constant, variable, ...
 */
abstract class TypedStructuralElement extends StructuralElement {

    /**
     * @var string
     * The name of the typed structural element
     */
    private $name;

    /**
     * @var UnionType
     * A set of types satisfyped by this typed structural
     * element.
     */
    private $type = null;

    /**
     * @var int
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    private $flags = 0;

    /**
     * @var FQSEN
     * The fully-qualified structural element name
     */
    protected $fqsen = null;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct($context);

        $this->name = $name;
        $this->type = $type;
        $this->flags = $flags;
    }

    /**
     * After a clone is called on this object, clone our
     * type and fqsen so that they survive copies intact
     *
     * @return null
     */
    public function __clone() {
        parent::__clone();
        $this->type = $this->type
            ? clone($this->type)
            : $this->type;

        $this->fqsen = $this->fqsen
            ? clone($this->fqsen)
            : $this->fqsen;
    }

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FQSEN {
        // Get the stored FQSEN if it exists
        if ($this->fqsen) {
            return $this->fqsen;
        }

        // else generate it
        return $this->getContext()->getScopeFQSEN();
    }

    /**
     * @param FQSEN $fqsen
     * A fully qualified structural element name to set on
     * this element
     *
     * @return null
     */
    public function setFQSEN(FQSEN $fqsen) {
        $this->fqsen = $fqsen;
    }

    /**
     * @return UnionType
     * Get the type of this structural element
     */
    public function getUnionType() : UnionType {
        return $this->type;
    }

    /**
     * @param UnionType $type
     * Set the type of this element
     *
     * @return null
     */
    public function setUnionType(UnionType $type) {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getFlags() : int {
        return $this->flags;
    }

    /**
     * @param int $flags
     * @return null
     */
    public function setFlags(int $flags) {
        $this->flags = $flags;
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema(__CLASS__, [
            new Column('fqsen', 'STRING', true),
            new Column('name', 'STRING'),
            new Column('type', 'STRING'),
            new Column('flags', 'INT'),
            new Column('context', 'STRING'),
            new Column('is_deprecated', 'BOOL'),
        ]);

        return $schema;
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return array_merge(parent::toRow(), [
            'name' => (string)$this->name,
            'type' => (string)$this->type,
            'flags' => $this->flags,
            'fqsen' => (string)$this->fqsen,
            'context' => base64_encode(serialize($this->getContext())),
            'is_deprecated' => $this->isDeprecated(),
        ]);
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) {
        assert(false, 'fromRow');
    }

    /**
     * @return string
     * The value of the primary key for this model
     */
    public function primaryKeyValue() {
        return (string)$this->fqsen;
    }

}
