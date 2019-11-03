<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\Analysis\ArgumentType;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\AssociativeArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\ListType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\ReturnTypeOverrideCapability;
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
final class ArrayReturnTypeOverridePlugin extends PluginV3 implements
    ReturnTypeOverrideCapability
{

    /**
     * @return array<string,\Closure>
     */
    private static function getReturnTypeOverridesStatic() : array
    {
        $mixed_type  = MixedType::instance(false);
        $false_type  = FalseType::instance(false);
        $array_type  = ArrayType::instance(false);
        $null_type   = NullType::instance(false);
        $nullable_array_type_set = [ArrayType::instance(true)];
        $nullable_int_key_array_type_set = [ListType::fromElementType(MixedType::instance(true), true)];
        $int_or_string_or_false = UnionType::fromFullyQualifiedRealString('int|string|false');
        $int_or_string_or_null = UnionType::fromFullyQualifiedRealString('int|string|null');
        $int_or_string = UnionType::fromFullyQualifiedRealString('int|string');
        // TODO: This might be replaced by non-null array if php 8.0 would throw for these cases.
        $real_nullable_array = UnionType::fromFullyQualifiedRealString('?array');
        $probably_real_array = UnionType::fromFullyQualifiedPHPDocAndRealString('array', '?array');
        $probably_real_assoc_array = UnionType::fromFullyQualifiedPHPDocAndRealString('associative-array', '?associative-array');
        $probably_real_assoc_array_falsey = UnionType::fromFullyQualifiedPHPDocAndRealString('associative-array', '?associative-array|?false');

        /**
         * @param list<Node|int|float|string> $args
         */
        $get_element_type_of_first_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $false_type) : UnionType {
            if (\count($args) >= 1) {
                $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $element_types = $array_type->genericArrayElementTypes();
                if (!$element_types->isEmpty()) {
                    return $element_types->withType($false_type);
                }
            }
            return $mixed_type->asPHPDocUnionType();
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $get_element_type_of_first_arg_check_nonempty = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $false_type) : UnionType {
            if (\count($args) >= 1) {
                $arg_node = $args[0];
                $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $element_types = $array_type->genericArrayElementTypes();
                if (!$element_types->isEmpty()) {
                    // We set __phan_is_nonempty because the return type is computed after the original variable type is changed.
                    // @phan-suppress-next-line PhanUndeclaredProperty
                    if ($array_type->containsFalsey() && !isset($arg_node->__phan_is_nonempty)) {
                        // This array can be empty, so these helpers can return false.
                        return $element_types->withType($false_type);
                    }
                    return $element_types;
                }
            }
            return $mixed_type->asPHPDocUnionType();
        };
        /**
         * @param list<Node|int|float|string> $args
         * Note that key() is currently guaranteed to return int|string|null, and ignores implementations of ArrayAccess.
         * See zend_hash_get_current_key_zval_ex in php-src/Zend/zend_hash.c
         */
        $key_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($int_or_string_or_null, $null_type) : UnionType {
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
        $get_key_type_of_first_arg_or_null = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($int_or_string, $int_or_string_or_null, $null_type) : UnionType {
            if (\count($args) == 0) {
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
        $get_key_type_of_second_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($int_or_string_or_false, $false_type) : UnionType {
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
        $get_first_array_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_array, $null_type) : UnionType {
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
        $make_get_first_array_arg = static function (bool $can_reduce_size) use ($probably_real_assoc_array) : Closure {
             return /** @param list<Node|int|float|string> $args */ static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_assoc_array, $can_reduce_size) : UnionType {
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
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_fill_keys_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $probably_real_array) : UnionType {
            if (\count($args) == 2) {
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
        $array_fill_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) == 3) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
                return $element_types->asNonEmptyGenericArrayTypes(GenericArrayType::KEY_INT);
            }
            return $array_type->asPHPDocUnionType();
        };

        /**
         * @param list<Node|int|string|float> $args
         */
        $array_filter_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($nullable_array_type_set, $probably_real_assoc_array) : UnionType {
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
                                $passed_array_element_types = $passed_array_type->genericArrayElementTypes();
                                ArgumentType::analyzeParameter($code_base, $context, $filter_function, $passed_array_element_types, $context->getLineNumberStart(), 0);
                                if (!Config::get_quick_mode()) {
                                    $analyzer = new PostOrderAnalysisVisitor($code_base, $context, []);
                                    $analyzer->analyzeCallableWithArgumentTypes([$passed_array_element_types], $filter_function);
                                }
                            }
                        }
                        // TODO: Handle 3 args?
                        //
                        // ARRAY_FILTER_USE_KEY - pass key as the only argument to callback instead of the value
                        // ARRAY_FILTER_USE_BOTH - pass both value and key as arguments to callback instead of the value
                    } elseif (\count($args) === 1) {
                        // array_filter with count($args) === 1 implies elements of the resulting array aren't falsey
                        return $generic_passed_array_type->withFlattenedTopLevelArrayShapeTypeInstances()
                                                         ->withMappedElementTypes(static function (UnionType $union_type) : UnionType {
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
        $array_reduce_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type) : UnionType {
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
         * @param list<Node|int|string|float> $args
         */
        $merge_array_types_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (!$args) {
                return NullType::instance(false)->asRealUnionType();
            }
            // TODO: Clean up once target_php_version >= 80000
            $has_non_array = false;
            $types = null;
            foreach ($args as $arg) {
                $passed_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg);
                $new_types = $passed_array_type->genericArrayTypes();
                $types = $types ? $types->withUnionType($new_types) : $new_types;
                $has_non_array = $has_non_array || (!$passed_array_type->hasRealTypeSet() || !$passed_array_type->asRealUnionType()->nonArrayTypes()->isEmpty());
            }
            if ($types) {
                $types = $types->withFlattenedTopLevelArrayShapeTypeInstances()
                               ->withIntegerKeyArraysAsLists();
            } else {
                $types = UnionType::empty();
            }
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
            Func $function,
            array $args
        ) use (
            $nullable_array_type_set,
            $probably_real_array,
            $real_nullable_array
) : UnionType {
            // TODO: Handle non-empty-array in these methods and convert to non-empty-array.
            if (\count($args) < 2) {
                return $real_nullable_array;
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
            if (\count($function_like_list) === 0) {
                return $probably_real_array;
            }
            $arguments = \array_slice($args, 1);
            $possible_return_types = UnionType::empty();
            $cache_outer = [];
            /**
             * @param Node|int|string|float|null $argument
             */
            $get_argument_type = static function ($argument, int $i) use ($code_base, $context, &$cache_outer) : UnionType {
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
            $get_argument_type_for_array_map = static function ($argument, int $i) use ($get_argument_type, &$cache) : UnionType {
                if (isset($cache[$i])) {
                    return $cache[$i];
                }
                // Convert T[] to T
                $argument_type = $get_argument_type($argument, $i)->genericArrayElementTypes();
                $cache[$i] = $argument_type;
                return $argument_type;
            };
            foreach ($function_like_list as $map_function) {
                ArgumentType::analyzeForCallback(
                    $map_function,
                    $arguments,
                    $context,
                    $code_base,
                    $get_argument_type_for_array_map
                );
                // TODO: fix https://github.com/phan/phan/issues/2554
                $possible_return_types = $possible_return_types->withUnionType($map_function->getUnionType());
            }
            if (Config::get_track_references()) {
                foreach ($function_like_list as $map_function) {
                    $map_function->addReference($context);
                }
            }
            if (!Config::get_quick_mode()) {
                $argument_types = [];
                foreach ($arguments as $i => $node) {
                    $argument_types[] = $get_argument_type_for_array_map($node, $i);
                }
                foreach ($function_like_list as $map_function) {
                    $analyzer = new PostOrderAnalysisVisitor($code_base, $context, []);
                    $analyzer->analyzeCallableWithArgumentTypes($argument_types, $map_function);
                }
            }
            if ($possible_return_types->isEmpty()) {
                return $probably_real_array;
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
                if ($type->isArrayLike()) {
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
        $array_pad_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type, $nullable_array_type_set) : UnionType {
            if (\count($args) != 3) {
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
        $array_keys_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_array, $nullable_int_key_array_type_set) : UnionType {
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
            return $key_union_type->asListTypes()->withRealTypeSet($nullable_int_key_array_type_set);
        };
        /**
         * @param list<Node|int|string|float> $args
         */
        $array_values_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($nullable_int_key_array_type_set, $real_nullable_array) : UnionType {
            if (\count($args) != 1) {
                return $real_nullable_array;
            }
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $element_type = $union_type->genericArrayElementTypes();
            $result = $element_type->asListTypes();
            if ($result->isEmpty()) {
                return UnionType::fromFullyQualifiedPHPDocAndRealString('list<mixed>', '?list<mixed>');
            }
            return $result->withRealTypeSet($nullable_int_key_array_type_set);
        };
        /**
         * @param list<Node|int|string|float> $args
         */
        $each_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $false_type, $int_or_string) : UnionType {
            if (\count($args) >= 1) {
                $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $element_types = $array_type->genericArrayElementTypes();
                $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($array_type);
                if ($key_type_enum !== GenericArrayType::KEY_MIXED) {
                    $key_type = GenericArrayType::unionTypeForKeyType($key_type_enum);
                } else {
                    $key_type = $int_or_string;
                }
                $array_shape_type = ArrayShapeType::fromFieldTypes([$key_type, $element_types], false);

                return new UnionType([$array_shape_type, $false_type], false, [ArrayType::instance(false), $false_type]);
            }
            return $mixed_type->asPHPDocUnionType();
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_combine_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($probably_real_assoc_array_falsey, $false_type) : UnionType {
            if (\count($args) < 2) {
                return $false_type->asPHPDocUnionType();
            }
            $keys_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $values_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
            $keys_element_type = $keys_type->genericArrayElementTypes();
            $values_element_type = $values_type->genericArrayElementTypes();
            $key_enum_type = GenericArrayType::keyTypeFromUnionTypeValues($keys_element_type);
            $result = $values_element_type->asGenericArrayTypes($key_enum_type);
            return $result->withRealTypeSet($probably_real_assoc_array_falsey->getRealTypeSet());
        };
        return [
            // Gets the element types of the first
            'array_pop'   => $get_element_type_of_first_arg_check_nonempty,
            'array_shift' => $get_element_type_of_first_arg_check_nonempty,
            'current'     => $get_element_type_of_first_arg,
            'end'         => $get_element_type_of_first_arg_check_nonempty,
            'next'        => $get_element_type_of_first_arg,
            'pos'         => $get_element_type_of_first_arg,  // alias of 'current'
            'prev'        => $get_element_type_of_first_arg,
            'reset'       => $get_element_type_of_first_arg_check_nonempty,
            'each'        => $each_callback,

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
            'array_unique'              => $get_first_array_arg_assoc_same_size,
            'array_values'              => $array_values_callback,
            // TODO: iterator_to_array
        ];
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,\Closure>
     */
    public function getReturnTypeOverrides(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getReturnTypeOverridesStatic();
        }
        return $overrides;
    }
}
