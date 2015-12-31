<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\AST\ContextNode;
use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Exception\CodeBaseException;
use \Phan\Language\Context;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Parameter;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Language\Type\ArrayType;
use \Phan\Language\Type\BoolType;
use \Phan\Language\Type\CallableType;
use \Phan\Language\Type\FloatType;
use \Phan\Language\Type\GenericArrayType;
use \Phan\Language\Type\IntType;
use \Phan\Language\Type\MixedType;
use \Phan\Language\Type\NativeType;
use \Phan\Language\Type\NullType;
use \Phan\Language\Type\ObjectType;
use \Phan\Language\Type\ResourceType;
use \Phan\Language\Type\ScalarType;
use \Phan\Language\Type\StringType;
use \Phan\Language\Type\VoidType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

class ArgumentType {

    /**
     * @param Method $method
     * The method we're analyzing arguments for
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
     * @return null
     *
     * @see \Phan\Deprecated\Pass2::arg_check
     * Formerly `function arg_check`
     */
    public static function analyze(
        Method $method,
        Node $node,
        Context $context,
        CodeBase $code_base
    ) {
        // Special common cases where we want slightly
        // better multi-signature error messages
        if($method->getContext()->isInternal()) {
            self::analyzeInternalArgumentType(
                $method,
                $node,
                $context,
                $code_base
            );
        }

        // Emit an error if this method is marked as deprecated
        if($method->isDeprecated()) {
            Log::err(
                Log::EDEP,
                "Call to deprecated function {$method->getFQSEN()}() defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                $context->getFile(),
                $context->getLineNumberStart()
            );
        }

        $arglist = $node->children['args'];
        $argcount = count($arglist->children);

        // Figure out if any version of this method has any
        // parameters that are variadic
        $is_varargs = array_reduce(
            iterator_to_array($method->alternateGenerator($code_base)),
            function (bool $carry, Method $alternate_method) : bool {
                return $carry || (
                    array_reduce($alternate_method->getParameterList(),
                    function (bool $carry, Parameter $parameter) {
                        return ($carry || $parameter->isVariadic());
                    }, false)
                );
            }, false);

        // Figure out if any of the arguments are a call to unpack()
        $is_unpack = array_reduce($arglist->children,
            function ($carry, $node) {
                return ($carry || (
                    $node instanceof Node
                    && $node->kind == \ast\AST_UNPACK
                ));
            }, false);

        // Make sure we have enough arguments
        if(!$is_unpack
            && $argcount < $method->getNumberOfRequiredParameters()
        ) {
            $alternate_found = false;
            foreach ($method->alternateGenerator($code_base) as $alternate_method) {
                $alternate_found = $alternate_found || (
                    $argcount >=
                    $alternate_method->getNumberOfParameters()
                );
            }

            if(!$alternate_found) {
                if($method->getContext()->isInternal()) {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getFQSEN()}() which requires {$method->getNumberOfRequiredParameters()} arg(s)",
                        $context->getFile(),
                        $context->getLineNumberStart()
                    );
                } else {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getFQSEN()}() which requires {$method->getNumberOfRequiredParameters()} arg(s) defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $context->getLineNumberStart()
                    );
                }
            }
        }

        // Make sure we don't have too many arguments
        if(!$is_varargs
            && $argcount > $method->getNumberOfParameters()
        ) {
            $alternate_found = false;
            foreach ($method->alternateGenerator($code_base) as $alternate_method) {
                $alternate_found = $alternate_found || (
                    $argcount <=
                    $alternate_method->getNumberOfParameters()
                );
            }

            if (!$alternate_found) {
                $max = $method->getNumberOfParameters();
                if($method->getContext()->isInternal()) {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getFQSEN()}() which only takes {$max} arg(s)",
                        $context->getFile(),
                        $node->lineno
                    );
                } else {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getFQSEN()}() which only takes {$max} arg(s) defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $node->lineno
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
     * @param CodeBase $code_base
     * The global code base
     *
     * @param Method $method
     * The method we're analyzing arguments for
     *
     * @param Node $node
     * The node holding the method call we're looking at
     *
     * @param Context $context
     * The context in which we see the call
     *
     * @return null
     *
     * @see \Phan\Deprecated\Pass2::arglist_type_check
     * Formerly `function arglist_type_check`
     */
    private static function analyzeParameterList(
        CodeBase $code_base,
        Method $method,
        Node $node,
        Context $context
    ) {
        foreach($node->children ?? [] as $i => $argument) {

            // Get the parameter associated with this argument
            $parameter = $method->getParameterList()[$i] ?? null;

            // This issue should be caught elsewhere
            if (!$parameter) {
                continue;
            }

            // If this is a pass-by-reference parameter, make sure
            // we're passing an allowable argument
            if($parameter->isPassByReference()) {
                if((!$argument instanceof \ast\Node)
                    || ($argument->kind != \ast\AST_VAR
                        && $argument->kind != \ast\AST_DIM
                        && $argument->kind != \ast\AST_PROP
                        && $argument->kind != \ast\AST_STATIC_PROP
                    )
                ) {
                    Log::err(
                        Log::ETYPE,
                        "Only variables can be passed by reference at arg#".($i+1)." of {$method->getFQSEN()}()",
                        $context->getFile(),
                        $node->lineno
                    );
                } else {
                    $variable_name = (new ContextNode(
                        $code_base, $context, $argument
                    ))->getVariableName();

                    if($argument->kind == \ast\AST_STATIC_PROP) {
                        if (in_array($variable_name, [
                            'self', 'static', 'parent'
                        ])) {
                            Log::err(
                                Log::ESTATIC,
                                "Using {$variable_name}:: when not in object context",
                                $context->getFile(),
                                $argument->lineno
                            );
                        }
                    }
                }
            }

            // Get the type of the argument. We'll check it against
            // the parameter in a moment
            try {
                $argument_type = UnionType::fromNode(
                    $context, $code_base, $argument
                );
            } catch (CodeBaseException $exception) {
                Log::err(
                    Log::EUNDEF,
                    $exception->getMessage(),
                    $context->getFile(),
                    $node->lineno
                );

                $argument_type = new UnionType();
            }

            // Expand it to include all parent types up the chain
            $argument_type_expanded =
                $argument_type->asExpandedTypes($code_base);

            /* TODO see issue #42
               If argument is an object and it has a String union type,
               then we need to ignore that in strict_types=1 mode.
            if ($argument instanceof \ast\Node) {
                if(!empty($argument->children['class'])) {
                    // arg is an object
                    if ($method->getContext()->getStrictTypes()) {
                        ...
                    }
                }
            }
              or maybe UnionType::fromNode should check strict_types and
              not return the string union type

              or we shouldn't add the string type at all when a class
              has a __toString() and instead set a flag and check that
              instead
            */

            // Check the method to see if it has the correct
            // parameter types. If not, keep hunting through
            // alternates of the method until we find one that
            // takes the correct types
            $alternate_parameter = null;
            $alternate_found = false;

            foreach ($method->alternateGenerator($code_base)
                as $alternate_id => $alternate_method
            ) {
                if (empty($alternate_method->getParameterList()[$i])) {
                    continue;
                }

                // Get the parameter associated with this argument
                $alternate_parameter =
                    $alternate_method->getParameterList()[$i] ?? null;

                // Expand the types to find all parents and traits
                $alternate_parameter_type_expanded =
                    $alternate_parameter
                    ->getUnionType()
                    ->asExpandedTypes($code_base);

                // See if the argument can be cast to the
                // parameter
                if ($argument_type_expanded->canCastToUnionType(
                    $alternate_parameter_type_expanded
                )) {
                    $alternate_found = true;
                    break;
                }
            }

            if (!$alternate_found) {
                $parameter_name = $alternate_parameter
                    ? $alternate_parameter->getName()
                    : 'unknown';

                $parameter_type = $alternate_parameter
                    ? $alternate_parameter->getUnionType()
                    : 'unknown';

                if ($method->getContext()->isInternal()) {
                    Log::err(
                        Log::ETYPE,
                        "arg#".($i+1)."($parameter_name) is "
                        . "$argument_type_expanded but {$method->getFQSEN()}() "
                        . "takes $parameter_type",
                        $context->getFile(),
                        $node->lineno
                    );
                } else {
                    Log::err(
                        Log::ETYPE,
                        "arg#".($i+1)."($parameter_name) is "
                        . "$argument_type_expanded but {$method->getFQSEN()}() "
                        . "takes $parameter_type "
                        . "defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $node->lineno
                    );
                }
            }
        }
    }

    /**
     * Emit a log message if the type of the given
     * node cannot be cast to the given type
     *
     * @param Node|null|string|int $node
     * A node or whatever php-ast feels like returning
     *
     * @return bool
     * True if the cast is possible, else false
     */
    private static function analyzeNodeUnionTypeCast(
        $node,
        Context $context,
        CodeBase $code_base,
        UnionType $cast_type,
        string $log_message
    ) : bool {
        // Get the type of the node
        $node_type = UnionType::fromNode(
            $context,
            $code_base,
            $node
        );

        // See if it can be cast to the given type
        $can_cast = $node_type->canCastToUnionType(
            $cast_type
        );

        // If it can't, emit the log message
        if (!$can_cast) {
            Log::err(
                Log::EPARAM,
                sprintf($log_message, $node_type),
                $context->getFile(),
                $context->getLineNumberStart()
            );
        }

        return $can_cast;
    }

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @param Method $method
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
     * @return null
     *
     * @see \Phan\Deprecated\Pass2::arg_check
     * Formerly `function arg_check`
     */
    private static function analyzeInternalArgumentType(
        Method $method,
        Node $node,
        Context $context,
        CodeBase $code_base
    ) {
        $arglist = $node->children['args'];
        $argcount = count($arglist->children);

        switch($method->getName()) {
        case 'join':
        case 'implode':
            // (string glue, array pieces),
            // (array pieces, string glue) or
            // (array pieces)
            if($argcount == 1) {
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[0],
                    $context,
                    $code_base,
                    ArrayType::instance()->asUnionType(),
                    "arg#1(pieces) is %s but {$method->getFQSEN()}() takes array when passed only 1 arg"
                );
                return;
            } else if($argcount == 2) {
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

                if((string)$arg1_type == 'array') {
                    if (!$arg1_type->canCastToUnionType(
                        StringType::instance()->asUnionType()
                    )) {
                        Log::err(
                            Log::EPARAM,
                            "arg#2(glue) is $arg2_type but {$method->getFQSEN()}() takes string when arg#1 is array",
                            $context->getFile(),
                            $context->getLineNumberStart()
                        );
                    }
                } else if((string)$arg1_type == 'string') {
                    if (!$arg2_type->canCastToUnionType(
                        ArrayType::instance()->asUnionType()
                    )) {
                        Log::err(
                            Log::EPARAM,
                            "arg#2(pieces) is $arg2_type but {$method->getFQSEN()}() takes array when arg#1 is string",
                            $context->getFile(),
                            $context->getLineNumberStart()
                        );
                    }
                }
                return;
            }

            // Any other arg counts we will let the regular
            // checks handle
            break;
        case 'array_udiff':
        case 'array_diff_uassoc':
        case 'array_uintersect_assoc':
        case 'array_intersect_ukey':
            if($argcount < 3) {
                Log::err(
                    Log::EPARAM,
                    "call with $argcount arg(s) to {$method->getFQSEN()}() which requires {$method->getNumberOfRequiredParameters()} arg(s)",
                    $context->getFile(),
                    $context->getLineNumberStart()
                );

                return;
            }

            self::analyzeNodeUnionTypeCast(
                $arglist->children[$argcount - 1],
                $context,
                $code_base,
                CallableType::instance()->asUnionType(),
                "The last argument to {$method->getFQSEN()} must be a callable"
            );

            for ($i=0; $i < ($argcount - 1); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$i],
                    $context,
                    $code_base,
                    CallableType::instance()->asUnionType(),
                    "arg#".($i+1)." is %s but {$method->getFQSEN()}() takes array"
                );
            }
            return;

        case 'array_diff_uassoc':
        case 'array_uintersect_uassoc':
            if($argcount < 4) {
                Log::err(
                    Log::EPARAM,
                    "call with $argcount arg(s) to {$method->getFQSEN()}() which requires {$method->getNumberOfRequiredParameters()} arg(s)",
                    $context->getFile(),
                    $context->getLineNumberStart()
                );
                return;
            }

            // The last 2 arguments must be a callable and there
            // can be a variable number of arrays before it
            self::analyzeNodeUnionTypeCast(
                $arglist->children[$argcount - 1],
                $context,
                $code_base,
                CallableType::instance()->asUnionType(),
                "The last argument to {$method->getFQSEN()} must be a callable"
            );

            self::analyzeNodeUnionTypeCast(
                $arglist->children[$argcount - 2],
                $context,
                $code_base,
                CallableType::instance()->asUnionType(),
                "The second last argument to {$method->getFQSEN()} must be a callable"
            );

            for($i=0; $i < ($argcount-2); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$i],
                    $context,
                    $code_base,
                    ArrayType::instance()->asUnionType(),
                    "arg#".($i+1)." is %s but {$method->getFQSEN()}() takes array"
                );
            }
            return;

        case 'strtok':
            // (string str, string token) or (string token)
            if($argcount == 1) {
                // If we have just one arg it must be a string token
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[0],
                    $context,
                    $code_base,
                    ArrayType::instance()->asUnionType(),
                    "arg#1(token) is %s but {$method->getFQSEN()}() takes string when passed only one arg"
                );
            }
            // The arginfo check will handle the other case
            break;
        case 'min':
        case 'max':
            if($argcount == 1) {
                // If we have just one arg it must be an array
                if (!self::analyzeNodeUnionTypeCast(
                    $arglist->children[0],
                    $context,
                    $code_base,
                    ArrayType::instance()->asUnionType(),
                    "arg#1(values) is %s but {$method->getFQSEN()}() takes array when passed only one arg"
                )) {
                    return;
                }
            }
            // The arginfo check will handle the other case
            break;
        default:
            break;
        }
    }
}

