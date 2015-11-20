<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;

trait Access {

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
