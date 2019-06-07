<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Exception;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ResourceType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use ReflectionMethod;
use function count;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Refactor this.
 *
 * TODO: Support real types (e.g. array_values() if the passed in real union type is an array, otherwise real type is ?array
 *
 * @phan-file-suppress PhanUnusedClosureParameter
 */
final class RedundantConditionCallPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability
{
    private const _IS_IMPOSSIBLE = 1;
    private const _IS_REDUNDANT = 2;
    private const _IS_REASONABLE_CONDITION = 3;

    /**
     * @return array<string,\Closure>
     */
    private static function getAnalyzeFunctionCallClosuresStatic() : array
    {
        /**
         * @param Closure(UnionType):int $checker returns _IS_IMPOSSIBLE/_IS_REDUNDANT/_IS_REASONABLE_CONDITION
         * @param string $expected_type
         * @return Closure(CodeBase, Context, FunctionInterface, array<int,mixed>):void
         */
        $make_first_arg_checker = static function (Closure $checker, string $expected_type) : Closure {
            /**
             * @param array<int,Node|int|float|string> $args
             */
            return static function (CodeBase $code_base, Context $context, FunctionInterface $unused_function, array $args) use ($checker, $expected_type) : void {
                if (count($args) < 1) {
                    return;
                }
                try {
                    $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0], false);
                } catch (Exception $_) {
                    return;
                }
                if (!$union_type->hasRealTypeSet()) {
                    return;
                }
                $result = $checker($union_type->getRealUnionType());
                if ($result === null) {
                    return;
                }
                if ($result === self::_IS_REDUNDANT) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeRedundantCondition,
                        $context->getLineNumberStart(),
                        ASTReverter::toShortString($args[0]),
                        $union_type->getRealUnionType(),
                        $expected_type
                    );
                } elseif ($result === self::_IS_IMPOSSIBLE)  {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeImpossibleCondition,
                        $context->getLineNumberStart(),
                        ASTReverter::toShortString($args[0]),
                        $union_type->getRealUnionType(),
                        $expected_type
                    );
                }
            };
        };
        $make_simple_first_arg_checker = static function (string $extract_types_method, string $expected_type) use ($make_first_arg_checker) : Closure {
            $method = new ReflectionMethod(UnionType::class, $extract_types_method);
            return $make_first_arg_checker(static function (UnionType $type) use ($method) : int {
                $new_real_type = $method->invoke($type)->nonNullableClone();
                if ($new_real_type->isEmpty()) {
                    return self::_IS_IMPOSSIBLE;
                }
                if ($new_real_type->isEqualTo($type)) {
                    return self::_IS_REDUNDANT;
                }
                return self::_IS_REASONABLE_CONDITION;
            }, $expected_type);
        };
        $resource_callback = $make_first_arg_checker(static function (UnionType $type) : int {
            $new_real_type = $type->makeFromFilter(static function (Type $type) : bool {
                return $type instanceof ResourceType;
            })->nonNullableClone();
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            if ($new_real_type->isEqualTo($type)) {
                return self::_IS_REDUNDANT;
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'resource');
        $null_callback = $make_first_arg_checker(static function (UnionType $type) : int {
            if (!$type->containsNullable()) {
                return self::_IS_IMPOSSIBLE;
            }
            if ($type->hasTypeMatchingCallback(function (Type $type) : bool {
                return !($type instanceof NullType || $type instanceof VoidType);
            })) {
                return self::_IS_REASONABLE_CONDITION;
            }
            return self::_IS_REDUNDANT;
        }, 'null');
        $numeric_callback = $make_first_arg_checker(static function (UnionType $union_type) : int {
            $has_non_numeric = false;
            $has_numeric = false;
            foreach ($union_type->getTypeSet() as $type) {
                if ($type->isNullable()) {
                    $has_non_numeric = true;
                }
                if ($type instanceof IntType || $type instanceof FloatType) {
                    $has_numeric = true;
                } elseif ($type->isPossiblyNumeric()) {
                    return self::_IS_REASONABLE_CONDITION;
                } else {
                    $has_non_numeric = true;
                }
            }
            if ($has_numeric) {
                if ($has_non_numeric) {
                    return self::_IS_REASONABLE_CONDITION;
                }
                return self::_IS_REDUNDANT;
            }
            return self::_IS_IMPOSSIBLE;
        }, 'numeric');

        $int_callback = $make_simple_first_arg_checker('intTypes', 'int');
        $callable_callback = $make_simple_first_arg_checker('callableTypes', 'callable');
        $bool_callback = $make_simple_first_arg_checker('getTypesInBoolFamily', 'bool');
        $float_callback = $make_simple_first_arg_checker('floatTypes', 'float');
        $string_callback = $make_simple_first_arg_checker('stringTypes', 'string');

        // TODO: Implement checks for the commented out conditions.
        // TODO: Check intval, boolval, etc.
        return [
            // 'is_a' => $is_a_callback,
            // 'is_array' => $array_callback,
            'is_bool' => $bool_callback,
            // TODO: Don't emit false positives for arrays/strings
            // 'is_callable' => $callable_callback,
            'is_double' => $float_callback,
            'is_float' => $float_callback,
            'is_int' => $int_callback,
            'is_integer' => $int_callback,
            // 'is_iterable' => $make_basic_assertion_callback('iterable'),  // TODO: Could keep basic array types and classes extending iterable
            'is_long' => $int_callback,
            'is_null' => $null_callback,
            'is_numeric' => $numeric_callback,
            // 'is_object' => $object_callback,
            'is_real' => $float_callback,
            'is_resource' => $resource_callback,
            // TODO: Don't warn about CallableType, which can be string/scalar
            // 'is_scalar' => $scalar_callback,
            'is_string' => $string_callback,
            // 'empty' => $null_callback,
        ];
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $overrides;
    }
}
