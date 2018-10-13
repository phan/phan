<?php declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
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
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        if ($method->getUnionType()->isEmpty()) {
            $this->emitIssue(
                $code_base,
                $method->getContext(),
                'PhanPluginUnknownMethodReturnType',
                'Method {METHOD} has no declared or inferred return type',
                [(string)$method->getFQSEN()]
            );
        }
        foreach ($method->getParameterList() as $parameter) {
            if ($parameter->getUnionType()->isEmpty()) {
                $this->emitIssue(
                    $code_base,
                    $parameter->createContext($method),
                    'PhanPluginUnknownMethodParamType',
                    'Method {METHOD} has no declared or inferred parameter type for ${PARAMETER}',
                    [(string)$method->getFQSEN(), $parameter->getName()]
                );
            }
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
            $this->emitIssue(
                $code_base,
                $function->getContext(),
                $issue,
                $message,
                [(string)$function->getFQSEN()]
            );
        }
        foreach ($function->getParameterList() as $parameter) {
            if ($function->getFQSEN()->isClosure()) {
                $issue = 'PhanPluginUnknownClosureParamType';
                $message = 'Closure {FUNCTION} has no declared or inferred return type for ${PARAMETER}';
            } else {
                $issue = 'PhanPluginUnknownFunctionParamType';
                $message = 'Function {FUNCTION} has no declared or inferred return type for ${PARAMETER}';
            }
            if ($parameter->getUnionType()->isEmpty()) {
                $this->emitIssue(
                    $code_base,
                    $parameter->createContext($function),
                    $issue,
                    $message,
                    [(string)$function->getFQSEN(), $parameter->getName()]
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
        if ($property->getUnionType()->isEmpty()) {
            $this->emitIssue(
                $code_base,
                $property->getContext(),
                'PhanPluginUnknownPropertyType',
                "Property {PROPERTY} has an initial type that cannot be inferred",
                [(string)$property->getFQSEN()]
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnknownElementTypePlugin();
