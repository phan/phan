<?php declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use Phan\PluginV2\AnalyzePropertyCapability;

/**
 * This file checks if any elements in the codebase have undeclared types.
 */
class UnknownElementTypePlugin extends PluginV2 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AnalyzePropertyCapability
{

    /**
     * Returns true for array, ?array, and array|null
     */
    private static function isRegularArray(UnionType $type) : bool
    {
        return $type->hasTypeMatchingCallback(static function (Type $type) : bool {
            return get_class($type) === ArrayType::class;
        }) && !$type->hasTypeMatchingCallback(static function (Type $type) : bool {
            return get_class($type) !== ArrayType::class && !($type instanceof NullType);
        });
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        if ($method->getFQSEN() !== $method->getRealDefiningFQSEN()) {
            return;
        }

        self::performChecks(
            $code_base,
            $method,
            'PhanPluginUnknownMethodReturnType',
            'Method {METHOD} has no declared or inferred return type',
            'PhanPluginUnknownArrayMethodReturnType',
            'Method {METHOD} has a return type of array, but does not specify any key types or value types'
        );
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        foreach ($method->getParameterList() as $parameter) {
            if ($parameter->getUnionType()->isEmpty()) {
                self::emitIssue(
                    $code_base,
                    $parameter->createContext($method),
                    'PhanPluginUnknownMethodParamType',
                    'Method {METHOD} has no declared or inferred parameter type for ${PARAMETER}',
                    [(string)$method->getFQSEN(), $parameter->getName()]
                );
            } elseif (self::isRegularArray($parameter->getUnionType())) {
                self::emitIssue(
                    $code_base,
                    $parameter->createContext($method),
                    'PhanPluginUnknownArrayMethodParamType',
                    'Method {METHOD} has a parameter type of array for ${PARAMETER}, but does not specify any key types or value types',
                    [(string)$method->getFQSEN(), $parameter->getName()]
                );
            }
        }
    }

    private static function performChecks(
        CodeBase $code_base,
        AddressableElement $element,
        string $issue_type_for_empty,
        string $message_for_empty,
        string $issue_type_for_unknown_array,
        string $message_for_unknown_array
    ) {
        $union_type = $element->getUnionType();
        if ($union_type->isEmpty()) {
            self::emitIssue(
                $code_base,
                $element->getContext(),
                $issue_type_for_empty,
                $message_for_empty,
                [(string)$element->getFQSEN()]
            );
        } elseif (self::isRegularArray($union_type)) {
            self::emitIssue(
                $code_base,
                $element->getContext(),
                $issue_type_for_unknown_array,
                $message_for_unknown_array,
                [(string)$element->getFQSEN()]
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        if ($function->getUnionType()->isEmpty()) {
            if ($function->getFQSEN()->isClosure()) {
                $issue = 'PhanPluginUnknownClosureReturnType';
                $message = 'Closure {FUNCTION} has no declared or inferred return type';
            } else {
                $issue = 'PhanPluginUnknownFunctionReturnType';
                $message = 'Function {FUNCTION} has no declared or inferred return type';
            }
            self::emitIssue(
                $code_base,
                $function->getContext(),
                $issue,
                $message,
                [(string)$function->getNameForIssue()]
            );
        } elseif (self::isRegularArray($function->getUnionType())) {
            if ($function->getFQSEN()->isClosure()) {
                $issue = 'PhanPluginUnknownArrayClosureReturnType';
                $message = 'Closure {FUNCTION} has a return type of array, but does not specify key or value types';
            } else {
                $issue = 'PhanPluginUnknownArrayFunctionReturnType';
                $message = 'Function {FUNCTION} has a return type of array, but does not specify key or value types';
            }
            self::emitIssue(
                $code_base,
                $function->getContext(),
                $issue,
                $message,
                [(string)$function->getNameForIssue()]
            );
        }
        foreach ($function->getParameterList() as $parameter) {
            if ($parameter->getUnionType()->isEmpty()) {
                if ($function->getFQSEN()->isClosure()) {
                    $issue = 'PhanPluginUnknownClosureParamType';
                    $message = 'Closure {FUNCTION} has no declared or inferred return type for ${PARAMETER}';
                } else {
                    $issue = 'PhanPluginUnknownFunctionParamType';
                    $message = 'Function {FUNCTION} has no declared or inferred return type for ${PARAMETER}';
                }
                self::emitIssue(
                    $code_base,
                    $parameter->createContext($function),
                    $issue,
                    $message,
                    [(string)$function->getNameForIssue(), $parameter->getName()]
                );
            } elseif (self::isRegularArray($parameter->getUnionType())) {
                if ($function->getFQSEN()->isClosure()) {
                    $issue = 'PhanPluginUnknownArrayClosureParamType';
                    $message = 'Closure {FUNCTION} has a parameter type of array for ${PARAMETER}, but does not specify any key types or value types';
                } else {
                    $issue = 'PhanPluginUnknownArrayFunctionParamType';
                    $message = 'Function {FUNCTION} has a parameter type of array for ${PARAMETER}, but does not specify any key types or value types';
                }
                self::emitIssue(
                    $code_base,
                    $parameter->createContext($function),
                    $issue,
                    $message,
                    [(string)$function->getNameForIssue(), $parameter->getName()]
                );
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    ) {
        if ($property->getFQSEN() !== $property->getRealDefiningFQSEN()) {
            return;
        }
        self::performChecks(
            $code_base,
            $property,
            'PhanPluginUnknownPropertyType',
            'Property {PROPERTY} has an initial type that cannot be inferred',
            'PhanPluginUnknownArrayPropertyType',
            'Property {PROPERTY} has an array type, but does not specify any key types or value types'
        );
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnknownElementTypePlugin();
