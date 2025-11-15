<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\flags;
use ast\Node;
use Closure;
use Phan\Analysis\ArgumentType;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\Analysis\RedundantCondition;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\AssociativeArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\ListType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\NonEmptyListType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;
use Phan\PluginV3\PreAnalyzeNodeCapability;
use Phan\PluginV3\ReturnTypeOverrideCapability;

use function array_keys;
use function count;
use function is_string;
use function strcasecmp;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Refactor this.
 *
 * TODO: Support real types (e.g. array_values() if the passed in real union type is an array, otherwise real type is ?array
 *
 * @phan-file-suppress PhanUnusedClosureParameter
 */
final class ArrayReturnTypeOverridePlugin extends PluginV3 implements
    ReturnTypeOverrideCapability,
    PreAnalyzeNodeCapability
{

    /**
     * @return class-string<PluginAwarePreAnalysisVisitor>
     */
    public static function getPreAnalyzeNodeVisitorClassName(): string
    {
        return ArrayReturnTypeOverridePreAnalysisVisitor::class;
    }

    /**
     * @return array<string,\Closure>
     */
    private static function getReturnTypeOverridesStatic(): array
    {
        $mixed_type  = MixedType::instance(false);
        $false_type  = FalseType::instance(false);
        $array_type  = ArrayType::instance(false);
        $null_type   = NullType::instance(false);
        $nullable_array_type_set = [ArrayType::instance(true)];
        $nullable_list_type_set = [ListType::fromElementType(MixedType::instance(true), true)];
        $int_or_string_or_false = UnionType::fromFullyQualifiedRealString('int|string|false');
        $int_or_string_or_null = UnionType::fromFullyQualifiedRealString('int|string|null');
        $int_or_string = UnionType::fromFullyQualifiedRealString('int|string');
        $real_array = UnionType::fromFullyQualifiedRealString('array');
        $probably_real_array = UnionType::fromFullyQualifiedPHPDocAndRealString('array', '?array');
        $probably_real_assoc_array = UnionType::fromFullyQualifiedPHPDocAndRealString('associative-array', '?associative-array');
        $probably_real_assoc_array_falsey = UnionType::fromFullyQualifiedPHPDocAndRealString('associative-array', '?associative-array|?false');

        /**
         * @param list<Node|int|float|string> $args
         */
        $get_element_type_of_first_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $false_type): UnionType {
            if (\count($args) >= 1) {
                $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $element_types = $array_type->genericArrayElementTypes(true, $code_base);
                if (!$element_types->isEmpty()) {
                    return $element_types->withType($false_type);
                }
            }
            return $mixed_type->asPHPDocUnionType();
        };
        /**
         * @return Closure(CodeBase, Context, Func, list<Node|int|float|string>): UnionType
         */
        $get_element_type_of_first_arg_check_nonempty_builder = static function (Type $default_type) use ($mixed_type): Closure {
            /**
             * @param list<Node|int|float|string> $args
             */
            return static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $default_type): UnionType {
                if (\count($args) >= 1) {
                    $arg_node = $args[0];
                    $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                    $element_types = $array_type->genericArrayElementTypes(true, $code_base);
                    if (!$element_types->isEmpty()) {
                        // We set __phan_is_nonempty because the return type is computed after the original variable type is changed.
                        if ($array_type->containsFalsey() && !isset($arg_node->__phan_is_nonempty)) {
                            // This array can be empty, so these helpers can return false/null.
                            return $element_types->withType($default_type);
                        }
                        return $element_types;
                    }
                }
                return $mixed_type->asPHPDocUnionType();
            };
        };

        $get_element_type_of_first_arg_check_nonempty_false = $get_element_type_of_first_arg_check_nonempty_builder($false_type);
        $get_element_type_of_first_arg_check_nonempty_null = $get_element_type_of_first_arg_check_nonempty_builder($null_type);
        /**
         * @param list<Node|int|float|string> $args
         * Note that key() is currently guaranteed to return int|string|null, and ignores implementations of ArrayAccess.
         * See zend_hash_get_current_key_zval_ex in php-src/Zend/zend_hash.c
         */
        $key_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($int_or_string_or_null, $null_type): UnionType {
            if (\count($args) !== 1) {
                return $null_type->asRealUnionType();
            }
            $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($array_type);
            if ($key_type_enum === GenericArrayType::KEY_MIXED) {
                return UnionType::fromFullyQualifiedRealString('int|string|null');
            }
            $key_type = GenericArrayType::unionTypeForKeyType($key_type_enum)->withType($null_type);
            if (!$array_type->hasRealTypeSet()) {
                return $key_type->withRealTypeSet($int_or_string_or_null->getRealTypeSet());
            }
            $real_key_type_enum = GenericArrayType::keyUnionTypeFromTypeSetStrict($array_type->getRealTypeSet());
            if ($real_key_type_enum === GenericArrayType::KEY_MIXED) {
                return $key_type->withType($null_type)->withRealTypeSet($int_or_string_or_null->getRealTypeSet());
            }
            $real_key_type = GenericArrayType::unionTypeForKeyType($key_type_enum);
            return $key_type->withRealTypeSet(\array_merge($real_key_type->getTypeSet(), [$null_type]));
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $get_key_type_of_first_arg_or_null = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($int_or_string, $int_or_string_or_null, $null_type): UnionType {
            if (\count($args) === 0) {
                return $null_type->asRealUnionType();
            }
            $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($array_type);
            if ($key_type_enum !== GenericArrayType::KEY_MIXED) {
                $key_type = GenericArrayType::unionTypeForKeyType($key_type_enum);
                if ($array_type->containsFalsey()) {
                    $key_type = $key_type->withType($null_type);
                }
                return $key_type->withRealTypeSet($int_or_string_or_null->getRealTypeSet());
            }
            if ($array_type->containsFalsey()) {
                return $int_or_string_or_null;
            }
            return $int_or_string;
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $get_key_type_of_second_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($int_or_string_or_false, $false_type): UnionType {
            if (\count($args) >= 2) {
                $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
                $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($array_type);
                if ($key_type_enum !== GenericArrayType::KEY_MIXED) {
                    $key_type = GenericArrayType::unionTypeForKeyType($key_type_enum);
                    return $key_type->withType($false_type)->withRealTypeSet($int_or_string_or_false->getTypeSet());
                }
            }
            return $int_or_string_or_false;
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $get_first_array_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_array, $null_type): UnionType {
            if (\count($args) === 0) {
                return $null_type->asRealUnionType();
            }
            $arg_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $element_types = $arg_type->genericArrayTypes();
            if ($element_types->isEmpty()) {
                return $probably_real_array;
            }
            $result = $element_types->withFlattenedTopLevelArrayShapeTypeInstances()
                                    ->withIntegerKeyArraysAsLists();
            if (!$result->hasRealTypeSet() || !$arg_type->getRealUnionType()->nonArrayTypes()->isEmpty()) {
                $result = $result->withRealTypeSet($probably_real_array->getRealTypeSet());
            }
            return $result;
        };
        $make_get_first_array_arg = static function (bool $can_reduce_size) use ($probably_real_assoc_array): Closure {
             return /** @param list<Node|int|float|string> $args */ static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_assoc_array, $can_reduce_size): UnionType {
                if (\count($args) >= 1) {
                    $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->genericArrayTypes();
                    if (!$element_types->isEmpty()) {
                        return $element_types->withFlattenedTopLevelArrayShapeTypeInstances()
                                             ->withAssociativeArrays($can_reduce_size)
                                             ->withRealTypeSet($probably_real_assoc_array->getRealTypeSet())
                                             ->withPossiblyEmptyArrays();
                    }
                }
                return $probably_real_assoc_array;
             };
        };
        $get_first_array_arg_assoc = $make_get_first_array_arg(true);
        // Same as $get_first_array_arg_assoc, but will convert types such as non-empty-array to non-empty-assocative-array instead of just associative-array
        $get_first_array_arg_assoc_same_size = $make_get_first_array_arg(false);
        /** @param list<Node|int|float|string> $args */
        $array_unique_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_assoc_array): UnionType {
            if (\count($args) >= 1) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->genericArrayTypes();
                if (!$element_types->isEmpty()) {
                    $result = $element_types->withFlattenedTopLevelArrayShapeTypeInstances()
                                            ->withAssociativeArrays(false)
                                            ->withPossiblyEmptyArrays()
                                            ->withRealTypeSet($probably_real_assoc_array->getRealTypeSet());
                    return $result;
                }
            }
            return $probably_real_assoc_array;
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_fill_keys_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $probably_real_array): UnionType {
            if (\count($args) === 2) {
                $key_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $key_type_enum = GenericArrayType::keyTypeFromUnionTypeValues($key_types);
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
                if ($element_types->isEmpty()) {
                    if ($key_type_enum === GenericArrayType::KEY_MIXED) {
                        return $probably_real_array;
                    }
                    $element_types = $mixed_type->asPHPDocUnionType();
                }
                return $element_types->asNonEmptyGenericArrayTypes($key_type_enum);
            }
            return $probably_real_array;
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $array_fill_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type): UnionType {
            if (\count($args) === 3) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
                return $element_types->asNonEmptyGenericArrayTypes(GenericArrayType::KEY_INT);
            }
            return $array_type->asPHPDocUnionType();
        };

        /**
         * @param list<Node|int|string|float> $args
         */
        $array_filter_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($nullable_array_type_set, $probably_real_assoc_array): UnionType {
            if (\count($args) >= 1) {
                $passed_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $generic_passed_array_type = $passed_array_type->genericArrayTypes();
                if (!$generic_passed_array_type->isEmpty()) {
                    $generic_passed_array_type = $generic_passed_array_type->withRealTypeSet($nullable_array_type_set);
                    if (\count($args) >= 2) {
                        // As a side effect of getting the list of callables, this warns about invalid callables
                        $filter_function_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[1], true);
                        if (Config::get_track_references()) {
                            foreach ($filter_function_list as $filter_function) {
                                $filter_function->addReference($context);
                            }
                        }
                        if (count($args) === 2) {
                            foreach ($filter_function_list as $filter_function) {
                                // Analyze that the individual elements passed to array_filter()'s callback make sense.
                                // TODO: analyze ARRAY_FILTER_USE_KEY, ARRAY_FILTER_USE_BOTH
                                $passed_array_element_types = $passed_array_type->genericArrayElementTypes(true, $code_base);
                                $line = $args[0]->lineno ?? $context->getLineNumberStart();
                                ArgumentType::analyzeParameter(
                                    $code_base,
                                    $context,
                                    $filter_function,
                                    $passed_array_element_types,
                                    $line,
                                    0,
                                    new Node(\ast\AST_UNPACK, 0, ['expr' => $args[0]], $line),  // dummy node for issue messages
                                    null
                                );
                                if (!Config::get_quick_mode()) {
                                    $analyzer = new PostOrderAnalysisVisitor($code_base, $context, []);
                                    $analyzer->analyzeCallableWithArgumentTypes([$passed_array_element_types], $filter_function);
                                }
                            }
                        }
                        if (self::callbacksRemoveNull($filter_function_list)) {
                            $generic_passed_array_type = $generic_passed_array_type->withMappedElementTypes(static function (UnionType $union_type): UnionType {
                                return $union_type->nonNullableClone();
                            });
                        }
                        // TODO: Handle 3 args?
                        //
                        // ARRAY_FILTER_USE_KEY - pass key as the only argument to callback instead of the value
                        // ARRAY_FILTER_USE_BOTH - pass both value and key as arguments to callback instead of the value
                    } elseif (\count($args) === 1) {
                        // array_filter with count($args) === 1 implies elements of the resulting array aren't falsey
                        return $generic_passed_array_type->withFlattenedTopLevelArrayShapeTypeInstances()
                                                         ->withMappedElementTypes(static function (UnionType $union_type): UnionType {
                                                            return $union_type->nonFalseyClone();
                                                         })
                                                         ->withAssociativeArrays(true)
                                                         ->withPossiblyEmptyArrays();
                    }
                    // TODO: Analyze if it and the flags are compatible with the arguments to the closure provided.
                    // TODO: withFlattenedArrayShapeOrLiteralTypeInstances() for other values
                    return $generic_passed_array_type->withFlattenedTopLevelArrayShapeTypeInstances()
                                                     ->withAssociativeArrays(true)
                                                     ->withPossiblyEmptyArrays();
                }
            }
            return $probably_real_assoc_array;
        };

        /**
         * @param list<Node|int|string|float> $args
         */
        $array_reduce_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type): UnionType {
            if (\count($args) < 2) {
                return $mixed_type->asPHPDocUnionType();
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[1], true);
            if (\count($function_like_list) === 0) {
                return $mixed_type->asPHPDocUnionType();
            }
            $function_return_types = UnionType::empty();
            foreach ($function_like_list as $function_like) {
                // TODO: Support analysis of map/reduce functions with dependent union types?
                $function_return_types = $function_return_types->withUnionType($function_like->getUnionType());
            }
            if ($function_return_types->isEmpty()) {
                $function_return_types = $function_return_types->withType($mixed_type);
            }
            return $function_return_types;
        };

        /**
         * Merges array shapes intelligently, preserving key-to-type mappings when possible.
         * For array_merge semantics: rightmost array's values win for overlapping keys.
         * NOTE: If all keys are integers, this will return null to fallback to generic/list handling.
         *
         * @param list<ArrayShapeType> $shapes
         * @param list<bool> $is_empty_array whether each corresponding shape argument is known to be empty
         * @return ?UnionType The merged shape, or null if merging requires fallback to generic array
         */
        $merge_array_shapes = static function (array $shapes, array $is_empty_array): ?UnionType {
            if (empty($shapes)) {
                return null;
            }

            $field_keys = [];
            foreach ($shapes as $shape_index => $shape) {
                if ($is_empty_array[$shape_index] ?? false) {
                    continue;
                }
                foreach (array_keys($shape->getFieldTypes()) as $key) {
                    $field_keys[$key] = true;
                }
            }

            if (!$field_keys) {
                return null;
            }

            /** @var array<string|int,UnionType> $merged_fields */
            $merged_fields = [];
            foreach (array_keys($field_keys) as $key) {
                $merged_type = UnionType::empty();
                $has_merged_type = false;
                $is_required = false;

                // Traverse shapes from the last argument to the first so that we can
                // emulate array_merge semantics where later arguments override earlier ones.
                for ($i = count($shapes) - 1; $i >= 0; $i--) {
                    if ($is_empty_array[$i] ?? false) {
                        continue;
                    }
                    $current_fields = $shapes[$i]->getFieldTypes();
                    if (!isset($current_fields[$key])) {
                        continue;
                    }
                    /** @var UnionType $field_union */
                    $field_union = $current_fields[$key];
                    $field_required = !$field_union->isPossiblyUndefined();
                    $field_union = $field_union->withIsPossiblyUndefined(false);

                    if (!$has_merged_type) {
                        $merged_type = $field_union;
                        $is_required = $field_required;
                        $has_merged_type = true;
                        continue;
                    }

                    if ($is_required) {
                        // Later shapes already guarantee the presence of this key, so earlier shapes
                        // cannot affect the runtime value.
                        continue;
                    }

                    $merged_type = $merged_type->withUnionType($field_union);
                    $is_required = $is_required || $field_required;
                }

                if (!$has_merged_type) {
                    continue;
                }

                $merged_fields[$key] = $is_required ? $merged_type : $merged_type->withIsPossiblyUndefined(true);
            }

            if (!$merged_fields) {
                return null;
            }

            // If all keys are integers, don't return a shape (let normal list handling take over)
            $all_int_keys = true;
            /** @phan-suppress-next-line PhanUnusedVariableValueOfForeachWithKey */
            foreach ($merged_fields as $key => $_type) {
                if (!is_int($key)) {
                    $all_int_keys = false;
                    break;
                }
            }

            // Only return a shape if we have at least one non-integer key
            if ($all_int_keys) {
                return null;
            }

            // Create and return the merged array shape
            return ArrayShapeType::fromFieldTypes($merged_fields, false)->asPHPDocUnionType();
        };

        /**
         * @param list<Node|int|string|float> $args
         */
        $merge_array_types_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type, $merge_array_shapes): UnionType {
            if (!$args) {
                return NullType::instance(false)->asRealUnionType();
            }
            $has_non_array = false;
            /** @var array<int,array{shapes:list<ArrayShapeType>,has_empty:bool,array_union:UnionType,arg_index:int}> $array_shapes_per_arg */
            $array_shapes_per_arg = [];  // Track shapes per argument to avoid merging union alternatives
            $types = null;

            // Collect array types and track if we have array shapes
            // We track shapes per argument because union types within an argument are alternatives (OR),
            // not combinations to merge. Only merge shapes from different arguments.
            foreach ($args as $arg_index => $arg) {
                $passed_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg);
                $new_types = $passed_array_type->genericArrayTypes();

                $arg_shapes = [];
                $arg_has_empty = false;

                // Check if this argument is or contains an array shape or generic array
                foreach ($new_types->getTypeSet() as $type) {
                    if ($type instanceof ArrayShapeType) {
                        $arg_shapes[] = $type;
                        // Check if it's an empty shape
                        if (!$type->isNotEmptyArrayShape()) {
                            $arg_has_empty = true;
                        }
                    }
                }

                // Track shapes from this argument (they may be union alternatives)
                // Also track the full array union to validate no other array variants exist
                if (!empty($arg_shapes)) {
                    $array_shapes_per_arg[] = [
                        'arg_index' => $arg_index,  // Track which argument this came from
                        'shapes' => $arg_shapes,
                        'has_empty' => $arg_has_empty,
                        'array_union' => $new_types  // Full union for validation
                    ];
                }

                $types = $types instanceof UnionType ? $types->withUnionType($new_types) : $new_types;
                $has_non_array = $has_non_array || (!$passed_array_type->hasRealTypeSet() || !$passed_array_type->asRealUnionType()->nonArrayTypes()->isEmpty());
            }

            // Only process shape merging if we have shapes and can do it safely.
            // We only merge shapes from different arguments, never from the same argument
            // (which would be union alternatives).
            if (count($array_shapes_per_arg) === 1 && count($array_shapes_per_arg[0]['shapes']) === 1) {
                // Single argument with one shape. This could be from the first argument or a later argument.
                $shape_info = $array_shapes_per_arg[0];
                /** @phan-suppress-next-line PhanTypeInvalidDimOffset */
                $shape_arg_index = $shape_info['arg_index'];
                $shape_only = $shape_info['shapes'][0];
                /** @phan-suppress-next-line PhanTypeInvalidDimOffset */
                $shape_arg_union = $shape_info['array_union'];

                // Check if this argument's union consists only of shapes (no generic alternatives)
                $shape_arg_all_shapes = true;
                /** @phan-suppress-next-line PhanNonClassMethodCall,PhanPluginUnknownObjectMethodCall */
                foreach ($shape_arg_union->getTypeSet() as $type) {
                    if ($type instanceof ArrayType && !($type instanceof ArrayShapeType)) {
                        $shape_arg_all_shapes = false;
                        break;
                    }
                }

                // Preserve the shape if:
                // 1. It's from the first argument (arg_index 0), OR
                // 2. It's from the ACTUAL LAST argument (arg_index == count($args) - 1)
                //    (shapes from middle arguments are not guaranteed after later arguments override them)
                if ($shape_arg_all_shapes && ($shape_arg_index === 0 || $shape_arg_index === count($args) - 1)) {
                    // Check if this shape has any non-integer keys
                    $has_non_int_keys = false;
                    /** @phan-suppress-next-line PhanUnusedVariableValueOfForeachWithKey */
                    foreach ($shape_only->getFieldTypes() as $key => $_type) {
                        if (!is_int($key)) {
                            $has_non_int_keys = true;
                            break;
                        }
                    }

                    // Only preserve as shape if we have non-integer keys
                    if ($has_non_int_keys) {
                        $merged_shape = $shape_only->asPHPDocUnionType();

                        if ($shape_arg_index === 0) {
                            // Shape is from the first argument, return it directly
                            $types = $merged_shape->withIntegerKeyArraysAsLists();
                        } else {
                            // Shape is from a later argument (last argument in array_merge).
                            // Flatten away shapes from earlier non-pure arguments (they're not guaranteed),
                            // keeping only generic array types, then merge with the guaranteed last shape.
                            // This preserves generic element access while adding the guaranteed keys.
                            $flattened_types = $types->withFlattenedTopLevelArrayShapeTypeInstances();
                            $types = $flattened_types->withUnionType($merged_shape)->withIntegerKeyArraysAsLists();
                        }

                        if ($has_non_array || !$types->hasRealTypeSet()) {
                            $types = $types->withRealTypeSet([ArrayType::instance(true)]);
                        }
                        return $types;
                    }
                }
            } elseif (count($array_shapes_per_arg) > 1) {
                // Multiple arguments, each with exactly one shape: merge them
                // In array_merge, the rightmost (last) argument's values always win for string keys.
                // So if the actual last argument passed to array_merge is a pure shape, its keys are guaranteed.
                // NOTE: $array_shapes_per_arg only contains arguments WITH shapes, so we must verify
                // that the last shape we collected is actually from the final argument position.

                // Check if there's a shape from the actual last argument (arg_index == count($args) - 1)
                $last_arg_index = count($args) - 1;
                $last_arg_is_pure_shape = false;
                $last_shape_info = null;

                foreach ($array_shapes_per_arg as $arg_info) {
                    if ($arg_info['arg_index'] === $last_arg_index) {
                        $last_shape_info = $arg_info;
                        // Check if this last argument is pure shapes
                        if (count($arg_info['shapes']) === 1) {
                            $is_pure = true;
                            foreach ($arg_info['array_union']->getTypeSet() as $type) {
                                if ($type instanceof ArrayType && !($type instanceof ArrayShapeType)) {
                                    $is_pure = false;
                                    break;
                                }
                            }
                            $last_arg_is_pure_shape = $is_pure;
                        }
                        break;
                    }
                }

                if (!$last_arg_is_pure_shape) {
                    // Last argument is not a pure single shape, can't preserve shapes from any argument
                    // Fall through to generic handling below
                } else {
                    // Last argument is pure with exactly one shape
                    // Check if ALL arguments are pure (so we can merge all)
                    $all_single_shape = true;
                    $all_args_pure_shapes = true;
                    $shapes_to_merge = [];
                    $is_empty_flags = [];

                    foreach ($array_shapes_per_arg as $arg_info) {
                        if (count($arg_info['shapes']) !== 1) {
                            $all_single_shape = false;
                            break;
                        }

                        $array_union = $arg_info['array_union'];
                        foreach ($array_union->getTypeSet() as $type) {
                            if ($type instanceof ArrayType && !($type instanceof ArrayShapeType)) {
                                // This argument has non-shape alternatives
                                $all_args_pure_shapes = false;
                                break;  // Just break the inner foreach, not both
                            }
                        }

                        $shapes_to_merge[] = $arg_info['shapes'][0];
                        $is_empty_flags[] = $arg_info['has_empty'];
                    }

                    // If all arguments have exactly one shape AND all are pure shapes, merge them
                    if ($all_single_shape && $all_args_pure_shapes) {
                        $merged_shape = $merge_array_shapes($shapes_to_merge, $is_empty_flags);
                        if ($merged_shape !== null) {
                            // Use the merged shape and also apply integer key as list conversion
                            $types = $merged_shape->withIntegerKeyArraysAsLists();
                            if ($has_non_array || !$types->hasRealTypeSet()) {
                                $types = $types->withRealTypeSet([ArrayType::instance(true)]);
                            }
                            return $types;
                        }
                    } else {
                        // Not all arguments are pure, but we know the ACTUAL LAST argument IS pure.
                        // In array_merge, the last argument's keys are guaranteed to be in the result.
                        // However, shapes from non-pure earlier arguments must be removed because they
                        // may not actually be present (the argument could match its generic alternative).
                        // We flatten the shapes away and keep only the generic array types, then merge
                        // with the guaranteed shape from the last argument.
                        if ($last_shape_info !== null) {
                            $last_shape = $last_shape_info['shapes'][0];
                            $last_shape_union = $last_shape->asPHPDocUnionType();
                            // Flatten shapes from non-pure arguments, keeping only generic arrays
                            $flattened_types = $types->withFlattenedTopLevelArrayShapeTypeInstances();
                            // Merge the flattened types with the guaranteed last shape
                            $types = $flattened_types->withUnionType($last_shape_union)->withIntegerKeyArraysAsLists();
                            if ($has_non_array || !$types->hasRealTypeSet()) {
                                $types = $types->withRealTypeSet([ArrayType::instance(true)]);
                            }
                            return $types;
                        }
                    }
                }
            }

            // Fall back to the original behavior if we can't merge array shapes
            $types = $types->withFlattenedTopLevelArrayShapeTypeInstances()
                           ->withIntegerKeyArraysAsLists();
            if ($types->isEmpty()) {
                $types = $types->withType($array_type);
            }
            if ($has_non_array || !$types->hasRealTypeSet()) {
                $types = $types->withRealTypeSet([ArrayType::instance(true)]);
            }
            return $types;
        };

        /**
         * @param list<Node|int|string|float> $args
         */
        $array_map_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $array_map_function,
            array $args
        ) use (
            $nullable_array_type_set,
            $real_array
        ): UnionType {
            // TODO: Handle non-empty-array in these methods and convert to non-empty-array.
            if (\count($args) < 2) {
                // Will throw an ArgumentCountError
                return $real_array;
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
            foreach ($function_like_list as $mapping_function) {
                $mapping_node = $mapping_function->getNode();
                if ($mapping_node instanceof Node) {
                    if (isset($mapping_node->__phan_skip_param_too_few_unpack)) {
                        /** @phan-suppress-next-line PhanUndeclaredProperty */
                        unset($mapping_node->__phan_skip_param_too_few_unpack);
                    }
                }
            }
            if (\count($function_like_list) === 0) {
                return $array_map_function->getUnionType();
            }
            $arguments = \array_slice($args, 1);
            $cache_outer = [];
            /**
             * @param Node|int|string|float|null $argument
             */
            $get_argument_type = static function (Node|float|int|null|string $argument, int $i) use ($code_base, $context, &$cache_outer): UnionType {
                if (isset($cache_outer[$i])) {
                    return $cache_outer[$i];
                }
                $argument_type = UnionTypeVisitor::unionTypeFromNode(
                    $code_base,
                    $context,
                    $argument,
                    true
                );
                $cache_outer[$i] = $argument_type;
                return $argument_type;
            };
            $cache = [];
            // Don't calculate argument types more than once.
            /**
             * @param Node|int|string|float|null $argument
             */
            $get_argument_type_for_array_map = static function (Node|float|int|null|string $argument, int $i) use ($get_argument_type, $code_base, &$cache): UnionType {
                if (isset($cache[$i])) {
                    return $cache[$i];
                }
                // Convert T[] to T
                $array_union_type = $get_argument_type($argument, $i);
                $argument_type = $array_union_type->genericArrayElementTypes(true, $code_base);

                // Fix for issue #4872: When element types are arrays themselves, check if the
                // source arrays are definitely non-empty. If so, convert generic array element
                // types to non-empty variants to allow proper unpacking validation.
                if (!$argument_type->hasArrayLike($code_base)) {
                    // Element type is not an array, no need to check for non-empty
                    $cache[$i] = $argument_type;
                    return $argument_type;
                }
                // Check if all source arrays in the union are definitely non-empty
                $all_non_empty = true;
                foreach ($array_union_type->getTypeSet() as $type) {
                    if ($type instanceof ArrayType) {
                        // Check if this array type is definitely non-empty
                        // ArrayShapeType and GenericArrayType both have isDefinitelyNonEmptyArray()
                        if ($type instanceof ArrayShapeType || $type instanceof GenericArrayType) {
                            $is_non_empty = $type->isDefinitelyNonEmptyArray();
                            if (!$is_non_empty) {
                                // Array might be empty
                                $all_non_empty = false;
                                break;
                            }
                        } else {
                            // Plain ArrayType without shape/size info - might be empty
                            $all_non_empty = false;
                            break;
                        }
                    } else {
                        // Non-array type in union
                        $all_non_empty = false;
                        break;
                    }
                }

                // If the source arrays are definitely non-empty, ensure array element types
                // are also marked as non-empty (preserves info for unpacking validation)
                if ($all_non_empty) {
                    $new_types = [];
                    foreach ($argument_type->getTypeSet() as $element_type) {
                        if ($element_type instanceof ArrayShapeType) {
                            if ($element_type->isDefinitelyNonEmptyArray()) {
                                $new_types[] = $element_type->asPHPDocUnionType();
                            } else {
                                $element_union = $element_type->genericArrayElementUnionType();
                                $new_types[] = $element_union->asMappedUnionType(static function (Type $type): Type {
                                    return NonEmptyListType::fromElementType($type, false);
                                });
                            }
                            continue;
                        }
                        if ($element_type instanceof GenericArrayType) {
                            if ($element_type->isDefinitelyNonEmptyArray()) {
                                $new_types[] = $element_type->asPHPDocUnionType();
                            } else {
                                $key_type = $element_type instanceof AssociativeArrayType
                                    ? GenericArrayType::KEY_MIXED
                                    : GenericArrayType::KEY_INT;
                                $new_types[] = $element_type->genericArrayElementUnionType()->asNonEmptyGenericArrayTypes($key_type);
                            }
                            continue;
                        }
                        if ($element_type instanceof ArrayType) {
                            // Plain ArrayType - treat as list<mixed> and convert to non-empty-list
                            $new_types[] = MixedType::instance(false)
                                ->asPHPDocUnionType()
                                ->asMappedUnionType(static function (Type $type): Type {
                                    return NonEmptyListType::fromElementType($type, false);
                                });
                            continue;
                        }
                    }
                    if ($new_types) {
                        $argument_type = UnionType::of(\array_merge(...\array_map(
                            /** @return array<int, Type> */
                            static function (UnionType $t): array {
                                return $t->getTypeSet();
                            },
                            $new_types
                        )));
                    }
                }

                $cache[$i] = $argument_type;
                return $argument_type;
            };
            foreach ($function_like_list as $mapping_function) {
                ArgumentType::analyzeForCallback(
                    $mapping_function,
                    $arguments,
                    $context,
                    $code_base,
                    $get_argument_type_for_array_map
                );
            }
            if (Config::get_track_references()) {
                foreach ($function_like_list as $mapping_function) {
                    $mapping_function->addReference($context);
                }
            }
            if (!Config::get_quick_mode()) {
                $argument_types = [];
                foreach ($arguments as $i => $node) {
                    $argument_types[] = $get_argument_type_for_array_map($node, $i);
                }
                foreach ($function_like_list as $mapping_function) {
                    $analyzer = new PostOrderAnalysisVisitor($code_base, $context, []);
                    $erase_old_types = $mapping_function instanceof Func && $mapping_function->isClosure();
                    $analyzer->analyzeCallableWithArgumentTypes($argument_types, $mapping_function, [], $erase_old_types);
                }
            }

            // NOTE: Get the union type of the function or closure *after* analyzing that closure with the given argument types.
            // Analyzing a function will add the return types that were observed during analysis.
            $possible_return_types = null;
            foreach ($function_like_list as $mapping_function) {
                // TODO: Fix https://github.com/phan/phan/issues/2554
                /*
                if ($mapping_function->hasDependentReturnType() && count($args) === 2 && ($args[1]->kind ?? null) !== \ast\AST_UNPACK) {
                    $fake_node_line = $args[1]->lineno ?? $context->getLineNumberStart();
                    $fake_node = new Node(\ast\AST_DIM, 0, [
                        'expr' => $args[1],
                        'dim' => new Node(\ast\AST_CALL, 0, [
                            'expr' => new Node(\ast\AST_NAME, \ast\flags\NAME_FQ, ['name' => 'rand'], $fake_node_line),
                            'args' => new Node(\ast\AST_ARG_LIST, 0, [0, 1], $fake_node_line),
                        ], $fake_node_line)
                    ], $fake_node_line);
                    $new_element_types = $mapping_function->getDependentReturnType($code_base, $context, [$fake_node]);
                } else
                 */
                $new_element_types = $mapping_function->getUnionType();

                if ($possible_return_types instanceof UnionType) {
                    $possible_return_types = $possible_return_types->withUnionType($new_element_types);
                } else {
                    $possible_return_types = $new_element_types;
                }
            }
            if ($possible_return_types->isEmpty()) {
                return $real_array;
            }
            if (count($arguments) >= 2) {
                // There were two or more arrays passed to the closure
                $result = $possible_return_types->asNonEmptyListTypes()->withRealTypeSet($nullable_array_type_set);
                foreach ($arguments as $i => $arg) {
                    $input_array_type = $get_argument_type($arg, $i);
                    if ($input_array_type->isEmpty() || $input_array_type->containsFalsey()) {
                        return $result;
                    }
                }
                return $result->nonFalseyClone();
            }
            $input_array_type = $get_argument_type($arguments[0], 0);
            $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($input_array_type);

            $is_associative = false;
            $is_list = false;

            foreach ($input_array_type->getTypeSet() as $type) {
                if ($type->isArrayLike($code_base)) {
                    if ($type instanceof ListType) {
                        $is_list = true;
                    } elseif ($type instanceof AssociativeArrayType) {
                        $is_associative = true;
                    } else {
                        $is_list = false;
                        $is_associative = false;
                        break;
                    }
                }
            }
            if ($is_list xor $is_associative) {
                if ($is_list) {
                    $return = $possible_return_types->asNonEmptyListTypes();
                } else {
                    $return = $possible_return_types->asNonEmptyAssociativeArrayTypes($key_type_enum);
                }
            } else {
                $return = $possible_return_types->elementTypesToGenericArray($key_type_enum);
            }
            if (!$input_array_type->isEmpty() && !$input_array_type->containsFalsey()) {
                $return = $return->nonFalseyClone();
            }

            return $return->withRealTypeSet($nullable_array_type_set);
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_pad_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type, $nullable_array_type_set): UnionType {
            if (\count($args) !== 3) {
                return UnionType::fromFullyQualifiedRealString('?array');
            }
            $padded_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $result_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2])->asGenericArrayTypes(GenericArrayType::KEY_INT);
            $result_types = $result_types->withUnionType($padded_array_type->genericArrayTypes());
            if ($result_types->isEmpty()) {
                $result_types = $result_types->withType($array_type);
            }
            return $result_types->withRealTypeSet($nullable_array_type_set);
        };
        /**
         * @param list<Node|int|string|float> $args
         */
        $array_keys_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_array, $nullable_list_type_set): UnionType {
            if (\count($args) < 1 || \count($args) > 3) {
                return $probably_real_array;
            }
            $key_union_type = UnionTypeVisitor::unionTypeOfArrayKeyForNode($code_base, $context, $args[0]);
            if ($key_union_type === null) {
                $key_union_type = UnionType::fromFullyQualifiedPHPDocString('int|string');
            }
            if ($key_union_type->isEmpty()) {
                return UnionType::fromFullyQualifiedPHPDocAndRealString('list<mixed>', '?list<mixed>');
            }
            return $key_union_type->asListTypes()->withRealTypeSet($nullable_list_type_set);
        };
        /**
         * @param list<Node|int|string|float> $args
         */
        $array_values_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($nullable_list_type_set, $real_array): UnionType {
            if (\count($args) !== 1) {
                // Will throw an ArgumentCountError
                return $real_array;
            }
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $element_type = $union_type->genericArrayElementTypes(true, $code_base);
            $result = $element_type->asListTypes();
            if ($result->isEmpty()) {
                return UnionType::fromFullyQualifiedPHPDocAndRealString('list<mixed>', '?list<mixed>');
            }
            if (!$result->hasRealTypeSet()) {
                $result = $result->withRealTypeSet($nullable_list_type_set);
            }
            if ($union_type->hasRealTypeSet()) {
                foreach ($union_type->getRealTypeSet() as $type) {
                    if (!$type instanceof ListType) {
                        return $result;
                    }
                }
                RedundantCondition::emitInstance(
                    $args[0],
                    $code_base,
                    (clone $context)->withLineNumberStart($args[0]->lineno ?? $context->getLineNumberStart()),
                    Issue::RedundantArrayValuesCall,
                    [
                        $union_type->asRealUnionType(),
                        $function->getRepresentationForIssue(),
                    ],
                    static function (UnionType $union_type): bool {
                        foreach ($union_type->getRealTypeSet() as $type) {
                            if (!$type instanceof ListType) {
                                return false;
                            }
                        }
                        return $union_type->hasRealTypeSet();
                    }
                );
            }
            return $result;
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_combine_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_assoc_array_falsey, $false_type): UnionType {
            if (\count($args) < 2) {
                return $false_type->asPHPDocUnionType();
            }
            $keys_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $values_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
            $keys_element_type = $keys_type->genericArrayElementTypes(false, $code_base);
            $values_element_type = $values_type->genericArrayElementTypes(false, $code_base);
            $key_enum_type = GenericArrayType::keyTypeFromUnionTypeValues($keys_element_type);
            $result = $values_element_type->asGenericArrayTypes($key_enum_type);
            return $result->withRealTypeSet($probably_real_assoc_array_falsey->getRealTypeSet());
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $iterator_to_array_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($false_type): UnionType {
            if (\count($args) < 1) {
                return $false_type->asPHPDocUnionType();
            }
            $iterator_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $value_type = $iterator_type->iterableValueUnionType($code_base);
            if (\count($args) >= 2) {
                $use_keys = !UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1])->containsFalsey();
            } else {
                $use_keys = true;
            }
            if ($value_type->isEmpty()) {
                // TODO: Be more accurate about whether this is definitely an array/list
                if ($use_keys) {
                    return UnionType::fromFullyQualifiedPHPDocAndRealString('array', 'array|false');
                } else {
                    return UnionType::fromFullyQualifiedPHPDocAndRealString('list', 'array|false');
                }
            }
            if ($use_keys) {
                // TODO check for ListType
                $key_type = $iterator_type->iterableKeyUnionType($code_base);
                $key_type_enum = GenericArrayType::keyUnionTypeFromTypeSetStrict($key_type->getTypeSet());
                return $value_type->asGenericArrayTypes($key_type_enum);
            }
            return $value_type->asListTypes();
        };
        /**
         * @param list<Node|int|float|string> $args
         * Infer return type of array_chunk based on input array and $preserve_keys parameter
         */
        $array_chunk_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($nullable_list_type_set): UnionType {
            if (\count($args) < 1) {
                return UnionType::fromFullyQualifiedPHPDocAndRealString('list<array>', '?list<array>');
            }

            // Get the element type from the input array
            $input_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $element_type = $input_array_type->genericArrayElementTypes(true, $code_base);

            if ($element_type->isEmpty()) {
                return UnionType::fromFullyQualifiedPHPDocAndRealString('list<array>', '?list<array>');
            }

            // Determine if $preserve_keys is true, false, or unknown
            $preserve_keys = null;  // null means unknown
            if (\count($args) >= 3) {
                $preserve_keys_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
                // Try to determine the boolean value statically
                if ($preserve_keys_type->isExclusivelyBoolTypes()) {
                    if ($preserve_keys_type->containsTruthy() && !$preserve_keys_type->containsFalsey()) {
                        $preserve_keys = true;
                    } elseif ($preserve_keys_type->containsFalsey() && !$preserve_keys_type->containsTruthy()) {
                        $preserve_keys = false;
                    }
                }
            } else {
                // Default is false when omitted
                $preserve_keys = false;
            }

            // Build the chunk type based on $preserve_keys
            if ($preserve_keys === false) {
                // list<list<V>>
                $chunk_type = $element_type->asListTypes();
            } elseif ($preserve_keys === true) {
                // list<array<K,V>>
                $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($input_array_type);
                $chunk_type = $element_type->asGenericArrayTypes($key_type_enum);
            } else {
                // Unknown: list<list<V>|array<K,V>>
                $list_chunk = $element_type->asListTypes();
                $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($input_array_type);
                $array_chunk = $element_type->asGenericArrayTypes($key_type_enum);
                $chunk_type = $list_chunk->withUnionType($array_chunk);
            }

            // Wrap in a list and add real type
            $result = $chunk_type->asListTypes();
            if (!$result->hasRealTypeSet()) {
                $result = $result->withRealTypeSet($nullable_list_type_set);
            }

            return $result;
        };
        return [
            // Gets the element types of the first
            'array_pop'   => $get_element_type_of_first_arg_check_nonempty_null,
            'array_shift' => $get_element_type_of_first_arg_check_nonempty_null,
            'current'     => $get_element_type_of_first_arg,
            'end'         => $get_element_type_of_first_arg_check_nonempty_false,
            'next'        => $get_element_type_of_first_arg,
            'pos'         => $get_element_type_of_first_arg,  // alias of 'current'
            'prev'        => $get_element_type_of_first_arg,
            'reset'       => $get_element_type_of_first_arg_check_nonempty_false,

            'key'          => $key_callback,
            'array_key_first' => $get_key_type_of_first_arg_or_null,
            'array_key_last' => $get_key_type_of_first_arg_or_null,

            'array_search' => $get_key_type_of_second_arg,

            // array_filter and array_map
            'array_map'    => $array_map_callback,
            'array_filter' => $array_filter_callback,
            'array_reduce' => $array_reduce_callback,

            // misc
            'array_change_key_case'     => $get_first_array_arg_assoc_same_size,
            'array_combine'             => $array_combine_callback,  // combines keys with values
            'array_diff'                => $get_first_array_arg_assoc,
            'array_diff_assoc'          => $get_first_array_arg_assoc,
            'array_diff_uassoc'         => $get_first_array_arg_assoc,
            'array_diff_ukey'           => $get_first_array_arg_assoc,
            'array_fill_keys'           => $array_fill_keys_callback,
            'array_fill'                => $array_fill_callback,
            'array_intersect'           => $get_first_array_arg_assoc,
            'array_intersect_assoc'     => $get_first_array_arg_assoc,
            'array_intersect_key'       => $get_first_array_arg_assoc,
            'array_intersect_uassoc'    => $get_first_array_arg_assoc,
            'array_intersect_ukey'      => $get_first_array_arg_assoc,
            'array_keys'                => $array_keys_callback,
            'array_merge'               => $merge_array_types_callback,
            'array_merge_recursive'     => $merge_array_types_callback,
            'array_pad'                 => $array_pad_callback,
            'array_replace'             => $merge_array_types_callback,
            'array_replace_recursive'   => $merge_array_types_callback,
            'array_reverse'             => $get_first_array_arg,
            'array_slice'               => $get_first_array_arg,
            // 'array_splice' probably used more often by reference
            'array_udiff'               => $get_first_array_arg_assoc,
            'array_udiff_assoc'         => $get_first_array_arg_assoc,
            'array_udiff_uassoc'        => $get_first_array_arg_assoc,
            'array_uintersect'          => $get_first_array_arg_assoc,
            'array_uintersect_assoc'    => $get_first_array_arg_assoc,
            'array_uintersect_uassoc'   => $get_first_array_arg_assoc,
            'array_unique'              => $array_unique_callback,
            'array_values'              => $array_values_callback,
            'array_chunk'               => $array_chunk_callback,
            'iterator_to_array'         => $iterator_to_array_callback,
        ];
    }

    /**
     * @param array<int,\Phan\Language\Element\FunctionInterface> $filter_function_list
     */
    private static function callbacksRemoveNull(array $filter_function_list): bool
    {
        if (!$filter_function_list) {
            return false;
        }
        foreach ($filter_function_list as $filter_function) {
            $node = $filter_function->getNode();
            if (!($node instanceof Node)) {
                return false;
            }
            $param_name = self::extractFirstParameterName($node);
            if (!\is_string($param_name)) {
                return false;
            }
            $expr = self::extractReturnExpression($node);
            if (!($expr instanceof Node)) {
                return false;
            }
            if (!self::expressionChecksNotNull($expr, $param_name)) {
                return false;
            }
        }
        return true;
    }

    private static function extractFirstParameterName(Node $node): ?string
    {
        $params = $node->children['params'] ?? null;
        if (!($params instanceof Node)) {
            return null;
        }
        foreach ($params->children as $param_node) {
            if ($param_node instanceof Node && $param_node->kind === \ast\AST_PARAM) {
                $name = $param_node->children['name'] ?? null;
                return \is_string($name) ? $name : null;
            }
        }
        return null;
    }

    private static function extractReturnExpression(Node $node): ?Node
    {
        $stmts = $node->children['stmts'] ?? null;
        if (!($stmts instanceof Node)) {
            return null;
        }
        if ($node->kind === \ast\AST_ARROW_FUNC) {
            $expr = $stmts->children['expr'] ?? null;
            return $expr instanceof Node ? $expr : null;
        }
        if ($node->kind === \ast\AST_CLOSURE) {
            foreach ($stmts->children as $child) {
                if ($child instanceof Node && $child->kind === \ast\AST_RETURN) {
                    $expr = $child->children['expr'] ?? null;
                    return $expr instanceof Node ? $expr : null;
                }
            }
        }
        return null;
    }

    private static function expressionChecksNotNull(Node $expr, string $param_name): bool
    {
        if ($expr->kind !== \ast\AST_BINARY_OP) {
            return false;
        }
        $flags = $expr->flags;
        if ($flags !== flags\BINARY_IS_NOT_EQUAL && $flags !== flags\BINARY_IS_NOT_IDENTICAL) {
            return false;
        }
        $left = $expr->children['left'] ?? null;
        $right = $expr->children['right'] ?? null;
        if ($left instanceof Node && $right instanceof Node) {
            if (self::isParamVariableNode($left, $param_name) && self::isNullLiteralNode($right)) {
                return true;
            }
            if (self::isParamVariableNode($right, $param_name) && self::isNullLiteralNode($left)) {
                return true;
            }
        }
        return false;
    }

    private static function isParamVariableNode(Node $node, string $param_name): bool
    {
        if ($node->kind === \ast\AST_VAR) {
            $name = $node->children['name'] ?? null;
            return \is_string($name) && $name === $param_name;
        }
        return false;
    }

    private static function isNullLiteralNode(?Node $node): bool
    {
        if ($node instanceof Node && $node->kind === \ast\AST_CONST) {
            $name_node = $node->children['name'] ?? null;
            if ($name_node instanceof Node && $name_node->kind === \ast\AST_NAME) {
                return \strcasecmp((string)($name_node->children['name'] ?? ''), 'null') === 0;
            }
        }
        return false;
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     */
    public function getReturnTypeOverrides(CodeBase $code_base): array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getReturnTypeOverridesStatic();
        }
        return $overrides;
    }
}

/**
 * Pre-analysis visitor that marks array_map callbacks so that unpack warnings can be deferred
 * until the callback body has been re-analyzed with concrete element types.
 */
final class ArrayReturnTypeOverridePreAnalysisVisitor extends PluginAwarePreAnalysisVisitor
{
    public function visitCall(Node $node): void
    {
        $expr = $node->children['expr'] ?? null;
        if (!$expr instanceof Node || $expr->kind !== \ast\AST_NAME) {
            return;
        }
        $name = $expr->children['name'] ?? null;
        if (!is_string($name) || strcasecmp($name, 'array_map') !== 0) {
            return;
        }
        $args = $node->children['args'] ?? null;
        if (!$args instanceof Node) {
            return;
        }
        $first_arg = $args->children[0] ?? null;
        if ($first_arg instanceof Node && ($first_arg->kind === \ast\AST_CLOSURE || $first_arg->kind === \ast\AST_ARROW_FUNC)) {
            try {
                $closure_func = (new ContextNode($this->code_base, $this->context, $first_arg))->getClosure();
            } catch (\Throwable) {
                return;
            }
            $closure_node = $closure_func->getNode();
            if ($closure_node instanceof Node) {
                /** @phan-suppress-next-line PhanUndeclaredProperty */
                $closure_node->__phan_skip_param_too_few_unpack = true;
            }
        }
    }
}
