<?php declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast\Node;
use Closure;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\RecursionDepthException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Variable;
use Phan\Language\Type;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\PluginV2\StopParamAnalysisException;

use function is_string;

/**
 * This visitor analyzes arguments of calls to methods, functions, and closures
 * and emits issues for incorrect argument types.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
final class ArgumentType
{

    /**
     * @param FunctionInterface $method
     * The function/method we're analyzing arguments for
     *
     * @param Node $node
     * The node holding the method call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @param CodeBase $code_base
     * The global code base
     *
     * @return void
     */
    public static function analyze(
        FunctionInterface $method,
        Node $node,
        Context $context,
        CodeBase $code_base
    ) {
        self::checkIsDeprecatedOrInternal($code_base, $context, $method);
        if ($method->hasFunctionCallAnalyzer()) {
            try {
                $method->analyzeFunctionCall($code_base, $context->withLineNumberStart($node->lineno ?? 0), $node->children['args']->children);
            } catch (StopParamAnalysisException $_) {
                return;
            }
        }

        // Emit an issue if this is an externally accessed internal method
        $arglist = $node->children['args'];
        $argcount = \count($arglist->children);

        // Make sure we have enough arguments
        if ($argcount < $method->getNumberOfRequiredParameters() && !self::isUnpack($arglist->children)) {
            $alternate_found = false;
            foreach ($method->alternateGenerator($code_base) as $alternate_method) {
                $alternate_found = $alternate_found || (
                    $argcount >=
                    $alternate_method->getNumberOfRequiredParameters()
                );
            }

            if (!$alternate_found) {
                if ($method->isPHPInternal()) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooFewInternal,
                        $node->lineno ?? 0,
                        $argcount,
                        $method->getRepresentationForIssue(),
                        $method->getNumberOfRequiredParameters()
                    );
                } else {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooFew,
                        $node->lineno ?? 0,
                        $argcount,
                        $method->getRepresentationForIssue(),
                        $method->getNumberOfRequiredParameters(),
                        $method->getFileRef()->getFile(),
                        $method->getFileRef()->getLineNumberStart()
                    );
                }
            }
        }

        // Make sure we don't have too many arguments
        if ($argcount > $method->getNumberOfParameters() && !self::isVarargs($code_base, $method)) {
            $alternate_found = false;
            foreach ($method->alternateGenerator($code_base) as $alternate_method) {
                if ($argcount <= $alternate_method->getNumberOfParameters()) {
                    $alternate_found = true;
                    break;
                }
            }

            if (!$alternate_found) {
                self::emitParamTooMany($code_base, $context, $method, $node, $argcount);
            }
        }

        // Check the parameter types
        self::analyzeParameterList(
            $code_base,
            $method,
            $arglist,
            $context
        );
    }

    private static function emitParamTooMany(
        CodeBase $code_base,
        Context $context,
        FunctionInterface $method,
        Node $node,
        int $argcount
    ) {
        $max = $method->getNumberOfParameters();
        $caused_by_variadic = $argcount === $max + 1 && (\end($node->children['args']->children)->kind ?? null) === \ast\AST_UNPACK;
        if ($method->isPHPInternal()) {
            Issue::maybeEmit(
                $code_base,
                $context,
                $caused_by_variadic ? Issue::ParamTooManyUnpackInternal : Issue::ParamTooManyInternal,
                $node->lineno ?? 0,
                $caused_by_variadic ? $max : $argcount,
                $method->getRepresentationForIssue(),
                $max
            );
        } else {
            Issue::maybeEmit(
                $code_base,
                $context,
                $caused_by_variadic ? Issue::ParamTooManyUnpack : Issue::ParamTooMany,
                $node->lineno ?? 0,
                $caused_by_variadic ? $max : $argcount,
                $method->getRepresentationForIssue(),
                $max,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart()
            );
        }
    }

    /**
     * @return void
     */
    private static function checkIsDeprecatedOrInternal(CodeBase $code_base, Context $context, FunctionInterface $method)
    {
        // Special common cases where we want slightly
        // better multi-signature error messages
        if ($method->isPHPInternal()) {
            // Emit an error if this internal method is marked as deprecated
            if ($method->isDeprecated()) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::DeprecatedFunctionInternal,
                    $context->getLineNumberStart(),
                    $method->getRepresentationForIssue()
                );
            }
        } else {
            // Emit an error if this user-defined method is marked as deprecated
            if ($method->isDeprecated()) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::DeprecatedFunction,
                    $context->getLineNumberStart(),
                    $method->getRepresentationForIssue(),
                    $method->getFileRef()->getFile(),
                    $method->getFileRef()->getLineNumberStart()
                );
            }
        }

        // Emit an issue if this is an externally accessed internal method
        if ($method->isNSInternal($code_base)
            && !$method->isNSInternalAccessFromContext(
                $code_base,
                $context
            )
        ) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::AccessMethodInternal,
                $context->getLineNumberStart(),
                $method->getRepresentationForIssue(),
                $method->getElementNamespace() ?: '\\',
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart(),
                ($context->getNamespace()) ?: '\\'
            );
        }
    }

    private static function isVarargs(CodeBase $code_base, FunctionInterface $method) : bool
    {
        foreach ($method->alternateGenerator($code_base) as $alternate_method) {
            foreach ($alternate_method->getParameterList() as $parameter) {
                if ($parameter->isVariadic()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Figure out if any of the arguments are a call to unpack()
     * @param array<mixed,Node|int|string|float> $children
     */
    private static function isUnpack(array $children) : bool
    {
        foreach ($children as $child) {
            if ($child instanceof Node) {
                if ($child->kind === \ast\AST_UNPACK) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param FunctionInterface $method
     * The function/method we're analyzing arguments for
     *
     * @param array<int,Node|string|int|float> $arg_nodes $node
     * The node holding the arguments of the call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @param CodeBase $code_base
     * The global code base
     *
     * @param Closure $get_argument_type (Node|string|int $node, int $i) -> UnionType
     * Fetches the types of individual arguments.
     */
    public static function analyzeForCallback(
        FunctionInterface $method,
        array $arg_nodes,
        Context $context,
        CodeBase $code_base,
        Closure $get_argument_type
    ) {
        // Special common cases where we want slightly
        // better multi-signature error messages
        self::checkIsDeprecatedOrInternal($code_base, $context, $method);
        // TODO: analyzeInternalArgumentType

        $argcount = \count($arg_nodes);

        // Make sure we have enough arguments
        if ($argcount < $method->getNumberOfRequiredParameters() && !self::isUnpack($arg_nodes)) {
            $alternate_found = false;
            foreach ($method->alternateGenerator($code_base) as $alternate_method) {
                $alternate_found = $alternate_found || (
                    $argcount >=
                    $alternate_method->getNumberOfRequiredParameters()
                );
            }

            if (!$alternate_found) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::ParamTooFewCallable,
                    $context->getLineNumberStart(),
                    $argcount,
                    $method->getRepresentationForIssue(),
                    $method->getNumberOfRequiredParameters(),
                    $method->getFileRef()->getFile(),
                    $method->getFileRef()->getLineNumberStart()
                );
            }
        }

        // Make sure we don't have too many arguments
        if ($argcount > $method->getNumberOfParameters() && !self::isVarargs($code_base, $method)) {
            $alternate_found = false;
            foreach ($method->alternateGenerator($code_base) as $alternate_method) {
                if ($argcount <= $alternate_method->getNumberOfParameters()) {
                    $alternate_found = true;
                    break;
                }
            }

            if (!$alternate_found) {
                $max = $method->getNumberOfParameters();
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::ParamTooManyCallable,
                    $context->getLineNumberStart(),
                    $argcount,
                    $method->getRepresentationForIssue(),
                    $max,
                    $method->getFileRef()->getFile(),
                    $method->getFileRef()->getLineNumberStart()
                );
            }
        }

        // Check the parameter types
        self::analyzeParameterListForCallback(
            $code_base,
            $method,
            $arg_nodes,
            $context,
            $get_argument_type
        );
    }

    /**
     * @param CodeBase $code_base
     * The global code base
     *
     * @param FunctionInterface $method
     * The method we're analyzing arguments for
     *
     * @param array<int,Node|string|int|float> $arg_nodes $node
     * The node holding the arguments of the call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @param Closure $get_argument_type (Node|string|int $node, int $i) -> UnionType
     *
     * @return void
     */
    private static function analyzeParameterListForCallback(
        CodeBase $code_base,
        FunctionInterface $method,
        array $arg_nodes,
        Context $context,
        Closure $get_argument_type
    ) {
        // There's nothing reasonable we can do here
        if ($method instanceof Method) {
            if ($method->getIsMagicCall() || $method->getIsMagicCallStatic()) {
                return;
            }
        }

        foreach ($arg_nodes as $i => $argument) {
            // Get the parameter associated with this argument
            $parameter = $method->getParameterForCaller($i);

            // This issue should be caught elsewhere
            if (!$parameter) {
                continue;
            }

            // TODO: Warnings about call-by-reference are different for array_map, etc.

            // Get the type of the argument. We'll check it against
            // the parameter in a moment
            $argument_type = $get_argument_type($argument, $i);
            self::analyzeParameter($code_base, $context, $method, $argument_type, $argument->lineno ?? $context->getLineNumberStart(), $i);
            if ($parameter->isPassByReference()) {
                if ($argument instanceof Node) {
                    // @phan-suppress-next-line PhanUndeclaredProperty this is added for analyzers
                    $argument->is_reference = true;
                }
            }
        }
    }

    /**
     * These node types are guaranteed to be usable as references
     * @internal
     */
    const REFERENCE_NODE_KINDS = [
        \ast\AST_VAR,
        \ast\AST_DIM,
        \ast\AST_PROP,
        \ast\AST_STATIC_PROP,
    ];

    /**
     * @param CodeBase $code_base
     * The global code base
     *
     * @param FunctionInterface $method
     * The method we're analyzing arguments for
     *
     * @param Node $node
     * The node holding the arguments of the function/method call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @return void
     */
    private static function analyzeParameterList(
        CodeBase $code_base,
        FunctionInterface $method,
        Node $node,
        Context $context
    ) {
        // There's nothing reasonable we can do here
        if ($method instanceof Method) {
            if ($method->getIsMagicCall() || $method->getIsMagicCallStatic()) {
                return;
            }
        }

        foreach ($node->children as $i => $argument) {
            if (!\is_int($i)) {
                throw new AssertionError("Expected argument index to be an integer");
            }

            // Get the parameter associated with this argument
            $parameter = $method->getParameterForCaller($i);

            // This issue should be caught elsewhere
            if (!$parameter) {
                continue;
            }

            $argument_kind = $argument->kind ?? 0;

            // If this is a pass-by-reference parameter, make sure
            // we're passing an allowable argument
            if ($parameter->isPassByReference()) {
                if ((!$argument instanceof Node) || !\in_array($argument_kind, self::REFERENCE_NODE_KINDS, true)) {
                    $is_possible_reference = self::isExpressionReturningReference($code_base, $context, $argument);

                    if (!$is_possible_reference) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::TypeNonVarPassByRef,
                            $argument->lineno ?? $node->lineno ?? 0,
                            ($i + 1),
                            $method->getRepresentationForIssue()
                        );
                    }
                } else {
                    $variable_name = (new ContextNode(
                        $code_base,
                        $context,
                        $argument
                    ))->getVariableName();

                    if (Type::isSelfTypeString($variable_name)
                        && !$context->isInClassScope()
                        && ($argument_kind === \ast\AST_STATIC_PROP || $argument_kind === \ast\AST_PROP)
                    ) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::ContextNotObject,
                            $argument->lineno ?? $node->lineno ?? 0,
                            "$variable_name"
                        );
                    }
                }
            }

            // Get the type of the argument. We'll check it against
            // the parameter in a moment
            $argument_type = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $argument,
                true
            );
            self::analyzeParameter($code_base, $context, $method, $argument_type, $argument->lineno ?? $node->lineno ?? 0, $i);
            if ($parameter->isPassByReference()) {
                if ($argument instanceof Node) {
                    // @phan-suppress-next-line PhanUndeclaredProperty this is added for analyzers
                    $argument->is_reference = true;
                }
            }
            if ($argument_kind === \ast\AST_UNPACK) {
                self::analyzeRemainingParametersForVariadic($code_base, $context, $method, $i + 1, $node, $argument, $argument_type);
            }
        }
    }

    /**
     * @return void
     */
    private static function analyzeRemainingParametersForVariadic(
        CodeBase $code_base,
        Context $context,
        FunctionInterface $method,
        int $start_index,
        Node $node,
        Node $argument,
        UnionType $argument_type
    ) {
        // Check the remaining required parameters for this variadic argument.
        // To avoid false positives, don't check optional parameters for now.

        // TODO: Could do better (e.g. warn about too few/many params, warn about individual types)
        // if the array shape type is known or available in phpdoc.
        $param_count = $method->getNumberOfRequiredParameters();
        for ($i = $start_index; $i < $param_count; $i++) {
            // Get the parameter associated with this argument
            $parameter = $method->getParameterForCaller($i);

            // Shouldn't be possible?
            if (!$parameter) {
                return;
            }

            $argument_kind = $argument->kind;

            // If this is a pass-by-reference parameter, make sure
            // we're passing an allowable argument
            if ($parameter->isPassByReference()) {
                if (!\in_array($argument_kind, self::REFERENCE_NODE_KINDS, true)) {
                    $is_possible_reference = self::isExpressionReturningReference($code_base, $context, $argument);

                    if (!$is_possible_reference) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::TypeNonVarPassByRef,
                            $argument->lineno ?? $node->lineno ?? 0,
                            ($i + 1),
                            $method->getRepresentationForIssue()
                        );
                    }
                }
                // Omit ContextNotObject check, this was checked for the first matching parameter
            }

            self::analyzeParameter($code_base, $context, $method, $argument_type, $argument->lineno, $i);
            if ($parameter->isPassByReference()) {
                // @phan-suppress-next-line PhanUndeclaredProperty this is added for analyzers
                $argument->is_reference = true;
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param FunctionInterface $method
     * @param UnionType $argument_type
     * @param int $lineno
     * @return void
     */
    public static function analyzeParameter(CodeBase $code_base, Context $context, FunctionInterface $method, UnionType $argument_type, int $lineno, int $i)
    {
        // Expand it to include all parent types up the chain
        try {
            $argument_type_expanded =
                $argument_type->asExpandedTypes($code_base);
        } catch (RecursionDepthException $_) {
            return;
        }

        // Check the method to see if it has the correct
        // parameter types. If not, keep hunting through
        // alternates of the method until we find one that
        // takes the correct types
        $alternate_parameter = null;

        foreach ($method->alternateGenerator($code_base) as $alternate_method) {
            // Get the parameter associated with this argument
            $candidate_alternate_parameter = $alternate_method->getParameterForCaller($i);
            if (\is_null($candidate_alternate_parameter)) {
                continue;
            }

            $alternate_parameter = $candidate_alternate_parameter;
            if (!($alternate_parameter instanceof Variable)) {
                throw new AssertionError('Expected alternate_parameter to be Variable or subclass');
            }

            // See if the argument can be cast to the
            // parameter
            if ($argument_type_expanded->canCastToUnionType(
                $alternate_parameter->getNonVariadicUnionType()
            )) {
                if (Config::get_strict_param_checking() && $argument_type->typeCount() > 1) {
                    self::analyzeParameterStrict($code_base, $context, $method, $argument_type, $alternate_parameter, $lineno, $i);
                }
                return;
            }
        }

        if (!($alternate_parameter instanceof Parameter)) {
            return;  // skip type check - is this possible?
        }

        if ($alternate_parameter->isPassByReference() && $alternate_parameter->getReferenceType() === Parameter::REFERENCE_WRITE_ONLY) {
            return;
        }

        $parameter_type = $alternate_parameter->getNonVariadicUnionType();

        if ($parameter_type->hasTemplateTypeRecursive()) {
            // Don't worry about **unresolved** template types.
            // We resolve them if possible in ContextNode->getMethod()
            return;
        }
        if ($parameter_type->hasTemplateParameterTypes()) {
            // TODO: Make the check for templates recursive
            $argument_type_expanded_templates = $argument_type->asExpandedTypesPreservingTemplate($code_base);
            if ($argument_type_expanded_templates->canCastToUnionTypeHandlingTemplates($parameter_type, $code_base)) {
                // - can cast MyClass<\stdClass> to MyClass<mixed>
                // - can cast Some<\stdClass> to Option<\stdClass>
                // - cannot cast Some<\SomeOtherClass> to Option<\stdClass>
                return;
            }
            // echo "Debug: $argument_type $argument_type_expanded_templates cannot cast to $parameter_type\n";
        }

        if ($method->isPHPInternal()) {
            // If we are not in strict mode and we accept a string parameter
            // and the argument we are passing has a __toString method then it is ok
            if (!$context->getIsStrictTypes() && $parameter_type->hasNonNullStringType()) {
                try {
                    foreach ($argument_type_expanded->asClassList($code_base, $context) as $clazz) {
                        if ($clazz->hasMethodWithName($code_base, "__toString")) {
                            return;
                        }
                    }
                } catch (CodeBaseException $_) {
                    // Swallow "Cannot find class", go on to emit issue
                }
            }
        }
        // Check suppressions and emit the issue
        self::warnInvalidArgumentType($code_base, $context, $method, $alternate_parameter, $argument_type_expanded, $lineno, $i);
    }

    /**
     * @return void
     */
    private static function warnInvalidArgumentType(
        CodeBase $code_base,
        Context $context,
        FunctionInterface $method,
        Parameter $alternate_parameter,
        UnionType $argument_type_expanded,
        int $lineno,
        int $i
    ) {
        $parameter_type = $alternate_parameter->getNonVariadicUnionType();
        /**
         * @return ?string
         */
        $choose_issue_type = static function (string $issue_type, string $nullable_issue_type) use ($argument_type_expanded, $parameter_type, $code_base, $context, $lineno) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            if (!$argument_type_expanded->canCastToUnionTypeIfNonNull($parameter_type)) {
                return $issue_type;
            }
            if (Issue::shouldSuppressIssue($code_base, $context, $issue_type, $lineno, [])) {
                return null;
            }
            return $nullable_issue_type;
        };

        if ($method->isPHPInternal()) {
            $issue_type = $choose_issue_type(Issue::TypeMismatchArgumentInternal, Issue::TypeMismatchArgumentNullableInternal);
            if (!$issue_type) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $context,
                // @phan-suppress-next-line PhanAccessMethodInternal
                $issue_type,
                $lineno,
                ($i + 1),
                $alternate_parameter->getName(),
                $argument_type_expanded,
                $method->getRepresentationForIssue(),
                (string)$parameter_type
            );
            return;
        }
        $issue_type = $choose_issue_type(Issue::TypeMismatchArgument, Issue::TypeMismatchArgumentNullable);
        if (!$issue_type) {
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            $issue_type,
            $lineno,
            ($i + 1),
            $alternate_parameter->getName(),
            $argument_type_expanded,
            $method->getRepresentationForIssue(),
            (string)$parameter_type,
            $method->getFileRef()->getFile(),
            $method->getFileRef()->getLineNumberStart()
        );
    }

    private static function analyzeParameterStrict(CodeBase $code_base, Context $context, FunctionInterface $method, UnionType $argument_type, Variable $alternate_parameter, int $lineno, int $i)
    {
        if ($alternate_parameter instanceof Parameter && $alternate_parameter->isPassByReference() && $alternate_parameter->getReferenceType() === Parameter::REFERENCE_WRITE_ONLY) {
            return;
        }
        $type_set = $argument_type->getTypeSet();
        if (\count($type_set) < 2) {
            throw new AssertionError("Expected to have at least two parameter types when checking if parameter types match in strict mode");
        }

        $parameter_type = $alternate_parameter->getNonVariadicUnionType();

        $mismatch_type_set = UnionType::empty();
        $mismatch_expanded_types = null;

        // For the strict
        foreach ($type_set as $type) {
            // Expand it to include all parent types up the chain
            $individual_type_expanded = $type->asExpandedTypes($code_base);

            // See if the argument can be cast to the
            // parameter
            if (!$individual_type_expanded->canCastToUnionType(
                $parameter_type
            )) {
                if ($method->isPHPInternal()) {
                    // If we are not in strict mode and we accept a string parameter
                    // and the argument we are passing has a __toString method then it is ok
                    if (!$context->getIsStrictTypes() && $parameter_type->hasNonNullStringType()) {
                        if ($individual_type_expanded->hasClassWithToStringMethod($code_base, $context)) {
                            continue;  // don't warn about $type
                        }
                    }
                }
                $mismatch_type_set = $mismatch_type_set->withType($type);
                if ($mismatch_expanded_types === null) {
                    // Warn about the first type
                    $mismatch_expanded_types = $individual_type_expanded;
                }
            }
        }


        if ($mismatch_expanded_types === null) {
            // No mismatches
            return;
        }

        if ($method->isPHPInternal()) {
            Issue::maybeEmit(
                $code_base,
                $context,
                self::getStrictArgumentIssueType($mismatch_type_set, true),
                $lineno,
                ($i + 1),
                $alternate_parameter->getName(),
                $argument_type,
                $method->getRepresentationForIssue(),
                (string)$parameter_type,
                $mismatch_expanded_types
            );
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            self::getStrictArgumentIssueType($mismatch_type_set, false),
            $lineno,
            ($i + 1),
            $alternate_parameter->getName(),
            $argument_type,
            $method->getRepresentationForIssue(),
            (string)$parameter_type,
            $mismatch_expanded_types,
            $method->getFileRef()->getFile(),
            $method->getFileRef()->getLineNumberStart()
        );
    }

    private static function getStrictArgumentIssueType(UnionType $union_type, bool $is_internal) : string
    {
        if ($union_type->typeCount() === 1) {
            $type = $union_type->getTypeSet()[0];
            if ($type instanceof NullType) {
                return $is_internal ? Issue::PossiblyNullTypeArgumentInternal : Issue::PossiblyNullTypeArgument;
            }
            if ($type instanceof FalseType) {
                return $is_internal ? Issue::PossiblyFalseTypeArgumentInternal : Issue::PossiblyFalseTypeArgument;
            }
        }
        return $is_internal ? Issue::PartialTypeMismatchArgumentInternal : Issue::PartialTypeMismatchArgument;
    }

    /**
     * Used to check if a place expecting a reference is actually getting a reference from a node.
     * Obvious types which are always references (properties, variables) must be checked for before calling this.
     *
     * @param Node|string|int|float $node
     *
     * @return bool - True if this node is a call to a function that may return a reference?
     */
    private static function isExpressionReturningReference(CodeBase $code_base, Context $context, $node) : bool
    {
        if (!($node instanceof Node)) {
            return false;
        }
        $node_kind = $node->kind;
        if (\in_array($node_kind, self::REFERENCE_NODE_KINDS, true)) {
            return true;
        }
        if ($node_kind === \ast\AST_UNPACK) {
            return self::isExpressionReturningReference($code_base, $context, $node->children['expr']);
        }
        if ($node_kind === \ast\AST_CALL) {
            foreach ((new ContextNode(
                $code_base,
                $context,
                $node->children['expr']
            ))->getFunctionFromNode() as $function) {
                if ($function->returnsRef()) {
                    return true;
                }
            }
        } elseif ($node_kind === \ast\AST_STATIC_CALL || $node_kind === \ast\AST_METHOD_CALL) {
            $method_name = $node->children['method'] ?? null;
            if (is_string($method_name)) {
                $class_node = $node->children['class'] ?? $node->children['expr'];
                if (!($class_node instanceof Node)) {
                    return false;
                }
                try {
                    foreach (UnionTypeVisitor::classListFromNodeAndContext(
                        $code_base,
                        $context,
                        $class_node
                    ) as $class) {
                        if (!$class->hasMethodWithName(
                            $code_base,
                            $method_name
                        )) {
                            continue;
                        }

                        $method = $class->getMethodByName(
                            $code_base,
                            $method_name
                        );
                        // Return true if any of the possible methods (expect that just one is found) returns a reference.
                        if ($method->returnsRef()) {
                            return true;
                        }
                    }
                } catch (IssueException $_) {
                    // Swallow any issue exceptions here. They'll be caught elsewhere.
                }
            }
        }
        return false;
    }
}
