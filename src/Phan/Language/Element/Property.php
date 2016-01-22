<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedPropertyName;
use \Phan\Language\UnionType;

class Property extends ClassElement implements Addressable
{
    use AddressableImplementation;
    use ElementFutureUnionType;

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

        $string .= "{$this->getUnionType()} {$this->getName()}";

        return $string;
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     */
    public function getUnionType() : UnionType
    {
        if (null !== ($union_type = $this->getFutureUnionType())) {
            $this->getUnionType()->addUnionType($union_type);
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedPropertyName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FQSEN
    {
        // Get the stored FQSEN if it exists
        if ($this->fqsen) {
            return $this->fqsen;
        }

        return FullyQualifiedPropertyName::fromStringInContext(
            $this->getName(),
            $this->getContext()
        );
    }
}
