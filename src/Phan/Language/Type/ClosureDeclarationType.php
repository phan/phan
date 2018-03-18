<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Element\FunctionInterface;
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

    // computed properties

    /** @var int see FunctionTrait */
    private $required_param_count;

    /** @var int see FunctionTrait */
    private $optional_param_count;

    private $is_variadic;
    // end computed properties

    /**
     * @param array<int,ClosureDeclarationParameter> $params
     * @param UnionType $return_type
     */
    protected function __construct(array $params, UnionType $return_type, bool $returns_reference, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->params = $params;
        $this->return_type = $return_type;
        $this->returns_reference = $returns_reference;

        $required_param_count = 0;
        $optional_param_count = \count($params);
        ;
        // TODO: Warn about required after optional
        foreach ($params as $param) {
            if (!$param->isOptional()) {
                $required_param_count++;
            } elseif ($param->isVariadic()) {
                $this->is_variadic = true;
                $optional_param_count = FunctionInterface::INFINITE_PARAMETERS;
                break;
            }
        }
        $this->required_param_count = $required_param_count;
        $this->optional_param_count = $optional_param_count;
    }

    /**
     * @param array<int,ClosureDeclarationParameter> $params
     * @param UnionType $return_type
     * @param bool $returns_reference is this referring to a closure with a reference return value?
     * @param bool $is_nullable is this a nullable type?
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
        return $this->memoize(__FUNCTION__, function () {
            $parts = [];
            foreach ($this->params as $value) {
                $parts[] = $value->__toString();
            }
            $return_type = $this->return_type;
            $return_type_string = $return_type->__toString();
            if ($return_type->typeCount() >= 2) {
                $return_type_string = "($return_type_string)";
            }
            return ($this->is_nullable ? '?' : '') . 'Closure(' . \implode(',', $parts) . '):' . $return_type_string;
        });
    }

    public function __clone()
    {
        throw new \AssertionError('Should not clone ClosureTypeDeclaration');
    }

    /**
     * @return bool
     * True if this type is a callable or a Closure or a ClosureDeclarationType
     */
    public function isCallable() : bool
    {
        return true;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type) : bool
    {
        if ($type->isCallable()) {
            if ($this->getIsNullable()) {
                return false;
            }
            if ($type instanceof ClosureDeclarationType) {
                return $this->canCastToNonNullableClosureDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @return ?ClosureDeclarationParameter
     */
    public function getClosureParameterForArgument(int $i)
    {
        $result = $this->params[$i] ?? null;
        if (!$result) {
            return $this->is_variadic ? end($this->params) : null;
        }
        return $result;
    }

    public function canCastToNonNullableClosureDeclarationType(ClosureDeclarationType $type) : bool
    {
        if ($this->required_param_count > $type->required_param_count) {
            return false;
        }
        if ($this->optional_param_count < $type->optional_param_count) {
            return false;
        }
        if ($this->returns_reference !== $type->returns_reference) {
            return false;
        }
        // TODO: Allow nullable/null to cast to void?
        if (!$this->return_type->canCastToUnionType($type->return_type)) {
            return false;
        }
        foreach ($this->params as $i => $param) {
            $other_param = $type->getClosureParameterForArgument($i) ?? null;
            if (!$other_param) {
                break;
            }
            if (!$param->canCastToParameterIgnoringVariadic($other_param)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @override (Don't include \Closure in the expanded types. It interferes with type casting checking)
     */
    public function asExpandedTypes(
        CodeBase $unused_code_base,
        int $unused_recursion_depth = 0
    ) : UnionType {
        return $this->asUnionType();
    }
}
