<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FileRef;
use Phan\Language\UnionType;
use Phan\Model\CalledBy;

abstract class AddressableElement extends TypedElement implements AddressableElementInterface
{
    use \Phan\Memoize;

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
     *
     * @param FQSEN $fqsen
     * A fully qualified name for the element
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FQSEN $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags
        );

        $this->setFQSEN($fqsen);
    }

    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() {
        assert(!empty($this->fqsen), "FQSEN must be defined");
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
     * @param FileRef $file_ref
     * A reference to a location in which this typed structural
     * element is referenced.
     *
     * @return void
     */
    public function addReference(FileRef $file_ref)
    {
        $this->reference_list[] = $file_ref;
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

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     * @override
     */
    public final function hydrate(CodeBase $code_base)
    {
        if (!$this->isFirstExecution(__METHOD__)) {
            return;
        }

        $this->hydrateOnce($code_base);
    }

    protected function hydrateOnce(CodeBase $code_base)
    {
        // Do nothing unless overridden
    }
}
