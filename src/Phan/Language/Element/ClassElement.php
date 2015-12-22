<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Exception\CodeBaseException;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassElement;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;

abstract class ClassElement extends TypedStructuralElement {

    /**
     * @return FullyQualifiedClassName
     * The FQSEN of the class that originally defined
     * this element
     */
    public function getDefiningClassFQSEN(
    ) : FullyQualifiedClassName {
        if (!($this->getFQSEN() instanceof FullyQualifiedClassElement)) {
            return null;
        }

        return $this->getFQSEN()->getFullyQualifiedClassName();
    }

    /**
     * @return Clazz
     * The class that defined this element
     *
     * @throws CodeBaseException
     * An exception may be thrown if we can't find the
     * class
     */
    public function getDefiningClass(
        CodeBase $code_base
    ) : Clazz {
        $class_fqsen = $this->getDefiningClassFQSEN();

        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            throw new CodeBaseException(
                $class_fqsen,
                "Defining class $class_fqsen for {$this->getFQSEN()} not found"
            );
        }

        return $code_base->getClassByFQSEN($class_fqsen);
    }

}
