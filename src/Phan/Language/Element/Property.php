<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\PropertyScope;
use Phan\Language\UnionType;

class Property extends ClassElement
{
    use ElementFutureUnionType;
    use ClosedScopeElement;

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

        // Set an internal scope, so that issue suppressions can be placed on property doc comments.
        // (plugins acting on properties would then pick those up).
        // $fqsen is used to locate this property.
        $this->setInternalScope(new PropertyScope(
            $context->getScope(),
            $fqsen
        ));
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
            $union_type = new UnionType();
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
            $this->getUnionType()->addUnionType($union_type);
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
}
