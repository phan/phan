<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use \Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use \Phan\Language\UnionType;

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
     * @return FullyQualifiedClassConstantName|FullyQualifiedGlobalConstantName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FQSEN
    {
        // Get the stored FQSEN if it exists
        if ($this->fqsen) {
            return $this->fqsen;
        }

        if ($this->getContext()->isInClassScope()) {
            return FullyQualifiedClassConstantName::fromStringInContext(
                $this->getName(),
                $this->getContext()
            );
        } else {
            return FullyQualifiedGlobalConstantName::fromStringInContext(
                $this->getName(),
                $this->getContext()
            );
        }
    }
}
