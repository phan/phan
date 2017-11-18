<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\Analysis\ArgumentType;
use Phan\Analysis\PostOrderAnalysisVisitor;
use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\MixedType;
use Phan\Language\UnionType;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use Phan\PluginV2;
use ast\Node;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Refactor this.
 */
final class ArrayReturnTypeOverridePlugin extends PluginV2 implements
    ReturnTypeOverrideCapability
{

    /**
     * @return \Closure[]
     */
    private static function getReturnTypeOverridesStatic(CodeBase $code_base) : array
    {
        $mixed_type = MixedType::instance(false);
        $false_type = FalseType::instance(false);
        $array_type = ArrayType::instance(false);
        $get_element_type_of_first_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type, $false_type) : UnionType {
            if (\count($args) >= 1) {
                $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $element_types = $array_type->genericArrayElementTypes();
                if (!$element_types->isEmpty()) {
                    $element_types->addType($false_type);
                    return $element_types;
                }
            }
            return $mixed_type->asUnionType();
        };
        $get_first_array_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) >= 1) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->genericArrayTypes();
                if (!$element_types->isEmpty()) {
                    return $element_types;
                }
            }
            return $array_type->asUnionType();
        };
        $get_second_array_arg = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) >= 2) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1])->genericArrayTypes();
                if (!$element_types->isEmpty()) {
                    return $element_types;
                }
            }
            return $array_type->asUnionType();
        };
        $array_fill_keys_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) == 2) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
                if (!$element_types->isEmpty()) {
                    return $element_types->asNonEmptyGenericArrayTypes();
                }
            }
            return $array_type->asUnionType();
        };

        $array_fill_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) == 3) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
                return $element_types->asNonEmptyGenericArrayTypes();
            }
            return $array_type->asUnionType();
        };

        $array_filter_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) >= 1) {
                $passed_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $generic_passed_array_type = $passed_array_type->genericArrayTypes();
                if (!$generic_passed_array_type->isEmpty()) {
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
                                    $analyzer = new PostOrderAnalysisVisitor($code_base, $context, null);
                                    $analyzer->analyzeCallableWithArgumentTypes([$passed_array_element_types], $filter_function);
                                }
                            }
                        }
                        // TODO: Handle 3 args?
                        //
                        // ARRAY_FILTER_USE_KEY - pass key as the only argument to callback instead of the value
                        // ARRAY_FILTER_USE_BOTH - pass both value and key as arguments to callback instead of the value
                    }
                    // TODO: Analyze if it and the flags are compatible with the arguments to the closure provided.
                    return $generic_passed_array_type;
                }
            }
            return $array_type->asUnionType();
        };

        $array_reduce_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($mixed_type) : UnionType {
            if (\count($args) < 2) {
                return $mixed_type->asUnionType();
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[1], true);
            if (\count($function_like_list) === 0) {
                return $mixed_type->asUnionType();
            }
            $function_return_types = new UnionType();
            foreach ($function_like_list as $function_like) {
                // TODO: Support analysis of map/reduce functions with dependent union types?
                $function_return_types->addUnionType($function_like->getUnionType());
            }
            if ($function_return_types->isEmpty()) {
                $function_return_types->addType($mixed_type);
            }
            return $function_return_types;
        };

        $merge_array_types_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            $types = new UnionType();
            foreach ($args as $arg) {
                $passed_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg);
                $types->addUnionType($passed_array_type->genericArrayTypes());
            }
            if ($types->isEmpty()) {
                $types->addType($array_type);
            }
            return $types;
        };

        $array_map_callback = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use ($array_type) : UnionType {
            if (\count($args) < 2) {
                return $array_type->asUnionType();
            }
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
            if (\count($function_like_list) === 0) {
                return $array_type->asUnionType();
            }
            $arguments = \array_slice($args, 1);
            $possible_return_types = new UnionType();
            $cache = [];
            // Don't calculate argument types more than once.
            $get_argument_type_for_array_map = function ($argument, int $i) use ($code_base, $context, &$cache) : UnionType {
                if (isset($cache[$i])) {
                    return $cache[$i];
                }
                $argument_type = UnionTypeVisitor::unionTypeFromNode(
                    $code_base,
                    $context,
                    $argument,
                    true
                )->genericArrayElementTypes();
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
                if ($map_function->hasDependentReturnType()) {
                    $possible_return_types->addUnionType($map_function->getDependentReturnType($code_base, $context, $arguments));
                } else {
                    $possible_return_types->addUnionType($map_function->getUnionType());
                }
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
                    $analyzer = new PostOrderAnalysisVisitor($code_base, $context, null);
                    $analyzer->analyzeCallableWithArgumentTypes($argument_types, $map_function);
                }
            }
            if ($possible_return_types->isEmpty()) {
                return $array_type->asUnionType();
            }
            return $possible_return_types->elementTypesToGenericArray();
        };
        $array_pad_callback = static function (CodeBase $code_base, Context $context, Func $function, array $args) use ($array_type) : UnionType {
            if (\count($args) != 3) {
                return $array_type->asUnionType();
            }
            $padded_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $result_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2])->asGenericArrayTypes();
            $result_types->addUnionType($padded_array_type->genericArrayTypes());
            if ($result_types->isEmpty()) {
                $result_types->addType($array_type);
            }
            return $result_types;
        };
        return [
            // Gets the element types of the first
            'array_pop'   => $get_element_type_of_first_arg,
            'array_shift' => $get_element_type_of_first_arg,
            'current'     => $get_element_type_of_first_arg,
            'end'         => $get_element_type_of_first_arg,
            'next'        => $get_element_type_of_first_arg,
            'pos'         => $get_element_type_of_first_arg,  // alias of 'current'
            'prev'        => $get_element_type_of_first_arg,
            'reset'       => $get_element_type_of_first_arg,

            // array_filter and array_map
            'array_map'    => $array_map_callback,
            'array_filter' => $array_filter_callback,
            'array_reduce' => $array_reduce_callback,

            // misc
            'array_change_key_case'     => $get_first_array_arg,
            'array_combine'             => $get_second_array_arg,  // combines keys with values
            'array_diff'                => $get_first_array_arg,
            'array_diff_assoc'          => $get_first_array_arg,
            'array_diff_uassoc'         => $get_first_array_arg,
            'array_diff_ukey'           => $get_first_array_arg,
            'array_fill_keys'           => $array_fill_keys_callback,
            'array_fill'                => $array_fill_callback,
            'array_intersect'           => $merge_array_types_callback,
            'array_intersect_assoc'     => $merge_array_types_callback,
            'array_intersect_key'       => $merge_array_types_callback,
            'array_intersect_uassoc'    => $merge_array_types_callback,
            'array_intersect_ukey'      => $merge_array_types_callback,
            'array_merge'               => $merge_array_types_callback,
            'array_merge_recursive'     => $merge_array_types_callback,
            'array_pad'                 => $array_pad_callback,
            'array_replace'             => $merge_array_types_callback,
            'array_replace_recursive'   => $merge_array_types_callback,
            'array_reverse'             => $get_first_array_arg,
            // 'array_splice' probably used more often by reference
            'array_udiff'               => $get_first_array_arg,
            'array_udiff_assoc'         => $get_first_array_arg,
            'array_udiff_uassoc'        => $get_first_array_arg,
            'array_uintersect'          => $merge_array_types_callback,
            'array_uintersect_assoc'    => $merge_array_types_callback,
            'array_uintersect_uassoc'   => $merge_array_types_callback,
            'array_unique'              => $get_first_array_arg,
        ];
    }

    /**
     * @return \Closure[]
     */
    public function getReturnTypeOverrides(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $overrides = null;
        if ($overrides === null) {
            $overrides = self::getReturnTypeOverridesStatic($code_base);
        }
        return $overrides;
    }
}
