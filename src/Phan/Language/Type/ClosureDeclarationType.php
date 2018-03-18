<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

final class ClosureDeclarationType extends Type
{
    /** Not an override */
    const NAME = 'Closure';

    /** @var array<int,ClosureDeclarationParameter> */
    private $params;

    /** @var UnionType */
    private $return_type;

    /** @var bool */
    private $returns_reference;

    /**
     * @param array<int,ClosureDeclarationParameter> $param_types
     * @param UnionType $return_type
     */
    protected function __construct(array $param_types, UnionType $return_type, bool $returns_reference, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->params = $param_types;
        $this->return_type = $return_type;
        $this->returns_reference = $returns_reference;
    }

    /**
     * @param array<int,ClosureDeclarationParameter> $params
     * @param UnionType $return_type
     */
    public static function instanceForTypes(array $params, UnionType $return_type, bool $returns_reference, bool $is_nullable)
    {
        static $cache = [];
        $key_parts = [];
        if ($is_nullable) {
            $key_parts[] = '?';
        }
        if ($returns_reference) {
            $key_parts[] = '&';
        }
        foreach ($params as $param_info) {
            $key_parts[] = $param_info->generateUniqueId();
        }
        $key_parts[] = $return_type->generateUniqueId();
        $key = json_encode($key_parts);

        return $cache[$key] ?? ($cache[$key] = new self($params, $return_type, $returns_reference, $is_nullable));
    }

    /**
     * Used when serializing this type in union types.
     * @return string (e.g. "Closure(int,string&...):string[]")
     */
    public function __toString() : string
    {
        $parts = [];
        foreach ($this->params as $value) {
            $parts[] = $value->__toString();
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
