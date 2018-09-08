<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Type;
use Phan\Language\UnionType;

use function json_encode;

/**
 * Phan's representation of the type `iterable<KeyType,ValueType>`
 */
final class GenericIterableType extends IterableType
{
    /** @phan-override */
    const NAME = 'iterable';

    /**
     * @var UnionType
     */
    private $key_union_type;

    /**
     * @var UnionType
     */
    private $element_union_type;

    protected function __construct(UnionType $key_union_type, UnionType $element_union_type, bool $is_nullable)
    {
        parent::__construct('', self::NAME, [], $is_nullable);
        $this->key_union_type = $key_union_type;
        $this->element_union_type = $element_union_type;
    }

    /**
     * @return ?UnionType returns the iterable key's union type, if this is a subtype of iterable. null otherwise.
     */
    public function getKeyUnionType() : UnionType
    {
        return $this->key_union_type;
    }

    public function getElementUnionType() : UnionType
    {
        return $this->element_union_type;
    }

    /**
     * @return UnionType returns the iterable key's union type
     * @phan-override
     *
     * @see $this->getKeyUnionType()
     */
    public function iterableKeyUnionType(CodeBase $unused_code_base)
    {
        return $this->key_union_type;
    }

    /**
     * @return UnionType returns the iterable value's union type
     * @phan-override
     *
     * @see $this->getElementUnionType()
     */
    public function iterableValueUnionType(CodeBase $unused_code_base)
    {
        return $this->element_union_type;
    }

    public static function fromKeyAndValueTypes(UnionType $key_union_type, UnionType $element_union_type, bool $is_nullable) : GenericIterableType
    {
        static $cache = [];
        $key = ($is_nullable ? '?' : '') . json_encode($key_union_type->generateUniqueId()) . ':' . json_encode($element_union_type->generateUniqueId());
        return $cache[$key] ?? ($cache[$key] = new self($key_union_type, $element_union_type, $is_nullable));
    }

    public function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof GenericIterableType) {
            // TODO: Account for scalar key casting config?
            if (!$this->key_union_type->canCastToUnionType($type->key_union_type)) {
                return false;
            }
            if (!$this->element_union_type->canCastToUnionType($type->element_union_type)) {
                return false;
            }
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }

    public function __toString() : string
    {
        $string = $this->element_union_type->__toString();
        if (!$this->key_union_type->isEmpty()) {
            $string = $this->key_union_type->__toString() . ',' . $string;
        }
        $string = "iterable<$string>";

        if ($this->getIsNullable()) {
            $string = '?' . $string;
        }

        return $string;
    }
}
// Trigger autoloader for subclass before make() can get called.
\class_exists(ArrayType::class);
