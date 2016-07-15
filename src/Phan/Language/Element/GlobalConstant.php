<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\UnionType;

class GlobalConstant extends AddressableElement implements ConstantInterface
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
     * @return FullyQualifiedGlobalConstantName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FullyQualifiedGlobalConstantName
    {
        assert(!empty($this->fqsen), "FQSEN must be defined");
        return $this->fqsen;
    }
}
