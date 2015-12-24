<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Language\FutureUnionType;
use \Phan\Language\UnionType;
use \ast\Node;

class Constant extends ClassElement implements Addressable {
    use AddressableImplementation;

    /** @var FutureUnionType */
    private $future_union_type = null;

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

    /** @return void */
    public function setFutureUnionType(
        FutureUnionType $future_union_type
    ) {
        $this->future_union_type = $future_union_type;
    }

    public function getUnionType() : UnionType {
        if (!empty($this->future_union_type)) {
            $this->setUnionType(
                $this->future_union_type->get()
            );
            $this->future_union_type = null;
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedClassConstantName|FullyQualifiedConstantName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FQSEN {
        // Get the stored FQSEN if it exists
        if ($this->fqsen) {
            return $this->fqsen;
        }

        return FullyQualifiedConstantName::fromStringInContext(
            $this->getName(),
            $this->getContext()
        );
    }

    public function __toString() : string {
        return 'const ' . $this->getName();
    }
}
