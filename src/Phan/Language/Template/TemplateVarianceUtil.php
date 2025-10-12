<?php

declare(strict_types=1);

namespace Phan\Language\Template;

use Phan\Language\Element\Parameter;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\GenericIterableType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * Utility helpers for tracking template variance usages within complex type structures.
 */
final class TemplateVarianceUtil
{
    /**
     * Collects template parameter usages within the given union type for variance validation.
     *
     * @param array<string,TemplateType> $template_map canonical template definitions in scope
     * @return array<string,array{template:TemplateType,is_invariant:bool,context:?string}>
     */
    public static function collectTemplateUsagesForVariance(UnionType $union_type, array $template_map): array
    {
        $result = [];
        if ($union_type->isEmpty() || !$template_map) {
            return $result;
        }

        self::collectFromUnion($union_type, $template_map, $result, false, null);
        return $result;
    }

    /**
     * @param array<string,TemplateType> $template_map
     * @param array<string,array{template:TemplateType,is_invariant:bool,context:?string}> $result
     */
    private static function collectFromUnion(
        UnionType $union_type,
        array $template_map,
        array &$result,
        bool $is_invariant,
        ?string $context
    ): void {
        if ($union_type->isEmpty()) {
            return;
        }
        foreach ($union_type->getTypeSet() as $type) {
            self::collectFromType($type, $template_map, $result, $is_invariant, $context);
        }
    }

    /**
     * @param array<string,TemplateType> $template_map
     * @param array<string,array{template:TemplateType,is_invariant:bool,context:?string}> $result
     */
    private static function collectFromType(
        Type $type,
        array $template_map,
        array &$result,
        bool $is_invariant,
        ?string $context
    ): void {
        if ($type instanceof TemplateType) {
            $name = $type->getName();
            if (!isset($template_map[$name])) {
                return;
            }
            $existing = $result[$name] ?? null;
            if (!$existing) {
                $result[$name] = [
                    'template' => $template_map[$name],
                    'is_invariant' => $is_invariant,
                    'context' => $is_invariant ? $context : null,
                ];
                return;
            }
            if (!$existing['is_invariant'] && $is_invariant) {
                $existing['is_invariant'] = true;
                $existing['context'] = $context;
                $result[$name] = $existing;
            }
            return;
        }

        if ($type instanceof GenericArrayType) {
            self::collectFromUnion($type->genericArrayElementUnionType(), $template_map, $result, true, 'array element');
            return;
        }

        if ($type instanceof ArrayShapeType) {
            foreach ($type->getFieldTypes() as $field_type) {
                self::collectFromUnion($field_type, $template_map, $result, true, 'array shape field');
            }
            return;
        }

        if ($type instanceof FunctionLikeDeclarationType) {
            $return_type = $type->getPHPDocReturnType();
            if ($return_type) {
                self::collectFromUnion($return_type, $template_map, $result, true, 'callable return type');
            }
            foreach ($type->getParameterList() as $parameter) {
                if (!$parameter instanceof Parameter) {
                    continue;
                }
                $param_union = $parameter->getUnionType();
                if ($param_union->isEmpty()) {
                    continue;
                }
                $param_context = 'callable parameter $' . $parameter->getName();
                self::collectFromUnion($param_union, $template_map, $result, $is_invariant, $param_context);
            }
            return;
        }

        if ($type instanceof GenericIterableType) {
            self::collectFromUnion($type->getKeyUnionType(), $template_map, $result, true, 'iterable key');
            self::collectFromUnion($type->getElementUnionType(), $template_map, $result, true, 'iterable element');
            return;
        }

        foreach ($type->getTemplateParameterTypeList() as $inner_union_type) {
            self::collectFromUnion($inner_union_type, $template_map, $result, $is_invariant, $context);
        }
    }
}
