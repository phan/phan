<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\UnionType;
use \Phan\Persistent\Column;
use \Phan\Persistent\Schema;

class Property extends TypedStructuralElement {

    /**
     * @param \phan\Context $context
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
        parent::__construct(
            $context,
            $name,
            $type,
            $flags
        );
    }

    /**
     * @return bool
     * True if this is a public property
     */
    public function isPublic() {
        return !(
            $this->isProtected() || $this->isPrivate()
        );
    }

    /**
     * @return bool
     * True if this is a protected property
     */
    public function isProtected() {
        return (bool)(
            $this->getFlags() & \ast\flags\MODIFIER_PROTECTED
        );
    }

    /**
     * @return bool
     * True if this is a private property
     */
    public function isPrivate() {
        return (bool)(
            $this->getFlags() & \ast\flags\MODIFIER_PRIVATE
        );
    }

    public function __toString() : string {
        $string = '';

        if ($this->isPublic()) {
            $string .= 'public ';
        } else if ($this->isProtected()) {
            $string .= 'protected ';
        } else if ($this->isPrivate()) {
            $string .= 'private ';
        }

        $string .= "{$this->getUnionType()} {$this->getName()}";

        return $string;
    }

    /**
     * @return Schema
     * The schema for this model
     */
    public static function createSchema() : Schema {
        $schema = new Schema('Property', [
            new Column('fqsen', 'STRING', true),
            new Column('name', 'STRING'),
            new Column('type', 'STRING'),
            new Column('flags', 'INTEGER'),
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
        ]);
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : array {
        print_r($row);
    }

}
