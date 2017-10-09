<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\AST\UnionTypeVisitor;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\MixedType;
use Phan\Language\UnionType;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use Phan\PluginV2;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 */
class ArrayReturnTypeOverridePlugin extends PluginV2 implements ReturnTypeOverrideCapability {
    /**
     * @return \Closure[]
     */
    private static function getReturnTypeOverridesStatic(CodeBase $code_base) : array
    {
        $mixed_type = MixedType::instance(false);
        $false_type = FalseType::instance(false);
        $array_type = ArrayType::instance(false);
        $get_element_type_of_first_arg = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($mixed_type, $false_type) : UnionType {
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
        $get_first_array_arg = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) >= 1) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->genericArrayTypes();
                if (!$element_types->isEmpty()) {
                    return $element_types;
                }
            }
            return $array_type->asUnionType();
        };
        $get_second_array_arg = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) >= 2) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1])->genericArrayTypes();
                if (!$element_types->isEmpty()) {
                    return $element_types;
                }
            }
            return $array_type->asUnionType();
        };
        $get_first_array_arg_as_array = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) >= 1) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0])->genericArrayTypes();
                if (!$element_types->isEmpty()) {
                    return $element_types->asGenericArrayTypes();
                }
            }
            return $array_type->asGenericArrayType()->asUnionType();
        };
        $array_fill_keys_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) == 2) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
                if (!$element_types->isEmpty()) {
                    return $element_types->asGenericArrayTypes();
                }
            }
            return $array_type->asUnionType();
        };

        $array_fill_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) == 3) {
                $element_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
                if (!$element_types->isEmpty()) {
                    return $element_types->asGenericArrayTypes();
                }
            }
            return $array_type->asUnionType();
        };

        $array_filter_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) >= 1) {
                $passed_array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                $generic_passed_array_type = $passed_array_type->genericArrayTypes();
                if (!$generic_passed_array_type->isEmpty()) {
                    // TODO: Analyze if it and the flags are compatible with the arguments to the closure provided.
                    return $generic_passed_array_type;
                }
            }
            return $array_type->asUnionType();
        };

        $array_reduce_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($mixed_type) : UnionType {
            if (\count($args) < 2) {
                return $mixed_type->asUnionType();
            }
            $function_fqsen_list = UnionTypeVisitor::functionLikeFQSENListFromNodeAndContext($code_base, $context, $args[1], true);
            if (\count($function_fqsen_list) === 0) {
                return $mixed_type->asUnionType();
            }
            $function_return_types = new UnionType();
            foreach ($function_fqsen_list as $fqsen) {
                if ($fqsen instanceof FullyQualifiedMethodName) {
                    if (!$code_base->hasMethodWithFQSEN($fqsen)) {
                        // TODO: error PhanArrayMapClosure
                        continue;
                    }
                    $function_like = $code_base->getMethodByFQSEN($fqsen);
                } else {
                    assert($fqsen instanceof FullyQualifiedFunctionName);
                    if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
                        // TODO: error PhanArrayMapClosure
                        continue;
                    }
                    $function_like = $code_base->getFunctionByFQSEN($fqsen);
                }
                // TODO: dependent union type?
                $function_return_types->addUnionType($function_like->getUnionType());
            }
            if ($function_return_types->isEmpty()) {
                return $mixed_type->asUnionType();
            }
            return $function_return_types;
        };

        $merge_array_types_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
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

        $array_map_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
            if (\count($args) < 2) {
                return $array_type->asUnionType();
            }
            // TODO: improve functionLikeFQSENListFromNodeAndContext to include
            // 1. [MyClass::class, 'staticMethodName'],
            // 2. [$obj, 'instanceMethodName],
            // 3. 'global_func'
            // 4. 'MyClass::staticFunc'
            $function_fqsen_list = UnionTypeVisitor::functionLikeFQSENListFromNodeAndContext($code_base, $context, $args[0], true);
            if (\count($function_fqsen_list) === 0) {
                return $array_type->asUnionType();
            }
            $element_types = new UnionType();
            foreach ($function_fqsen_list as $fqsen) {
                if ($fqsen instanceof FullyQualifiedMethodName) {
                    if (!$code_base->hasMethodWithFQSEN($fqsen)) {
                        // TODO: error PhanArrayMapClosure
                        continue;
                    }
                    $function_like = $code_base->getMethodByFQSEN($fqsen);
                } else {
                    assert($fqsen instanceof FullyQualifiedFunctionName);
                    if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
                        // TODO: error PhanArrayMapClosure
                        continue;
                    }
                    $function_like = $code_base->getFunctionByFQSEN($fqsen);
                }
                $expected_parameter_count = \count($args) - 1;
                if ($function_like->getNumberOfRequiredRealParameters() > $expected_parameter_count) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooFewCallable,
                        $context->getLineNumberStart(),
                        $expected_parameter_count,
                        (string)$function_like->getFQSEN(),
                        $function_like->getNumberOfRequiredRealParameters(),
                        $function_like->getFileRef()->getFile(),
                        $function_like->getFileRef()->getLineNumberStart()
                    );
                } else if ($function_like->getNumberOfParameters() < $expected_parameter_count) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooManyCallable,
                        $context->getLineNumberStart(),
                        $expected_parameter_count,
                        (string)$function_like->getFQSEN(),
                        $function_like->getNumberOfParameters(),
                        $function_like->getFileRef()->getFile(),
                        $function_like->getFileRef()->getLineNumberStart()
                    );
                }
                // TODO: dependent union type?
                $element_types->addUnionType($function_like->getUnionType());
            }
            if ($element_types->isEmpty()) {
                return $array_type->asUnionType();
            }
            return $element_types->elementTypesToGenericArray();
        };
        $array_pad_callback = static function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
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
        return self::getReturnTypeOverridesStatic($code_base);
    }
}
