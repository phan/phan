<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\UnionType;

class ClassConstant extends ClassElement implements ConstantInterface
{
    use ConstantTrait;

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
    public function isOverrideIntended() : bool {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::IS_OVERRIDE_INTENDED
        );
    }

    /**
     * @param bool $is_override_intended - True if this class constant is intended to be an override of another class constant (contains (at)override)

     * @return void
     */
    public function setIsOverrideIntended(bool $is_override_intended) {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_OVERRIDE_INTENDED,
                $is_override_intended
            )
        );
    }
}
