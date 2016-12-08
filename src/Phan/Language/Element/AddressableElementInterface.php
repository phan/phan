<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Language\FQSEN;
use Phan\Language\FileRef;

interface AddressableElementInterface extends TypedElementInterface
{
    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN();

    /**
     * @param FQSEN $fqsen
     * A fully qualified structural element name to set on
     * this element
     *
     * @return void
     */
    public function setFQSEN(FQSEN $fqsen);

    /**
     * @return bool
     * True if this is a public property
     */
    public function isPublic() : bool;

    /**
     * @return bool
     * True if this is a protected property
     */
    public function isProtected() : bool;

    /**
     * @return bool
     * True if this is a private property
     */
    public function isPrivate() : bool;

    /**
     * @param FileRef $file_ref
     * A reference to a location in which this typed structural
     * element is referenced.
     *
     * @return void
     */
    public function addReference(FileRef $file_ref);

    /**
     * @return FileRef[]
     * A list of references to this typed structural element.
     */
    public function getReferenceList() : array;

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
    ) : int;

}
