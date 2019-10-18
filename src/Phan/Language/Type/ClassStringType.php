<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Phan\CodeBase;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * A type representing a string with an unknown value that is a fully qualified class name.
 *
 * Phan's representation for `class-string` and `class-string<T>`.
 * @phan-pure
 */
final class ClassStringType extends StringType
{
    /** @phan-override */
    const NAME = 'class-string';

    /** @override */
    public function isPossiblyNumeric() : bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec() : UnionType
    {
        return UnionType::fromFullyQualifiedPHPDocString('string');
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
     * Returns the class union type this class string represents, or the empty union type
     */
    public function getClassUnionType() : UnionType
    {
        $template_union_type = $this->template_parameter_type_list[0] ?? null;
        if (!$template_union_type) {
            return UnionType::empty();
        }
        return $template_union_type->makeFromFilter(static function (Type $type) : bool {
            return $type instanceof TemplateType || $type->isObjectWithKnownFQSEN();
        });
    }

    /**
     * @param CodeBase $code_base may be used for resolving inheritance @phan-unused-param
     * @param TemplateType $template_type the template type that this union type is being searched for
     *
     * @return ?Closure(UnionType):UnionType a closure to determine the union type(s) that are in the same position(s) as the template type.
     * This is overridden in subclasses.
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type) : ?Closure
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

    public function __toString() : string
    {
        $string = self::NAME;

        if (\count($this->template_parameter_type_list) > 0) {
            $string .= $this->templateParameterTypeListAsString();
        }

        if ($this->is_nullable) {
            $string = '?' . $string;
        }

        return $string;
    }

    public function canUseInRealSignature() : bool
    {
        return false;
    }

    public function withIsNullable(bool $is_nullable) : Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }
        // make() will throw if the namespace is the empty string
        return static::make(
            '\\',
            $this->name,
            $this->template_parameter_type_list,
            $is_nullable,
            Type::FROM_TYPE
        );
    }
}
