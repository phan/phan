<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\CodeBase;
use Phan\AST\UnionTypeVisitor;
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
    public function getReturnTypeOverrides(CodeBase $code_base) : array
    {
        $mixed_type = MixedType::instance(false);
        $false_type = FalseType::instance(false);
        $array_type = ArrayType::instance(false);
        $get_element_type_of_first_arg = function(CodeBase $code_base, Context $context, Func $function, array $args) use($mixed_type, $false_type) : UnionType {
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
        $array_filter_callback = function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
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
        $array_map_callback = function(CodeBase $code_base, Context $context, Func $function, array $args) use($array_type) : UnionType {
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
                // TODO: dependent union type?
                $element_types->addUnionType($function_like->getUnionType());
            }
            if ($element_types->isEmpty()) {
                return $array_type->asUnionType();
            }
            return $element_types->elementTypesToGenericArray();
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
            'array_map' => $array_map_callback,
            'array_filter' => $array_filter_callback,
        ];
    }
}
