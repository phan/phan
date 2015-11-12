<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Parameter;
use \Phan\Language\FQSEN;
use \Phan\Language\Type\{
    ArrayType,
    BoolType,
    CallableType,
    FloatType,
    GenericArrayType,
    IntType,
    MixedType,
    NativeType,
    NullType,
    ObjectType,
    ResourceType,
    ScalarType,
    StringType,
    VoidType
};
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

trait ArgumentType {

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
     * @return null
     *
     * @see \Phan\Deprecated\Pass2::arg_check
     * Formerly `function arg_check`
     */
    public static function analyzeArgumentType(
        Method $method,
        Node $node,
        Context $context
    ) {
        // Special common cases where we want slightly
        // better multi-signature error messages
        if($method->getContext()->isInternal()) {
            self::analyzeInternalArgumentType($method, $node, $context);
        }

        // Emit an error if this method is marked as deprecated
        if($method->isDeprecated()) {
            Log::err(
                Log::EDEP,
                "Call to deprecated function {$method->getName()}() defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                $context->getFile(),
                $context->getLineNumberStart()
            );
        }

        $arglist = $node->children['args'];
        $argcount = count($arglist->children);

        // Figure out if any parameters are variadic
        $is_varargs = array_reduce($method->getParameterList(),
            function ($carry, Parameter $parameter) {
                return ($carry || $parameter->isVariadic());
            }, false);

        // Figure out if any of the arguments are a call to unpack()
        $is_unpack = array_reduce($arglist->children,
            function ($carry, $node) {
                return ($carry || (
                    $node instanceof Node
                    && $node->kind == \ast\AST_UNPACK
                ));
            }, false);

        // Unpack is apparently something weird. If its not that
        // check to see if we have enough parameters.
        if(!$is_unpack
            && $argcount < $method->getNumberOfRequiredParameters()
        ) {
            $alternate_found = array_reduce(
                (array)$method->alternateGenerator($context->getCodeBase()),
                function (bool $carry, Method $alternate_method) : bool {
                    return $carry || (
                        $argcount >=
                        $alternate_method->getNumberOfParameters()
                    );
                }, false);

            if(!$alternate_found) {
                if($method->getContext()->isInternal()) {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getName()}() which requires {$method->getNumberOfRequiredParameters()} arg(s)",
                        $context->getFile(),
                        $context->getLineNumberStart()
                    );
                } else {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getName()}() which requires {$method->getNumberOfRequiredParameters()} arg(s) defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $context->getLineNumberStart()
                    );
                }
            }
        }


        if(!$is_varargs
            && $argcount > $method->getNumberOfParameters()
        ) {
            $alternate_found = array_reduce(
                (array)$method->alternateGenerator($context->getCodeBase()),
                function (bool $carry, Method $alternate_method) : bool {
                    return $carry || (
                        $argcount <=
                        $alternate_method->getNumberOfParameters()
                    );
                }, false);

            if (!$alternate_found) {
                $max = $method->getNumberOfParameters();
                if($method->getContext()->isInternal()) {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getName()}() which only takes {$max} arg(s)",
                        $context->getFile(),
                        $node->lineno
                    );
                } else {
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getName()}() which only takes {$max} arg(s) defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $node->lineno
                    );
                }
            }
        }

        // Check the parameter types
        self::analyzeParameterList($method, $arglist, $context);

        // Are the types right?
        // Check if we have any alternate arginfo signatures
        // Checking the alternates before the main to make the final error messages, if any, refer to the main signature
        $errs = [];
        /*
        $alt = 1;
        if($class_name) {
            $lc = strtolower($class_name);
            $lfn = strtolower($func['name']);
            $func['name'] = $class_name.'::'.$func['name'];
            while(!empty($classes[$lc]['methods']["$lfn $alt"])) {
                $errs = arglist_type_check($file, $namespace, $arglist, $classes[$lc]['methods']["$lfn $alt"], $current_scope, $current_class);
                $alt++;
                if(empty($errs)) break;
            }
        } else {
            while(!empty($functions["{$func['name']} $alt"])) {
                $errs = arglist_type_check($file, $namespace, $arglist, $functions["{$func['name']} $alt"], $current_scope, $current_class);
                $alt++;
                if(empty($errs)) break;
            }
        }
        if($alt==1 || ($alt>1 && !empty($errs))) $errs = arglist_type_check($file, $namespace, $arglist, $func, $current_scope, $current_class);
        */

        foreach($errs as $err) {
            Log::err(
                Log::ETYPE,
                $err,
                $context->getFile(),
                $node->lineno
            );
        }
    }

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
     * @return null
     *
     * @see \Phan\Deprecated\Pass2::arglist_type_check
     * Formerly `function arglist_type_check`
     */
    private static function analyzeParameterList(
        Method $method,
        Node $node,
        Context $context
    ) {
        foreach($node->children as $i => $argument) {

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
                        "Only variables can be passed by reference at arg#".($i+1)." of {$method->getName()}()",
                        $context->getFile(),
                        $node->lineno
                    );
                } else {
                    $variable_name =
                        self::astVariableName($argument);

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

            // Make sure each argument can be cast to the
            // specified parameter types
            $argument_type = UnionType::fromNode(
                $context, $argument
            );

            $parameter_type = $parameter->getUnionType();

            if (!$argument_type->canCastToExpandedUnionType(
                $parameter_type,
                $context->getCodeBase()
            )) {
                if ($method->getContext()->isInternal()) {
                    Log::err(
                        Log::ETYPE,
                        "arg#".($i+1)."({$parameter->getName()}) is $argument_type but {$method->getName()}() takes {$parameter->getUnionType()}",
                        $context->getFile(),
                        $node->lineno
                    );
                } else {
                    Log::err(
                        Log::ETYPE,
                        "arg#".($i+1)."({$parameter->getName()}) is $argument_type but {$method->getName()}() takes {$parameter->getUnionType()} defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $node->lineno
                    );
                }
            }

            // For user functions, add the types of the args to
            // the receiving function's scope
            if (!$method->getContext()->isInternal()) {
                // HMMM, this happens in ParseVisitor::visitMethod
                // already, yeah?
                //
                // I think we can skip the junk below
            }

            /*
            // For user functions, add the types of the args to the receiving function's scope
            if($func['file'] != 'internal') {
                if(empty($scope[$fn]['vars'][$param['name']])) {
                    $scope[$fn]['vars'][$param['name']] = ['type'=>'', 'tainted'=>false, 'tainted_by'=>''];
                }
                // If it is by-ref link it back to the local variable name
                if($param['flags'] & \ast\flags\PARAM_REF) {
                    $arg_type = node_type($file, $namespace, $arg, $current_scope, $current_class, $taint, false);
                    if($arg->kind == \ast\AST_STATIC_PROP && $arg->children[0]->kind == \ast\AST_NAME) {
                        $class_name = $arg->children[0]->children[0];
                        if($class_name == 'self' || $class_name == 'static' || $class_name == 'parent') {
                            if($current_class) {
                                if($class_name == 'static') $class_name = $current_class['name'];
                                if($class_name == 'self') {
                                    if($current_scope != 'global') list($class_name,) = explode('::', $current_scope);
                                    else $class_name = $current_class['name'];
                                }
                                else if($class_name == 'parent') $class_name = $current_class['parent'];
                                $static_call_ok = true;
                            } else $class_name = '';
                        } else {
                            $class_name = qualified_name($file, $arg->children[0], $namespace);
                        }
                        if($class_name) {
                            if(!($arg->children[1] instanceof \ast\Node)) {
                                if(empty($classes[strtolower($class_name)]['properties'][$arg->children[1]])) {
                                    Log::err(Log::ESTATIC, "Access to undeclared static property: {$class_name}::\${$arg->children[1]}", $file, $arg->lineno);
                                } else {
                                    $scope[$fn]['vars'][$param['name']] = &$classes[strtolower($class_name)]['properties'][$arg->children[1]];
                                }
                            }
                        }
                    } else {
                        if(!empty($scope[$current_scope]['vars'][$arg_name])) {
                            if($arg->kind != \ast\AST_DIM) {
                                $scope[$fn]['vars'][$param['name']] = &$scope[$current_scope]['vars'][$arg_name];
                            } else {
                                // Not going to try to guess array sub-types here
                                $scope[$fn]['vars'][$param['name']]['type'] = '';
                            }
                        } else {
                            $scope[$fn]['vars'][$param['name']]['type'] = $arg_type;
                        }
                    }
                } else {
                    $arg_type = node_type($file, $namespace, $arg, $current_scope, $current_class, $taint);
                    if(!empty($arg_type)) add_type($fn, $param['name'], strtolower($arg_type));
                }
            } else {
                $arg_type = node_type($file, $namespace, $arg, $current_scope, $current_class, $taint, !($param['flags'] & \ast\flags\PARAM_REF));
            }
            */

            /*
            // For all functions, add the param to the local scope if pass-by-ref
            // and make it an actual ref for user functions
            if($param['flags'] & \ast\flags\PARAM_REF) {
                if($func['file'] == 'internal') {
                    if(empty($scope[$current_scope]['vars'][$arg_name])) {
                        add_var_scope($current_scope, $arg_name, $arg_type);
                    } else {
                        add_type($current_scope, $arg_name, $param['type']);
                    }
                } else {
                    if(empty($scope[$current_scope]['vars'][$arg_name])) {
                        if(!array_key_exists($current_scope, $scope)) $scope[$current_scope] = [];
                        if(!array_key_exists('vars', $scope[$current_scope])) $scope[$current_scope]['vars'] = [];
                        $scope[$current_scope]['vars'][$arg_name] = &$scope[$fn]['vars'][$param['name']];
                    }
                }
            }
             */

            /*
            // turn callable:{closure n} into just callable
            if(strpos($arg_type, ':') !== false) list($arg_type,) = explode(':',$arg_type,2);
             */

            /*
            // if we have a single non-native type, expand it
            if(!empty($arg_type) && !is_native_type($arg_type)) {
                if(!empty($classes[strtolower($arg_type)]['type'])) {
                    $arg_type = $classes[strtolower($arg_type)]['type'];
                }
            }
             */
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
        UnionType $cast_type,
        string $log_message
    ) : bool {
        // Get the type of the node
        $node_type = UnionType::fromNode(
            $context,
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
     * @return null
     *
     * @see \Phan\Deprecated\Pass2::arg_check
     * Formerly `function arg_check`
     */
    private static function analyzeInternalArgumentType(
        Method $method,
        Node $node,
        Context $context
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
                    ArrayType::instance()->asUnionType(),
                    "arg#1(pieces) is %s but {$method->getName()}() takes array when passed only 1 arg"
                );
                return;
            } else if($argcount == 2) {
                $arg1_type = UnionType::fromNode(
                    $context,
                    $arglist->children[0]
                );

                $arg2_type = UnionType::fromNode(
                    $context,
                    $arglist->children[1]
                );

                if((string)$arg1_type == 'array') {
                    if (!$arg1_type->canCastToUnionType(
                        StringType::instance()->asUnionType()
                    )) {
                        Log::err(
                            Log::EPARAM,
                            "arg#2(glue) is $arg2_type but {$method->getName()}() takes string when arg#1 is array",
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
                            "arg#2(pieces) is $arg2_type but {$method->getName()}() takes array when arg#1 is string",
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
                    "call with $argcount arg(s) to {$method->getName()}() which requires {$method->getNumberOfRequiredParameters()} arg(s)",
                    $context->getFile(),
                    $context->getLineNumberStart()
                );

                return;
            }

            self::analyzeNodeUnionTypeCast(
                $arglist->children[$argcount - 1],
                $context,
                CallableType::instance()->asUnionType(),
                "The last argument to {$method->getName()} must be a callable"
            );

            for ($i=0; $i < ($argcount - 1); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$i],
                    $context,
                    CallableType::instance()->asUnionType(),
                    "arg#".($i+1)." is %s but {$method->getName()}() takes array"
                );
            }
            return;

        case 'array_diff_uassoc':
        case 'array_uintersect_uassoc':
            if($argcount < 4) {
                Log::err(
                    Log::EPARAM,
                    "call with $argcount arg(s) to {$method->getName()}() which requires {$method->getNumberOfRequiredParameters()} arg(s)",
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
                CallableType::instance()->asUnionType(),
                "The last argument to {$method->getName()} must be a callable"
            );

            self::analyzeNodeUnionTypeCast(
                $arglist->children[$argcount - 2],
                $context,
                CallableType::instance()->asUnionType(),
                "The second last argument to {$method->getName()} must be a callable"
            );

            for($i=0; $i < ($argcount-2); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $arglist->children[$i],
                    $context,
                    ArrayType::instance()->asUnionType(),
                    "arg#".($i+1)." is %s but {$method->getName()}() takes array"
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
                    ArrayType::instance()->asUnionType(),
                    "arg#1(token) is %s but {$method->getName()}() takes string when passed only one arg"
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
                    ArrayType::instance()->asUnionType(),
                    "arg#1(values) is %s but {$method->getName()}() takes array when passed only one arg"
                )) {
                    return;
                }
            }
            $varargs = true;
            // The arginfo check will handle the other case
            break;
        default:
            if(UnionType::builtinFunctionPropertyNameTypeMap(
                $method->getFQSEN(),
                $context->getCodeBase()
            )) {
                $varargs = true;
            }
            break;
        }
    }
}

