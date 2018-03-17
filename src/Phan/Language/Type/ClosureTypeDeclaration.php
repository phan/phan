<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

final class ClosureTypeDeclaration extends Type
{
    /** Not an override */
    const NAME = 'Closure';

    /** @var array<int,UnionType> */
    private $param_types;

    /** @var UnionType */
    private $return_type;

    /**
     * @param array<int,UnionType> $param_types
     * @param UnionType $return_type
     */
    protected function __construct(array $param_types, UnionType $return_type, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->param_types = $param_types;
        $this->return_type = $return_type;
    }

    /**
     * @param array<int,UnionType> $param_types
     * @param UnionType $return_type
     */
    public static function instanceForTypes(array $param_types, UnionType $return_type, bool $is_nullable)
    {
        static $cache = [];
        $key_parts = [];
        if ($is_nullable) {
            $key_parts[] = '?';
        }
        foreach ($param_types as $type) {
            $key_parts[] = $type->generateUniqueId();
        }
        $key_parts[] = $return_type->generateUniqueId();
        $key = \implode(',', $key_parts);

        return $cache[$key] ?? ($cache[$key] = new self($param_types, $return_type, $is_nullable));
    }

    public function __toString() : string
    {
        $parts = [];
        // TODO: CommentParameter instead of Parameter
        foreach ($this->param_types as $key => $value) {
            $value_repr = $value->__toString();
            $parts[] = $value_repr;
        }
        $return_type_string = $this->return_type->__toString();
        return ($this->is_nullable ? '?' : '') . 'Closure(' . \implode(',', $parts) . '):' . $return_type_string;
    }

    public function __clone()
    {
        throw new \AssertionError('Should not clone ClosureTypeDeclaration');
    }

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     */
    public function isCallable() : bool
    {
        return true;
    }
}
