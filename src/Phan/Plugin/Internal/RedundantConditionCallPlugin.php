<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Exception;
use Phan\Analysis\RedundantCondition;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ClassStringType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\ResourceType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use ReflectionMethod;

use function count;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Support real types (e.g. array_values() if the passed in real union type is an array, otherwise real type is ?array
 */
final class RedundantConditionCallPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability,
    PostAnalyzeNodeCapability
{
    private const _IS_IMPOSSIBLE = 1;
    private const _IS_REDUNDANT = 2;
    private const _IS_REASONABLE_CONDITION = 3;

    /**
     * @return array<string,\Closure>
     */
    private static function getAnalyzeFunctionCallClosuresStatic(): array
    {
        /**
         * @param Closure(UnionType):int $checker returns _IS_IMPOSSIBLE/_IS_REDUNDANT/_IS_REASONABLE_CONDITION
         * @param string $expected_type
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>, ?Node):void
         */
        $make_first_arg_checker = static function (Closure $checker, string $expected_type, bool $fail_early_on_non_empty_mixed = true): Closure {
            /**
             * @param list<Node|int|float|string> $args
             */
            return static function (CodeBase $code_base, Context $context, FunctionInterface $unused_function, array $args, ?Node $_) use ($checker, $expected_type, $fail_early_on_non_empty_mixed): void {
                if (count($args) < 1) {
                    return;
                }
                $arg = $args[0];
                try {
                    $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg, false);
                } catch (Exception $_) {
                    return;
                }
                if (!$union_type->hasRealTypeSet()) {
                    return;
                }
                $real_union_type = $union_type->getRealUnionType()->withStaticResolvedInContext($context);
                if ($fail_early_on_non_empty_mixed ? $real_union_type->hasMixedOrNonEmptyMixedType() : $real_union_type->hasMixedTypeStrict()) {
                    return;
                }
                $result = $checker($real_union_type);
                if ($result === null) {
                    return;
                }
                if ($result === self::_IS_REDUNDANT) {
                    RedundantCondition::emitInstance(
                        $arg,
                        $code_base,
                        $context,
                        Issue::RedundantCondition,
                        [
                            ASTReverter::toShortString($arg),
                            $union_type->getRealUnionType(),
                            $expected_type,
                        ],
                        static function (UnionType $type) use ($checker): bool {
                            return $checker($type) === self::_IS_REDUNDANT;
                        }
                    );
                } elseif ($result === self::_IS_IMPOSSIBLE) {
                    RedundantCondition::emitInstance(
                        $arg,
                        $code_base,
                        $context,
                        Issue::ImpossibleCondition,
                        [
                            ASTReverter::toShortString($arg),
                            $union_type->getRealUnionType(),
                            $expected_type,
                        ],
                        static function (UnionType $type) use ($checker): bool {
                            return $checker($type) === self::_IS_IMPOSSIBLE;
                        }
                    );
                }
            };
        };
        /**
         * @param Closure(UnionType, CodeBase):int $checker returns _IS_IMPOSSIBLE/_IS_REDUNDANT/_IS_REASONABLE_CONDITION
         * @param string $expected_type
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>, ?Node):void
         */
        $make_codebase_aware_first_arg_checker = static function (Closure $checker, string $expected_type) use ($make_first_arg_checker): Closure {
            /**
             * @param list<Node|int|float|string> $args
             */
            return static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $node) use ($checker, $expected_type, $make_first_arg_checker): void {
                $single_checker = static function (UnionType $type) use ($checker, $code_base): int {
                    return $checker($type, $code_base);
                };
                $arg_checker = $make_first_arg_checker($single_checker, $expected_type);
                $arg_checker($code_base, $context, $function, $args, $node);
            };
        };
        /**
         * @param Closure(UnionType, CodeBase, Context):int $checker returns _IS_IMPOSSIBLE/_IS_REDUNDANT/_IS_REASONABLE_CONDITION
         * @param string $expected_type
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>, ?Node):void
         */
        $make_context_aware_first_arg_checker = static function (Closure $checker, string $expected_type) use ($make_first_arg_checker): Closure {
            /**
             * @param list<Node|int|float|string> $args
             */
            return static function (CodeBase $code_base, Context $context, FunctionInterface $function, array $args, ?Node $node) use ($checker, $expected_type, $make_first_arg_checker): void {
                $single_checker = static function (UnionType $type) use ($checker, $code_base, $context): int {
                    return $checker($type, $code_base, $context);
                };
                $arg_checker = $make_first_arg_checker($single_checker, $expected_type);
                $arg_checker($code_base, $context, $function, $args, $node);
            };
        };

        $make_simple_first_arg_checker = static function (string $extract_types_method, string $expected_type) use ($make_first_arg_checker): Closure {
            $method = new ReflectionMethod(UnionType::class, $extract_types_method);
            /** @suppress PhanPluginUnknownObjectMethodCall ReflectionMethod cannot be analyzed */
            return $make_first_arg_checker(static function (UnionType $type) use ($method): int {
                $new_real_type = $method->invoke($type);
                if ($new_real_type->isEmpty()) {
                    return self::_IS_IMPOSSIBLE;
                }
                $new_real_type = $new_real_type->nonNullableClone();
                if ($new_real_type->isEqualTo($type)) {
                    return self::_IS_REDUNDANT;
                }
                return self::_IS_REASONABLE_CONDITION;
            }, $expected_type);
        };
        $resource_callback = $make_first_arg_checker(static function (UnionType $type): int {
            $new_real_type = $type->makeFromFilter(static function (Type $type): bool {
                return $type instanceof ResourceType;
            });
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            $new_real_type = $new_real_type->nonNullableClone();
            if ($new_real_type->isEqualTo($type)) {
                return self::_IS_REDUNDANT;
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'resource');
        $null_callback = $make_first_arg_checker(static function (UnionType $type): int {
            if (!$type->containsNullableOrUndefined()) {
                return self::_IS_IMPOSSIBLE;
            }
            if ($type->isNull()) {
                return self::_IS_REDUNDANT;
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'null', false);
        $numeric_callback = $make_first_arg_checker(static function (UnionType $union_type): int {
            $has_non_numeric = false;
            $has_numeric = false;
            foreach ($union_type->getTypeSet() as $type) {
                if ($type->isNullable()) {
                    $has_non_numeric = true;
                }
                if ($type instanceof IntType || $type instanceof FloatType) {
                    $has_numeric = true;
                } elseif ($type->isPossiblyNumeric()) {
                    if ($type instanceof LiteralStringType) {
                        $has_numeric = true;
                        continue;
                    }
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

        /**
         * @param Closure(UnionType):bool $condition
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>, ?Node):void
         */
        $make_cast_callback = static function (Closure $condition, string $expected_type) use ($make_first_arg_checker): Closure {
            return $make_first_arg_checker(static function (UnionType $union_type) use ($condition): int {
                if (!$union_type->containsNullableOrUndefined() && $condition($union_type)) {
                    return self::_IS_REDUNDANT;
                }
                return self::_IS_REASONABLE_CONDITION;
            }, $expected_type);
        };
        $callable_callback = $make_codebase_aware_first_arg_checker(static function (UnionType $type, CodeBase $code_base): int {
            $new_real_type = $type->callableTypes($code_base);
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            $new_real_type = $new_real_type->nonNullableClone();
            if ($new_real_type->isEqualTo($type)) {
                if (!$new_real_type->hasTypeMatchingCallback(static function (Type $type): bool {
                    return $type instanceof ArrayShapeType;
                })
                ) {
                    return self::_IS_REDUNDANT;
                }
                // is_callable([$obj, 'someFn') is a reasonable condition, fall through.
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'callable');
        $scalar_callback = $make_first_arg_checker(static function (UnionType $type): int {
            $new_real_type = $type->scalarTypesStrict(true);
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            if ($new_real_type->isEqualTo($type)) {
                if (!$new_real_type->hasTypeMatchingCallback(static function (Type $type): bool {
                    return $type instanceof ArrayShapeType;
                })
                ) {
                    return self::_IS_REDUNDANT;
                }
                // is_callable([$obj, 'someFn') is a reasonable condition, fall through.
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'scalar');
        $class_exists_callback = $make_first_arg_checker(static function (UnionType $type): int {
            if ($type->isType(ClassStringType::instance(false))) {
                return self::_IS_REDUNDANT;
            }
            $new_real_type = $type->classStringTypes();
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'class-string');

        $intval_callback = $make_cast_callback(static function (UnionType $union_type): bool {
            return $union_type->intTypes()->isEqualTo($union_type);
        }, 'int');
        $boolval_callback = $make_cast_callback(static function (UnionType $union_type): bool {
            return $union_type->isExclusivelyBoolTypes();
        }, 'bool');
        $doubleval_callback = $make_cast_callback(static function (UnionType $union_type): bool {
            return $union_type->floatTypes()->isEqualTo($union_type);
        }, 'float');
        $strval_callback = $make_cast_callback(static function (UnionType $union_type): bool {
            return $union_type->isExclusivelyStringTypes();
        }, 'string');

        $int_callback = $make_simple_first_arg_checker('intTypes', 'int');
        $bool_callback = $make_simple_first_arg_checker('getTypesInBoolFamily', 'bool');
        $float_callback = $make_simple_first_arg_checker('floatTypes', 'float');
        $iterable_callback = $make_codebase_aware_first_arg_checker(static function (UnionType $union_type, CodeBase $code_base): int {
            $new_real_type = $union_type->iterableTypesStrictCastAssumeTraversable($code_base);
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            if ($new_real_type->isEqualTo($union_type)) {
                return self::_IS_REDUNDANT;
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'iterable');
        /** @suppress PhanAccessMethodInternal */
        $countable_callback = $make_context_aware_first_arg_checker(static function (UnionType $union_type, CodeBase $code_base, Context $context): int {
            $new_real_type = UnionType::of(
                UnionType::castTypeListToCountable($code_base, $union_type->getTypeSet(), $context),
                []
            );
            if ($new_real_type->isEmpty()) {
                return self::_IS_IMPOSSIBLE;
            }
            if ($new_real_type->isEqualTo($union_type)) {
                return self::_IS_REDUNDANT;
            }
            return self::_IS_REASONABLE_CONDITION;
        }, 'countable');
        $object_callback = $make_simple_first_arg_checker('objectTypesStrictAllowEmpty', 'object');
        $array_callback = $make_simple_first_arg_checker('arrayTypesStrictCastAllowEmpty', 'array');
        $array_is_list_callback = $make_simple_first_arg_checker('listTypesStrictCastAllowEmpty', 'list');
        $string_callback = $make_simple_first_arg_checker('stringTypes', 'string');

        // TODO: Implement checks for the commented out conditions.
        // TODO: Check intval, boolval, etc.
        return [
            // 'is_a' => $is_a_callback,
            'is_array' => $array_callback,
            'is_bool' => $bool_callback,
            'is_callable' => $callable_callback,
            'is_countable' => $countable_callback,
            'is_double' => $float_callback,
            'is_float' => $float_callback,
            'is_int' => $int_callback,
            'is_integer' => $int_callback,
            'is_iterable' => $iterable_callback,  // TODO: Could keep basic array types and classes extending iterable
            'is_long' => $int_callback,
            'is_null' => $null_callback,
            'is_numeric' => $numeric_callback,
            'is_object' => $object_callback,
            'is_real' => $float_callback,
            'is_resource' => $resource_callback,
            'is_scalar' => $scalar_callback,
            'is_string' => $string_callback,

            'array_is_list' => $array_is_list_callback,
            'class_exists' => $class_exists_callback,
            'intval' => $intval_callback,
            'boolval' => $boolval_callback,
            'floatval' => $doubleval_callback,
            'doubleval' => $doubleval_callback,
            'strval' => $strval_callback,
        ];
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     * @override
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $overrides;
    }

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return RedundantConditionVisitor::class;
    }
}
