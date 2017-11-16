<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Variable;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use ast\Node;

class ArgumentType
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
        // Special common cases where we want slightly
        // better multi-signature error messages
        self::checkIsDeprecatedOrInternal($code_base, $context, $method);
        if ($method->hasFunctionCallAnalyzer()) {
            $method->analyzeFunctionCall($code_base, $context->withLineNumberStart($node->lineno ?? 0), $node->children['args']->children ?? []);
        }

        if ($method->isPHPInternal()) {
            if (self::analyzeInternalArgumentType(
                $method,
                $node,
                $context,
                $code_base
            )) {
                // Stop analyzing if an issue was found.
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
                        (string)$method->getFQSEN(),
                        $method->getNumberOfRequiredParameters()
                    );
                } else {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooFew,
                        $node->lineno ?? 0,
                        $argcount,
                        (string)$method->getFQSEN(),
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
                $max = $method->getNumberOfParameters();
                if ($method->isPHPInternal()) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooManyInternal,
                        $node->lineno ?? 0,
                        $argcount,
                        (string)$method->getFQSEN(),
                        $max
                    );
                } else {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooMany,
                        $node->lineno ?? 0,
                        $argcount,
                        (string)$method->getFQSEN(),
                        $max,
                        $method->getFileRef()->getFile(),
                        $method->getFileRef()->getLineNumberStart()
                    );
                }
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
                    (string)$method->getFQSEN()
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
                    (string)$method->getFQSEN(),
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
                (string)$method->getFQSEN(),
                $method->getElementNamespace($code_base) ?: '\\',
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
     * @param Node[]|string[]|int[] $children
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
     * @param Node[]|string[]|int[] $arg_nodes $node
     * The node holding the arguments of the call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @param CodeBase $code_base
     * The global code base
     *
     * @param ?\Closure $get_argument_type (Node|string|int $node, int $i) -> UnionType
     * Fetches the types of individual arguments.
     */
    public static function analyzeForCallback(
        FunctionInterface $method,
        array $arg_nodes,
        Context $context,
        CodeBase $code_base,
        \Closure $get_argument_type
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
                    (string)$method->getFQSEN(),
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
                    (string)$method->getFQSEN(),
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
     * @param Node[]|string[]|int[] $arg_nodes $node
     * The node holding the arguments of the call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @param \Closure $get_argument_type (Node|string|int $node, int $i) -> UnionType
     *
     * @return void
     */
    private static function analyzeParameterListForCallback(
        CodeBase $code_base,
        FunctionInterface $method,
        array $arg_nodes,
        Context $context,
        \Closure $get_argument_type
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
            self::analyzeParameter($code_base, $context, $method, $argument_type, $argument->lineno ?? 0, $i);
        }
    }


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

        foreach ($node->children ?? [] as $i => $argument) {
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
                if ((!$argument instanceof \ast\Node)
                    || ($argument_kind !== \ast\AST_VAR
                        && $argument_kind !== \ast\AST_DIM
                        && $argument_kind !== \ast\AST_PROP
                        && $argument_kind !== \ast\AST_STATIC_PROP
                    )
                ) {
                    $is_possible_reference = self::is_function_returning_reference($code_base, $context, $argument);

                    if (!$is_possible_reference) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::TypeNonVarPassByRef,
                            $node->lineno ?? 0,
                            ($i+1),
                            (string)$method->getFQSEN()
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
                            $node->lineno ?? 0,
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
            self::analyzeParameter($code_base, $context, $method, $argument_type, $node->lineno ?? 0, $i);
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
        $argument_type_expanded =
            $argument_type->asExpandedTypes($code_base);

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
            \assert($alternate_parameter instanceof Variable);

            // See if the argument can be cast to the
            // parameter
            if ($argument_type_expanded->canCastToUnionType(
                $alternate_parameter->getUnionType()
            )) {
                return;
            }
        }

        if (!($alternate_parameter instanceof Parameter)) {
            return;  // skip type check - is this possible?
        }

        if ($alternate_parameter->isPassByReference() && $alternate_parameter->getReferenceType() === Parameter::REFERENCE_WRITE_ONLY) {
            return;
        }

        $parameter_type = $alternate_parameter->getUnionType();

        if ($parameter_type->hasTemplateType()) {
            // Don't worry about template types
            return;
        }

        if ($method->isPHPInternal()) {
            // If we are not in strict mode and we accept a string parameter
            // and the argument we are passing has a __toString method then it is ok
            if (!$context->getIsStrictTypes() && \is_object($parameter_type) && $parameter_type->hasType(StringType::instance(false))) {
                try {
                    foreach ($argument_type_expanded->asClassList($code_base, $context) as $clazz) {
                        if ($clazz->hasMethodWithName($code_base, "__toString")) {
                            return;
                        }
                    }
                } catch (CodeBaseException $e) {
                    // Swallow "Cannot find class", go on to emit issue
                }
            }
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::TypeMismatchArgumentInternal,
                $lineno,
                ($i+1),
                $alternate_parameter->getName(),
                $argument_type_expanded,
                (string)$method->getFQSEN(),
                (string)$parameter_type
            );
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            Issue::TypeMismatchArgument,
            $lineno,
            ($i+1),
            $alternate_parameter->getName(),
            $argument_type_expanded,
            (string)$method->getFQSEN(),
            (string)$parameter_type,
            $method->getFileRef()->getFile(),
            $method->getFileRef()->getLineNumberStart()
        );
    }

    /**
     * Used to check if a place expecting a reference is actually getting a reference from a node.
     * Obvious types which are always references (properties, variables) must be checked for before calling this.
     *
     * @return bool - True if this node is a call to a function that may return a reference?
     */
    private static function is_function_returning_reference(CodeBase $code_base, Context $context, $node) : bool
    {
        if (!($node instanceof Node)) {
            return false;
        }
        $node_kind = $node->kind;
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
                try {
                    foreach (UnionTypeVisitor::classListFromNodeAndContext(
                        $code_base,
                        $context,
                        $node->children['class'] ?? $node->children['expr']
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
                } catch (IssueException $e) {
                    // Swallow any issue esceptions here. They'll be caught elsewhere.
                }
            }
        }
        return false;
    }

    /**
     * Emit a log message if the type of the given
     * node cannot be cast to the given type
     *
     * @param Node|null|string|int|float $node
     * A node or whatever php-ast feels like returning
     *
     * @param Context $context
     * The context in which the node was found
     *
     * @param CodeBase $code_base
     * The code base in which the node was found
     *
     * @param UnionType $cast_type
     * The type that is being casted to
     *
     * @param \Closure $issue_instance
     * An issue to emit if the cast doesn't work.
     *
     * @return bool
     * True if the cast is possible, else false
     */
    private static function analyzeNodeUnionTypeCast(
        $node,
        Context $context,
        CodeBase $code_base,
        UnionType $cast_type,
        \Closure $issue_instance
    ) : bool {

        // Get the type of the node
        $node_type = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node,
            true
        );

        // See if it can be cast to the given type
        $can_cast = $node_type->canCastToUnionType(
            $cast_type
        );

        // If it can't, emit the log message
        if (!$can_cast) {
            Issue::maybeEmitInstance(
                $code_base,
                $context,
                $issue_instance($node_type)
            );
        }

        return $can_cast;
    }

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @param FunctionInterface $method
     * The method we're analyzing arguments for
     *
     * @param Node $node
     * The node holding the method call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @param CodeBase $code_base
     *
     * @return bool
     * Return true if an issue was found, else false.
     */
    private static function analyzeInternalArgumentType(
        FunctionInterface $method,
        Node $node,
        Context $context,
        CodeBase $code_base
    ) {
        $arglist = $node->children['args'];
        $argcount = \count($arglist->children);

        switch ($method->getName()) {
            case 'join':
            case 'implode':
                // (string glue, array pieces),
                // (array pieces, string glue) or
                // (array pieces)
                if ($argcount == 1) {
                    return !self::analyzeNodeUnionTypeCast(
                        $arglist->children[0],
                        $context,
                        $code_base,
                        ArrayType::instance(false)->asUnionType(),
                        function (UnionType $unused_node_type) use ($context, $method) {
                            // "arg#1(pieces) is %s but {$method->getFQSEN()}() takes array when passed only 1 arg"
                            return Issue::fromType(Issue::ParamSpecial2)(
                                $context->getFile(),
                                $context->getLineNumberStart(),
                                [
                                    1,
                                    'pieces',
                                    (string)$method->getFQSEN(),
                                    'string',
                                    'array'
                                ]
                            );
                        }
                    );
                } elseif ($argcount == 2) {
                    $arg1_type = UnionType::fromNode(
                        $context,
                        $code_base,
                        $arglist->children[0]
                    );

                    $arg2_type = UnionType::fromNode(
                        $context,
                        $code_base,
                        $arglist->children[1]
                    );

                    if ((string)$arg1_type == 'array') {
                        if (!$arg2_type->canCastToUnionType(
                            StringType::instance(false)->asUnionType()
                        )) {
                            Issue::maybeEmit(
                                $code_base,
                                $context,
                                Issue::ParamSpecial1,
                                $context->getLineNumberStart(),
                                2,
                                'glue',
                                (string)$arg2_type,
                                (string)$method->getFQSEN(),
                                'string',
                                1,
                                'array'
                            );
                        }
                        return true;
                    } elseif ((string)$arg1_type == 'string') {
                        if (!$arg2_type->canCastToUnionType(
                            ArrayType::instance(false)->asUnionType()
                        )) {
                            Issue::maybeEmit(
                                $code_base,
                                $context,
                                Issue::ParamSpecial1,
                                $context->getLineNumberStart(),
                                2,
                                'pieces',
                                (string)$arg2_type,
                                (string)$method->getFQSEN(),
                                'array',
                                1,
                                'string'
                            );
                        }
                        return true;
                    }
                    return false;
                }

                // Any other arg counts we will let the regular
                // checks handle
                break;
            case 'array_udiff':
            case 'array_diff_uassoc':
            case 'array_uintersect_assoc':
            case 'array_intersect_ukey':
                if ($argcount < 3) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooFewInternal,
                        $context->getLineNumberStart(),
                        $argcount,
                        (string)$method->getFQSEN(),
                        $method->getNumberOfRequiredParameters()
                    );

                    return true;
                }

                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$argcount - 1],
                    $context,
                    $code_base,
                    CallableType::instance(false)->asUnionType(),
                    function (UnionType $unused_node_type) use ($context, $method) {
                        // "The last argument to {$method->getFQSEN()} must be a callable"
                        return Issue::fromType(Issue::ParamSpecial3)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            (string)$method->getFQSEN(),
                            'callable'
                            ]
                        );
                    }
                );

                for ($i=0; $i < ($argcount - 1); $i++) {
                    self::analyzeNodeUnionTypeCast(
                        $arglist->children[$i],
                        $context,
                        $code_base,
                        CallableType::instance(false)->asUnionType(),
                        function (UnionType $node_type) use ($context, $method, $i) {
                        // "arg#".($i+1)." is %s but {$method->getFQSEN()}() takes array"
                            return Issue::fromType(Issue::ParamTypeMismatch)(
                                $context->getFile(),
                                $context->getLineNumberStart(),
                                [
                                ($i+1),
                                (string)$node_type,
                                (string)$method->getFQSEN(),
                                'array'
                                ]
                            );
                        }
                    );
                }
                return true;

            case 'array_uintersect_uassoc':
                if ($argcount < 4) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ParamTooFewInternal,
                        $context->getLineNumberStart(),
                        $argcount,
                        (string)$method->getFQSEN(),
                        $method->getNumberOfRequiredParameters()
                    );

                    return true;
                }

                // The last 2 arguments must be a callable and there
                // can be a variable number of arrays before it
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$argcount - 1],
                    $context,
                    $code_base,
                    CallableType::instance(false)->asUnionType(),
                    function (UnionType $unused_node_type) use ($context, $method) {
                    // "The last argument to {$method->getFQSEN()} must be a callable"
                        return Issue::fromType(Issue::ParamSpecial3)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            (string)$method->getFQSEN(),
                            'callable'
                            ]
                        );
                    }
                );

                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$argcount - 2],
                    $context,
                    $code_base,
                    CallableType::instance(false)->asUnionType(),
                    function (UnionType $unused_node_type) use ($context, $method) {
                    // "The second last argument to {$method->getFQSEN()} must be a callable"
                        return Issue::fromType(Issue::ParamSpecial4)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            (string)$method->getFQSEN(),
                            'callable'
                            ]
                        );
                    }
                );

                for ($i=0; $i < ($argcount-2); $i++) {
                    self::analyzeNodeUnionTypeCast(
                        $arglist->children[$i],
                        $context,
                        $code_base,
                        ArrayType::instance(false)->asUnionType(),
                        function (UnionType $node_type) use ($context, $method, $i) {
                        // "arg#".($i+1)." is %s but {$method->getFQSEN()}() takes array"
                            return Issue::fromType(Issue::ParamTypeMismatch)(
                                $context->getFile(),
                                $context->getLineNumberStart(),
                                [
                                ($i+1),
                                (string)$node_type,
                                (string)$method->getFQSEN(),
                                'array'
                                ]
                            );
                        }
                    );
                }
                return true;

            case 'strtok':
                // (string str, string token) or (string token)
                if ($argcount == 1) {
                    // If we have just one arg it must be a string token
                    self::analyzeNodeUnionTypeCast(
                        $arglist->children[0],
                        $context,
                        $code_base,
                        StringType::instance(false)->asUnionType(),
                        function (UnionType $node_type) use ($context, $method) {
                            return Issue::fromType(Issue::ParamSpecial2)(
                                $context->getFile(),
                                $context->getLineNumberStart(),
                                [
                                    1,
                                    'token',
                                    (string)$node_type,
                                    (string)$method->getFQSEN(),
                                    'string'
                                ]
                            );
                        }
                    );
                    return true;
                }
                // The arginfo check will handle the other case
                break;

            case 'min':
            case 'max':
                if ($argcount == 1) {
                    // If we have just one arg it must be an array
                    if (!self::analyzeNodeUnionTypeCast(
                        $arglist->children[0],
                        $context,
                        $code_base,
                        ArrayType::instance(false)->asUnionType(),
                        function (UnionType $node_type) use ($context, $method) {
                        // "arg#1(values) is %s but {$method->getFQSEN()}() takes array when passed only one arg"
                            return Issue::fromType(Issue::ParamSpecial2)(
                                $context->getFile(),
                                $context->getLineNumberStart(),
                                [
                                1,
                                'values',
                                (string)$node_type,
                                (string)$method->getFQSEN(),
                                'array'
                                ]
                            );
                        }
                    )) {
                        return true;
                    }
                }
                // The arginfo check will handle the other case
                break;
            default:
                break;
        }
        return false;
    }
}
