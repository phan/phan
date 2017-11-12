<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
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
     * References from the same file and line are deduplicated to save memory.
     */
    private $reference_list = [];

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags
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
    public function getFQSEN()
    {
        \assert(!empty($this->fqsen), "FQSEN must be defined");
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
     * True if this is a protected element
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
     * True if this is a private element
     */
    public function isPrivate() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\MODIFIER_PRIVATE
        );
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param, may be used by subclasses)
     * The code base in which this element exists.
     *
     * @return bool
     * True if this is marked as an `(at)internal` element
     */
    public function isNSInternal(CodeBase $code_base) : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::IS_NS_INTERNAL
        );
    }

    /**
     * Set this element as being `internal`.
     * @return void
     */
    public function setIsNSInternal(bool $is_internal)
    {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::IS_NS_INTERNAL,
            $is_internal
        ));
    }

    /**
     * @param CodeBase $code_base
     * The code base in which this element exists.
     *
     * @return bool
     * True if this element is intern
     */
    public function isNSInternalAccessFromContext(
        CodeBase $code_base,
        Context $context
    ) : bool {
        // Figure out which namespace this element is within
        $element_namespace = $this->getElementNamespace($code_base);

        // Get our current namespace from the context
        $context_namespace = $context->getNamespace();

        // Test to see if the context is within the same
        // namespace as where the element is defined
        return (0 === strcasecmp($context_namespace, $element_namespace));
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
        if (Config::get_track_references()) {
            // Currently, we don't need to track references to PHP-internal methods/functions/constants
            // such as PHP_VERSION, strlen(), Closure::bind(), etc.
            // This may change in the future.
            if ($this->isPHPInternal()) {
                return;
            }
            $this->reference_list[(string)$file_ref] = $file_ref;
        }
    }

    /**
     * Copy addressable references from an element of the same subclass
     * @return void
     */
    public function copyReferencesFrom(AddressableElement $element)
    {
        if ($this === $element) {
            // Should be impossible
            return;
        }
        foreach ($element->reference_list as $key => $file_ref) {
            $this->reference_list[$key] = $file_ref;
        }
    }

    /**
     * @return FileRef[]
     * A list of references to this typed structural element.
     */
    public function getReferenceList() : array
    {
        return $this->reference_list;
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param)
     * Some elements may need access to the code base to
     * figure out their total reference count.
     *
     * @return int
     * The number of references to this typed structural element
     */
    public function getReferenceCount(
        CodeBase $code_base
    ) : int {
        return \count($this->reference_list);
    }

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     * @override
     */
    final public function hydrate(CodeBase $code_base)
    {
        if (!$this->isFirstExecution(__METHOD__)) {
            return;
        }

        $this->hydrateOnce($code_base);
    }

    protected function hydrateOnce(CodeBase $unused_code_base)
    {
        // Do nothing unless overridden
    }

    public function getElementNamespace(CodeBase $unused_code_base) : string
    {
        $element_fqsen = $this->getFQSEN();
        \assert($element_fqsen instanceof FullyQualifiedGlobalStructuralElement);

        // Figure out which namespace this element is within
        return $element_fqsen->getNamespace();
    }
}
