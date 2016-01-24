<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\CodeBase;
use \Phan\Database;
use \Phan\Language\FQSEN;
use \Phan\Language\FileRef;
use \Phan\Model\CalledBy;

abstract class AddressableElement extends TypedElement implements AddressableElementInterface
{
    /**
     * @var FQSEN
     */
    protected $fqsen;

    /**
     * @var FileRef[]
     * A list of locations in which this typed structural
     * element is referenced from.
     */
    private $reference_list = [];

    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() {
        assert(!empty($this->fqsen),
            "Empty FQSEN for $this\n");

        return $this->fqsen;
    }

    /**
     * @param FQSEN $fqsen
     * A fully qualified structural element name to set on
     * this element
     *
     * @return void
     */
    public function setFQSEN(FQSEN $fqsen)
    {
        $this->fqsen = $fqsen;
    }

    /**
     * @return bool
     * True if this is a public property
     */
    public function isPublic() : bool
    {
        return !(
            $this->isProtected() || $this->isPrivate()
        );
    }

    /**
     * @return bool
     * True if this is a protected property
     */
    public function isProtected() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\MODIFIER_PROTECTED
        );
    }

    /**
     * @return bool
     * True if this is a private property
     */
    public function isPrivate() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\MODIFIER_PRIVATE
        );
    }

    /**
     * After a clone is called on this object, clone our
     * type and fqsen so that they survive copies intact
     *
     * @return void
     */
    public function __clone()
    {
        parent::__clone();

        $this->setFQSEN(
            clone($this->getFQSEN())
        );
    }

    /**
     * @param FileRef $file_ref
     * A reference to a location in which this typed structural
     * element is referenced.
     *
     * @return void
     */
    public function addReference(FileRef $file_ref)
    {
        $this->reference_list[] = $file_ref;

        if (Database::isEnabled()) {
            (new CalledBy(
                (string)$this->getFQSEN(),
                $file_ref
            ))->write(Database::get());
        }
    }

    /**
     * @return FileRef[]
     * A list of references to this typed structural element.
     */
    public function getReferenceList() : array
    {
        if (!empty($this->reference_list)) {
            return $this->reference_list;
        }

        // If we have a database, see if we have some callers
        // defined there and save those
        if (Database::isEnabled()) {
            $this->reference_list = array_map(
                function (CalledBy $called_by) : FileRef {
                    return $called_by->getFileRef();
                },
                CalledBy::findManyByFQSEN(
                    Database::get(),
                    $this->getFQSEN()
                )
            );
        }

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
