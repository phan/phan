<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\UnionType;

class ClassConstant extends ClassElement implements ConstantInterface
{
    use ConstantTrait;

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
     * @param FullyQualifiedClassConstantName $fqsen
     * A fully qualified name for the class constant
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedClassConstantName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        // Presume that this is the original definition
        // of this class constant, and let it be overwritten
        // if it isn't.
        $this->setDefiningFQSEN($fqsen);
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     *
     * @return UnionType
     */
    public function getUnionType() : UnionType
    {
        if (null !== ($union_type = $this->getFutureUnionType())) {
            $this->getUnionType()->addUnionType($union_type);
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedClassConstantName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FullyQualifiedClassConstantName
    {
        \assert(!empty($this->fqsen), "FQSEN must be defined");
        return $this->fqsen;
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

        return $string . 'const ' . $this->getName();
    }

    /**
     * @return bool
     * True if this class constant is intended to be an override of another class constant (contains (at)override)
     */
    public function isOverrideIntended() : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::IS_OVERRIDE_INTENDED
        );
    }

    /**
     * @param bool $is_override_intended - True if this class constant is intended to be an override of another class constant (contains (at)override)

     * @return void
     */
    public function setIsOverrideIntended(bool $is_override_intended)
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_OVERRIDE_INTENDED,
                $is_override_intended
            )
        );
    }

    public function toStub() : string
    {
        $string = '    ';

        if ($this->isPublic()) {
            $string .= 'public ';
        } elseif ($this->isProtected()) {
            $string .= 'protected ';
        } elseif ($this->isPrivate()) {
            $string .= 'private ';
        }

        $string .= 'const ' . $this->getName() . ' = ';
        $fqsen = (string)$this->getFQSEN();
        if (defined($fqsen)) {
            // TODO: Could start using $this->getNodeForValue()?
            $string .= var_export(constant($fqsen), true) . ';';
        } else {
            $string .= "null;  // could not find";
        }
        return $string;
    }
}
