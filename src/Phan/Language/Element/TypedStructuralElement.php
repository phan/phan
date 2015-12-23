<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Database;
use \Phan\Language\Context;
use \Phan\Language\FileRef;
use \Phan\Language\UnionType;
use \Phan\Model\CalledBy;

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
     * @var FileRef[]
     * A list of locations in which this typed structural
     * element is referenced from.
     */
    private $reference_list = [];

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

        // Clone the FQSEN if it exists
        if ($this instanceof Addressable) {
            if ($this->getFQSEN()) {
                $this->setFQSEN(
                    clone($this->getFQSEN())
                );
            }
        }
    }

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    public function getName() : string {
        return $this->name;
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
     * @param FileRef $file_ref
     * A reference to a location in which this typed structural
     * element is referenced.
     *
     * @return void
     */
    public function addReference(FileRef $file_ref) {
        $this->reference_list[] = $file_ref;

        // If requested, save the reference to the
        // database
        if (Database::isEnabled()) {
            if ($this instanceof Addressable) {
                (new CalledBy(
                    (string)$this->getFQSEN(),
                    $file_ref
                ))->write(Database::get());
            }
        }
    }

    /**
     * @return FileRef[]
     * A list of references to this typed structural element.
     */
    public function getReferenceList() : array {
        return $this->reference_list;
    }

    /**
     * @param CodeBase $code_base
     * Some elements may need access to the code base to
     * figure out their total reference count.
     *
     * @return int
     * The number of references to this typed structural element
     */
    public function getReferenceCount(
        CodeBase $code_base
    ) : int {
        return count($this->reference_list);
    }

}
