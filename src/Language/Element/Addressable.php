<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\FQSEN;

trait Addressable {

    /**
     * @var FQSEN
     */
    protected $fqsen;

    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    abstract public function getFQSEN() : FQSEN;

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
     * Implementing classes must have a getFlags() method
     * that returns flags on the class
     */
    abstract function getFlags() : int;

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

}
