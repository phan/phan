<?php

declare(strict_types=1);

namespace Phan\Language\Template;

use Phan\CodeBase;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\GenericIterableType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * Finds the uses of template types in a type that violate the variance the template was declared with.
 *
 * A type is used in a position: a return type is a covariant position (the class produces the value),
 * a parameter is a contravariant position (the class consumes the value) and a property that can be written
 * is an invariant position (both).
 *
 * The position changes inside of a type:
 * - The parameter of a callable reverses it: the `T` in `@param callable(T):void $f` is produced by the class.
 * - The template parameter of a class that is declared contravariant reverses it too.
 * - Everything else keeps it: the return type of a callable, the elements of arrays and iterables (which are values in php),
 *   and the template parameters of other classes. (Phan treats a template without a declared variance as covariant.)
 *
 * A covariant template can only be used in covariant positions, and a contravariant template only in contravariant positions.
 */
final class TemplateVarianceUtil
{
    /** The value is produced: a return type, or a property that is only read */
    public const POSITION_COVARIANT = 1;
    /** The value is consumed: a parameter */
    public const POSITION_CONTRAVARIANT = -1;
    /** The value is produced and consumed: a property that can be written */
    public const POSITION_INVARIANT = 0;

    /**
     * Collects the uses of templates in $union_type that violate the declared variance of the template.
     *
     * @param array<string,TemplateType> $template_map the templates in scope
     * @param int $position the position $union_type is used in: one of the POSITION_* constants
     * @return array<string,array{template:TemplateType,context:?string}> maps the name of a template to its first violation.
     * 'context' describes where in $union_type the template is used, or is null if it is used directly.
     */
    public static function collectVarianceViolations(CodeBase $code_base, UnionType $union_type, array $template_map, int $position): array
    {
        $result = [];
        if ($union_type->isEmpty() || !$template_map) {
            return $result;
        }
        self::collectFromUnion($code_base, $union_type, $template_map, $result, $position, null, 0);
        return $result;
    }

    /**
     * @param array<string,TemplateType> $template_map
     * @param array<string,array{template:TemplateType,context:?string}> $result
     */
    private static function collectFromUnion(
        CodeBase $code_base,
        UnionType $union_type,
        array $template_map,
        array &$result,
        int $position,
        ?string $context,
        int $depth
    ): void {
        if ($depth > 10) {
            return;
        }
        foreach ($union_type->getTypeSet() as $type) {
            self::collectFromType($code_base, $type, $template_map, $result, $position, $context, $depth);
        }
    }

    /**
     * @param array<string,TemplateType> $template_map
     * @param array<string,array{template:TemplateType,context:?string}> $result
     */
    private static function collectFromType(
        CodeBase $code_base,
        Type $type,
        array $template_map,
        array &$result,
        int $position,
        ?string $context,
        int $depth
    ): void {
        if ($type instanceof TemplateType) {
            $name = $type->getName();
            $template = $template_map[$name] ?? null;
            if (!$template || isset($result[$name])) {
                return;
            }
            if (($template->isCovariant() && $position !== self::POSITION_COVARIANT) ||
                ($template->isContravariant() && $position !== self::POSITION_CONTRAVARIANT)) {
                $result[$name] = ['template' => $template, 'context' => $context];
            }
            return;
        }

        if ($type instanceof GenericArrayType) {
            self::collectFromUnion($code_base, $type->genericArrayElementUnionType(), $template_map, $result, $position, $context ?? 'array element', $depth + 1);
            return;
        }

        if ($type instanceof ArrayShapeType) {
            foreach ($type->getFieldTypes() as $field_type) {
                self::collectFromUnion($code_base, $field_type, $template_map, $result, $position, $context ?? 'array shape field', $depth + 1);
            }
            return;
        }

        if ($type instanceof FunctionLikeDeclarationType) {
            $return_type = $type->getPHPDocReturnType();
            if ($return_type) {
                self::collectFromUnion($code_base, $return_type, $template_map, $result, $position, $context ?? 'callable return type', $depth + 1);
            }
            foreach ($type->getParameterList() as $parameter) {
                if (!$parameter instanceof Parameter) {
                    continue;
                }
                // The parameter of a callable reverses the position.
                self::collectFromUnion(
                    $code_base,
                    $parameter->getUnionType(),
                    $template_map,
                    $result,
                    -$position,
                    $context ?? ('callable parameter $' . $parameter->getName()),
                    $depth + 1
                );
            }
            return;
        }

        if ($type instanceof GenericIterableType) {
            self::collectFromUnion($code_base, $type->getKeyUnionType(), $template_map, $result, $position, $context ?? 'iterable key', $depth + 1);
            self::collectFromUnion($code_base, $type->getElementUnionType(), $template_map, $result, $position, $context ?? 'iterable element', $depth + 1);
            return;
        }

        $template_parameter_type_list = $type->getTemplateParameterTypeList();
        if (!$template_parameter_type_list) {
            return;
        }
        $class_templates = self::getTemplatesOfClass($code_base, $type);
        foreach ($template_parameter_type_list as $i => $inner_union_type) {
            $class_template = $class_templates[$i] ?? null;
            // The template parameter of a class that is declared contravariant reverses the position: `Consumer<T>`
            $inner_position = $class_template && $class_template->isContravariant() ? -$position : $position;
            self::collectFromUnion($code_base, $inner_union_type, $template_map, $result, $inner_position, $context, $depth + 1);
        }
    }

    /**
     * @return list<TemplateType> the templates that the class of $type declares, or an empty list if the class is not known
     */
    private static function getTemplatesOfClass(CodeBase $code_base, Type $type): array
    {
        if (!$type->isObjectWithKnownFQSEN()) {
            return [];
        }
        try {
            $fqsen = FullyQualifiedClassName::fromType($type);
        } catch (\Exception) {
            return [];
        }
        if (!$code_base->hasClassWithFQSEN($fqsen)) {
            return [];
        }
        $templates = [];
        foreach ($code_base->getClassByFQSEN($fqsen)->getTemplateTypeMap() as $template) {
            if ($template instanceof TemplateType) {
                $templates[] = $template;
            }
        }
        return $templates;
    }
}
