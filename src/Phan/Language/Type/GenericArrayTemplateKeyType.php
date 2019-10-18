<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * A generic array type with a template as the key
 * @phan-pure
 */
class GenericArrayTemplateKeyType extends GenericArrayType
{
    /**
     * @var UnionType 1 or more template types
     */
    private $template_key_type;

    protected function __construct(Type $type, bool $is_nullable, UnionType $template_key_type)
    {
        parent::__construct($type, $is_nullable, GenericArrayType::KEY_MIXED);
        $this->template_key_type = $template_key_type;
    }

    /**
     * Generate a GenericArrayTemplateKeyType for an element type and a template type
     */
    public static function fromTemplateAndElementType(
        Type $type,
        bool $is_nullable,
        UnionType $key_type
    ) : GenericArrayTemplateKeyType {
        return new self($type, $is_nullable, $key_type);
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ) : UnionType {
        $element_type = $this->genericArrayElementUnionType();
        $new_element_type = $element_type->withTemplateParameterTypeMap($template_parameter_type_map);
        $new_key_type = $this->template_key_type->withTemplateParameterTypeMap($template_parameter_type_map);
        if ($element_type === $new_element_type && $new_key_type === $this->template_key_type) {
            return $this->asPHPDocUnionType();
        }
        if ($this->template_key_type !== $new_key_type) {
            if ($new_element_type->isEmpty()) {
                $new_element_type = MixedType::instance(false)->asPHPDocUnionType();
            }
            return $new_element_type->asGenericArrayTypes(
                GenericArrayType::keyTypeFromUnionTypeValues($new_key_type)
            )->withIsNullable($this->is_nullable);
        }
        return $new_element_type->asMappedUnionType(function (Type $type) : Type {
            return self::fromTemplateAndElementType($type, $this->is_nullable, $this->template_key_type);
        });
    }

    public function hasTemplateTypeRecursive() : bool
    {
        return true;
    }

    /**
     * If this generic array type in a parameter declaration has template types,
     * get the closure to extract the real types for that template type from argument union types
     *
     * @param CodeBase $code_base
     * @return ?Closure(UnionType, Context):UnionType
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type) : ?Closure
    {
        $element_closure = parent::getTemplateTypeExtractorClosure($code_base, $template_type);
        $key_closure = $this->template_key_type->getTemplateTypeExtractorClosure($code_base, $template_type);
        return TemplateType::combineParameterClosures($key_closure, $element_closure);
    }
}
