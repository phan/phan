<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Phan\CodeBase;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation for `class-string` and `class-string<T>`
 */
final class ClassStringType extends StringType
{
    /** @phan-override */
    const NAME = 'class-string';

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec() : UnionType
    {
        return UnionType::fromFullyQualifiedString('string');
    }

    public function hasTemplateTypeRecursive() : bool
    {
        $template_union_type = $this->template_parameter_type_list[0] ?? null;
        if (!$template_union_type) {
            return false;
        }
        foreach ($template_union_type->getTypeSet() as $type) {
            if ($type instanceof TemplateType) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param CodeBase $code_base may be used for resolving inheritance @phan-unused-param
     * @param TemplateType $template_type the template type that this union type is being searched for
     *
     * @return ?Closure(UnionType):UnionType a closure to determine the union type(s) that are in the same position(s) as the template type.
     * This is overridden in subclasses.
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type)
    {
        $template_union_type = $this->template_parameter_type_list[0] ?? null;
        if (!$template_union_type) {
            return null;
        }
        if (!$template_union_type->isType($template_type)) {
            return null;
        }
        return static function (UnionType $type) : UnionType {
            $result = UnionType::empty();
            foreach ($type->asStringScalarValues() as $string) {
                // Convert string arguments to the classes they represent
                try {
                    $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($string);
                } catch (\Exception $_) {
                    continue;
                }
                // Include the type, which may or may not be undefined
                $result = $result->withType($fqsen->asType());
            }
            return $result;
        };
    }

    /**
     * Replace the resolved reference to class T (possibly namespaced) with a regular template type.
     *
     * @param array<string,TemplateType> $template_fix_map maps the incorrectly resolved name to the template type
     * @return Type
     *
     * @see UnionType::withTemplateParameterTypeMap() for the opposite
     */
    public function withConvertTypesToTemplateTypes(array $template_fix_map) : Type
    {
        $template_union_type = $this->template_parameter_type_list[0] ?? null;
        if (!$template_union_type) {
            return $this;
        }
        $new_type = $template_union_type->withConvertTypesToTemplateTypes($template_fix_map);
        if ($new_type === $template_union_type) {
            return $this;
        }
        return new self(
            '',
            'class-string',
            [$new_type],
            $this->is_nullable
        );
    }
}
