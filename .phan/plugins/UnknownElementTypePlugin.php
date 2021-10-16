<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AnalyzePropertyCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\Suggestion;

/**
 * This file checks if any elements in the codebase have undeclared types.
 */
class UnknownElementTypePlugin extends PluginV3 implements
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AnalyzePropertyCapability,
    FinalizeProcessCapability
{
    /**
     * A list of closures to execute before emitting issues.
     * @var array<string,Closure(CodeBase):void>
     */
    private $deferred_checks = [];

    /**
     * Returns true for array, ?array, and array|null
     */
    private static function isRegularArray(UnionType $type): bool
    {
        return $type->hasTypeMatchingCallback(static function (Type $type): bool {
            return get_class($type) === ArrayType::class;
        }) && !$type->hasTypeMatchingCallback(static function (Type $type): bool {
            return get_class($type) !== ArrayType::class && !($type instanceof NullType);
        });
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ): void {
        if ($method->getFQSEN() !== $method->getRealDefiningFQSEN()) {
            return;
        }

        $this->performChecks(
            $method,
            'PhanPluginUnknownMethodReturnType',
            'Method {METHOD} has no declared or inferred return type',
            'PhanPluginUnknownArrayMethodReturnType',
            'Method {METHOD} has a return type of array, but does not specify any key types or value types'
        );
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        $warning_closures = [];
        $inferred_types = [];
        foreach ($method->getParameterList() as $i => $parameter) {
            if ($parameter->getUnionType()->isEmpty()) {
                $warning_closures[$i] = static function () use ($code_base, $parameter, $method, $i, &$inferred_types): void {
                    $suggestion = self::suggestionFromUnionType($inferred_types[$i] ?? null);
                    self::emitIssueAndSuggestion(
                        $code_base,
                        $parameter->createContext($method),
                        'PhanPluginUnknownMethodParamType',
                        'Method {METHOD} has no declared or inferred parameter type for ${PARAMETER}',
                        [(string)$method->getFQSEN(), $parameter->getName()],
                        $suggestion
                    );
                };
            } elseif (self::isRegularArray($parameter->getUnionType())) {
                $warning_closures[$i] = static function () use ($code_base, $parameter, $method, $i, &$inferred_types): void {
                    $suggestion = self::suggestionFromUnionTypeNotRegularArray($inferred_types[$i] ?? null);
                    self::emitIssueAndSuggestion(
                        $code_base,
                        $parameter->createContext($method),
                        'PhanPluginUnknownArrayMethodParamType',
                        'Method {METHOD} has a parameter type of array for ${PARAMETER}, but does not specify any key types or value types',
                        [(string)$method->getFQSEN(), $parameter->getName()],
                        $suggestion
                    );
                };
            }
        }
        if (!$warning_closures) {
            return;
        }
        $this->deferred_checks[$method->getFQSEN()->__toString()] = static function (CodeBase $_) use ($warning_closures): void {
            foreach ($warning_closures as $cb) {
                $cb();
            }
        };
        $method->addFunctionCallAnalyzer(
            /**
             * @param list<mixed> $args
             */
            static function (CodeBase $code_base, Context $context, Method $unused_method, array $args, Node $unused_node) use ($warning_closures, &$inferred_types): void {
                foreach ($warning_closures as $i => $_) {
                    $parameter = $args[$i] ?? null;
                    if ($parameter !== null) {
                        $parameter_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $parameter);
                        if ($parameter_type->isEmpty()) {
                            return;
                        }
                        $combined_type = $inferred_types[$i] ?? null;
                        if ($combined_type instanceof UnionType) {
                            $combined_type = $combined_type->withUnionType($parameter_type);
                        } else {
                            $combined_type = $parameter_type;
                        }
                        $inferred_types[$i] = $combined_type;
                    }
                }
            },
            $this
        );
    }

    private static function suggestionFromUnionType(?UnionType $type): ?Suggestion
    {
        if (!$type || $type->isEmpty()) {
            return null;
        }
        $type = $type->withFlattenedArrayShapeOrLiteralTypeInstances()->asNormalizedTypes();
        return Suggestion::fromString("Types inferred after analysis: $type");
    }

    private static function suggestionFromUnionTypeNotRegularArray(?UnionType $type): ?Suggestion
    {
        if (!$type || $type->isEmpty()) {
            return null;
        }
        if (self::isRegularArray($type)) {
            return null;
        }
        $type = $type->withFlattenedArrayShapeOrLiteralTypeInstances()->asNormalizedTypes();
        return Suggestion::fromString("Types inferred after analysis: $type");
    }

    private function performChecks(
        AddressableElement $element,
        string $issue_type_for_empty,
        string $message_for_empty,
        string $issue_type_for_unknown_array,
        string $message_for_unknown_array
    ): void {
        $union_type = $element->getUnionType();
        if ($union_type->isEmpty()) {
            $issue_type = $issue_type_for_empty;
            $message = $message_for_empty;
        } elseif (self::isRegularArray($union_type)) {
            $issue_type = $issue_type_for_unknown_array;
            $message = $message_for_unknown_array;
        } else {
            return;
        }
        $this->deferred_checks[$issue_type . ':' . $element->getFQSEN()->__toString()] = static function (CodeBase $code_base) use ($element, $issue_type, $message, $issue_type_for_unknown_array): void {
            $new_union_type = $element->getUnionType();
            $suggestion = null;
            if (!$new_union_type->isEmpty()) {
                if ($issue_type !== $issue_type_for_unknown_array || !self::isRegularArray($new_union_type)) {
                    $suggestion = self::suggestionFromUnionType($new_union_type);
                }
            }
            self::emitIssueAndSuggestion(
                $code_base,
                $element->getContext(),
                $issue_type,
                $message,
                [$element->getRepresentationForIssue()],
                $suggestion
            );
        };
    }

    /**
     * @param list<string|FQSEN> $args
     */
    private static function emitIssueAndSuggestion(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        string $message,
        array $args,
        ?Suggestion $suggestion
    ): void {
        self::emitIssue(
            $code_base,
            $context,
            $issue_type,
            $message,
            $args,
            Issue::SEVERITY_NORMAL,
            Issue::REMEDIATION_B,
            Issue::TYPE_ID_UNKNOWN,
            $suggestion
        );
    }


    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ): void {
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        if ($function->getUnionType()->isEmpty()) {
            if ($function->getFQSEN()->isClosure()) {
                $issue = 'PhanPluginUnknownClosureReturnType';
                $message = 'Closure {FUNCTION} has no declared or inferred return type';
            } else {
                $issue = 'PhanPluginUnknownFunctionReturnType';
                $message = 'Function {FUNCTION} has no declared or inferred return type';
            }
            $this->deferred_checks[$issue . ':' . $function->getFQSEN()->__toString()] = static function (CodeBase $code_base) use ($function, $issue, $message): void {
                $new_union_type = $function->getUnionType();
                $suggestion = self::suggestionFromUnionType($new_union_type);
                self::emitIssue(
                    $code_base,
                    $function->getContext(),
                    $issue,
                    $message,
                    [$function->getRepresentationForIssue()],
                    Issue::SEVERITY_NORMAL,
                    Issue::REMEDIATION_B,
                    Issue::TYPE_ID_UNKNOWN,
                    $suggestion
                );
            };
        } elseif (self::isRegularArray($function->getUnionType())) {
            if ($function->getFQSEN()->isClosure()) {
                $issue = 'PhanPluginUnknownArrayClosureReturnType';
                $message = 'Closure {FUNCTION} has a return type of array, but does not specify key or value types';
            } else {
                $issue = 'PhanPluginUnknownArrayFunctionReturnType';
                $message = 'Function {FUNCTION} has a return type of array, but does not specify key or value types';
            }
            $this->deferred_checks[$issue . ':' . $function->getFQSEN()->__toString()] = static function (CodeBase $code_base) use ($function, $issue, $message): void {
                $new_union_type = $function->getUnionType();
                $suggestion = self::suggestionFromUnionTypeNotRegularArray($new_union_type);
                self::emitIssue(
                    $code_base,
                    $function->getContext(),
                    $issue,
                    $message,
                    [$function->getRepresentationForIssue()],
                    Issue::SEVERITY_NORMAL,
                    Issue::REMEDIATION_B,
                    Issue::TYPE_ID_UNKNOWN,
                    $suggestion
                );
            };
        }
        $warning_closures = [];
        $inferred_types = [];
        foreach ($function->getParameterList() as $i => $parameter) {
            if ($parameter->getUnionType()->isEmpty()) {
                if ($function->getFQSEN()->isClosure()) {
                    $issue = 'PhanPluginUnknownClosureParamType';
                    $message = 'Closure {FUNCTION} has no declared or inferred parameter type for ${PARAMETER}';
                } else {
                    $issue = 'PhanPluginUnknownFunctionParamType';
                    $message = 'Function {FUNCTION} has no declared or inferred parameter type for ${PARAMETER}';
                }
                $warning_closures[$i] = static function () use ($code_base, $issue, $message, $parameter, $function, $i, &$inferred_types): void {
                    $suggestion = self::suggestionFromUnionType($inferred_types[$i] ?? null);
                    self::emitIssueAndSuggestion(
                        $code_base,
                        $parameter->createContext($function),
                        $issue,
                        $message,
                        [$function->getNameForIssue(), $parameter->getName()],
                        $suggestion
                    );
                };
            } elseif (self::isRegularArray($parameter->getUnionType())) {
                if ($function->getFQSEN()->isClosure()) {
                    $issue = 'PhanPluginUnknownArrayClosureParamType';
                    $message = 'Closure {FUNCTION} has a parameter type of array for ${PARAMETER}, but does not specify any key types or value types';
                } else {
                    $issue = 'PhanPluginUnknownArrayFunctionParamType';
                    $message = 'Function {FUNCTION} has a parameter type of array for ${PARAMETER}, but does not specify any key types or value types';
                }
                $warning_closures[$i] = static function () use ($code_base, $issue, $message, $parameter, $function, $i, &$inferred_types): void {
                    $suggestion = self::suggestionFromUnionType($inferred_types[$i] ?? null);
                    self::emitIssueAndSuggestion(
                        $code_base,
                        $parameter->createContext($function),
                        $issue,
                        $message,
                        [$function->getNameForIssue(), $parameter->getName()],
                        $suggestion
                    );
                };
            }
        }
        if (!$warning_closures) {
            return;
        }
        $this->deferred_checks[$function->getFQSEN()->__toString()] = static function (CodeBase $_) use ($warning_closures): void {
            foreach ($warning_closures as $cb) {
                $cb();
            }
        };
        $function->addFunctionCallAnalyzer(
            /**
             * @param list<mixed> $args
             */
            static function (CodeBase $code_base, Context $context, Func $unused_function, array $args, Node $unused_node) use ($warning_closures, &$inferred_types): void {
                foreach ($warning_closures as $i => $_) {
                    $parameter = $args[$i] ?? null;
                    if ($parameter !== null) {
                        $parameter_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $parameter);
                        if ($parameter_type->isEmpty()) {
                            return;
                        }
                        $combined_type = $inferred_types[$i] ?? null;
                        if ($combined_type instanceof UnionType) {
                            $combined_type = $combined_type->withUnionType($parameter_type);
                        } else {
                            $combined_type = $parameter_type;
                        }
                        $inferred_types[$i] = $combined_type;
                    }
                }
            },
            $this
        );
    }

    /**
     * @param CodeBase $code_base @unused-param
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     * @override
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    ): void {
        if ($property->getFQSEN() !== $property->getRealDefiningFQSEN()) {
            return;
        }
        $this->performChecks(
            $property,
            'PhanPluginUnknownPropertyType',
            'Property {PROPERTY} has an initial type that cannot be inferred',
            'PhanPluginUnknownArrayPropertyType',
            'Property {PROPERTY} has an array type, but does not specify any key types or value types'
        );
    }

    public function finalizeProcess(CodeBase $code_base): void
    {
        try {
            foreach ($this->deferred_checks as $check) {
                $check($code_base);
            }
        } finally {
            // There were errors in unit tests if this wasn't cleared.
            $this->deferred_checks = [];
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnknownElementTypePlugin();
