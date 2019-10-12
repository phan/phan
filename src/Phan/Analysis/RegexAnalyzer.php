<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use InvalidArgumentException;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\UnionType;
use Phan\Library\RegexKeyExtractor;

/**
 * This infers the union type of $matches in preg_match,
 * including the number of potential groups if that can be inferred.
 *
 * @see PregRegexPlugin for the plugin that actually emits warnings about invalid regexes
 */
class RegexAnalyzer
{
    /**
     * Returns the union type of the matches output parameter in a call to `preg_match()`
     * with the nodes in $argument_list.
     *
     * @param list<Node|string|float|int> $argument_list
     */
    public static function getPregMatchUnionType(
        CodeBase $code_base,
        Context $context,
        array $argument_list
    ) : UnionType {
        static $string_array_type = null;
        static $string_type = null;
        static $array_type = null;
        static $shape_array_type = null;
        static $shape_array_inner_type = null;
        if ($string_array_type === null) {
            // Note: Patterns **can** have named subpatterns
            $string_array_type = UnionType::fromFullyQualifiedPHPDocString('string[]');
            $string_type       = UnionType::fromFullyQualifiedPHPDocString('string');
            $array_type        = UnionType::fromFullyQualifiedPHPDocString('array');
            $shape_array_type  = UnionType::fromFullyQualifiedPHPDocString('array{0:string,1:int}[]');
            $shape_array_inner_type  = UnionType::fromFullyQualifiedPHPDocString('array{0:string,1:int}');
        }
        $regex_node = $argument_list[0];
        $regex = $regex_node instanceof Node ? (new ContextNode($code_base, $context, $regex_node))->getEquivalentPHPScalarValue() : $regex_node;
        try {
            $regex_group_keys = RegexKeyExtractor::getKeys($regex);
        } catch (InvalidArgumentException $_) {
            $regex_group_keys = null;
        }
        if (\count($argument_list) > 3) {
            $offset_flags_node = $argument_list[3];
            $bit = (new ContextNode($code_base, $context, $offset_flags_node))->getEquivalentPHPScalarValue();
        } else {
            $bit = 0;
        }

        if (!\is_int($bit)) {
            return $array_type;
        }
        // TODO: Support PREG_UNMATCHED_AS_NULL
        if ($bit & \PREG_OFFSET_CAPTURE) {
            if (\is_array($regex_group_keys)) {
                return self::makeArrayShape($regex_group_keys, $shape_array_inner_type);
            }
            return $shape_array_type;
        }

        if (\is_array($regex_group_keys)) {
            return self::makeArrayShape($regex_group_keys, $string_type);
        }
        return $string_array_type;
    }

    /**
     * Returns the union type of the matches output parameter in a call to `preg_match_all()`
     * with the nodes in $argument_list.
     *
     * @param list<Node|string|float|int> $argument_list
     */
    public static function getPregMatchAllUnionType(
        CodeBase $code_base,
        Context $context,
        array $argument_list
    ) : UnionType {
        if (\count($argument_list) > 3) {
            $offset_flags_node = $argument_list[3];
            $bit = (new ContextNode($code_base, $context, $offset_flags_node))->getEquivalentPHPScalarValue();
        } else {
            $bit = 0;
        }

        if (!\is_int($bit)) {
            return UnionType::fromFullyQualifiedPHPDocString('array[]');
        }

        $shape_array_type = self::getPregMatchUnionType($code_base, $context, $argument_list);
        if ($bit & \PREG_SET_ORDER) {
            return $shape_array_type->asGenericArrayTypes(GenericArrayType::KEY_INT);
        }
        return $shape_array_type->withMappedElementTypes(static function (UnionType $type) : UnionType {
            return $type->elementTypesToGenericArray(GenericArrayType::KEY_INT);
        });
    }

    /**
     * @param associative-array<int|string,true> $regex_group_keys
     */
    private static function makeArrayShape(
        array $regex_group_keys,
        UnionType $type
    ) : UnionType {
        $field_types = \array_map(
            /** @param true $_ */
            static function (bool $_) use ($type) : UnionType {
                return $type;
            },
            $regex_group_keys
        );
        // NOTE: This is treated as not 100% guaranteed to be an array to avoid false positives about comparing to non-arrays
        return ArrayShapeType::fromFieldTypes($field_types, false)->asPHPDocUnionType();
    }
}
