<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Config;
use Phan\Language\FileRef;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\PropertyScope;
use Phan\Language\UnionType;

use Closure;

class Property extends ClassElement
{
    use ElementFutureUnionType;
    use ClosedScopeElement;

    /**
     * @var ?FullyQualifiedPropertyName If this was originally defined in a trait, this is the trait's defining fqsen.
     * This is tracked separately from getDefiningFQSEN() in order to not break access checks on protected/private properties.
     * Used for dead code detection.
     */
    private $real_defining_fqsen;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedPropertyName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        // Presume that this is the original definition
        // of this property, and let it be overwritten
        // if it isn't.
        $this->setDefiningFQSEN($fqsen);
        $this->real_defining_fqsen = $fqsen;

        // Set an internal scope, so that issue suppressions can be placed on property doc comments.
        // (plugins acting on properties would then pick those up).
        // $fqsen is used to locate this property.
        $this->setInternalScope(new PropertyScope(
            $context->getScope(),
            $fqsen
        ));
    }

    /**
     * @return FullyQualifiedPropertyName the FQSEN with the original definition (Even if this is private/protected and inherited from a trait). Used for dead code detection.
     *                                    Inheritance tests use getDefiningFQSEN() so that access checks won't break.
     */
    public function getRealDefiningFQSEN() : FullyQualifiedPropertyName
    {
        return $this->real_defining_fqsen ?? $this->getDefiningFQSEN();
    }

    public function __toString() : string
    {
        $string = '';

        if ($this->isPublic()) {
            $string .= 'public ';
        } elseif ($this->isProtected()) {
            $string .= 'protected ';
        } elseif ($this->isPrivate()) {
            $string .= 'private ';
        }

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        // Since the UnionType can be a future, and that
        // can throw an exception, we catch it and ignore it
        try {
            $union_type = $this->getUnionType();
        } catch (\Exception $exception) {
            $union_type = UnionType::empty();
        }

        $string .= "$union_type \${$this->getName()}";


        return $string;
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     */
    public function getUnionType() : UnionType
    {
        if (null !== ($union_type = $this->getFutureUnionType())) {
            $this->setUnionType(parent::getUnionType()->withUnionType($union_type));
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedPropertyName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FullyQualifiedPropertyName
    {
        \assert(!empty($this->fqsen), "FQSEN must be defined");
        return $this->fqsen;
    }

    public function toStub()
    {
        $string = '    ';

        if ($this->isPublic()) {
            $string .= 'public ';
        } elseif ($this->isProtected()) {
            $string .= 'protected ';
        } elseif ($this->isPrivate()) {
            $string .= 'private ';
        }

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        $string .= "\${$this->getName()}";
        $string .= ';';

        return $string;
    }

    /**
     * @internal - Used by daemon mode to restore an element to the state it had before parsing.
     * @return ?Closure
     */
    public function createRestoreCallback()
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            // We already inferred the type for this class constant/global constant.
            // Nothing to do.
            return null;
        }
        // If this refers to a class constant in another file,
        // the resolved union type might change if that file changes.
        return function () use ($future_union_type) {
            $this->future_union_type = $future_union_type;
            // Probably don't need to call setUnionType(mixed) again...
        };
    }

    /**
     * @param FileRef $file_ref
     * A reference to a location in which this typed structural
     * element is referenced.
     *
     * @param bool $is_read_reference
     * If false, track that this has been read.
     *
     * @return void
     */
    public function addReference(FileRef $file_ref, $is_read_reference = true)
    {
        if (Config::get_track_references()) {
            // Currently, we don't need to track references to PHP-internal methods/functions/constants
            // such as PHP_VERSION, strlen(), Closure::bind(), etc.
            // This may change in the future.
            if ($this->isPHPInternal()) {
                return;
            }
            $this->reference_list[$file_ref->__toString()] = $file_ref;
            if ($is_read_reference) {
                $this->enablePhanFlagBits(Flags::WAS_PROPERTY_READ);
            }
        }
    }
}
