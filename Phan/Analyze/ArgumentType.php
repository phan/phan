<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\Element\Method;
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
    public static function analyzeArgumentType(
        Method $method,
        Node $node,
        Context $context
    ) {
        $ok = false;
        $varargs = false;
        $unpack = false;

        if($node->kind == \ast\AST_CALL
            || $node->kind == \ast\AST_NEW
        ) {
            $arglist = $node->children['args'];
        } else {
            $arglist = $node->children['args'];
        }

        $argcount = count($arglist->children);

        // Special common cases where we want slightly
        // better multi-signature error messages
        if($method->getContext()->isInternal()) {
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
        } else {
            if($method->isDeprecated()) {
                Log::err(
                    Log::EDEP,
                    "Call to deprecated function {$method->getName()}() defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                    $context->getFile(),
                    $context->getLineNumberStart()
                );
            }
        }

        foreach($method->getParameterList() as $parameter) {
            if ($parameter->isVariadic()) {
                $varargs = true;
            }
        }

        foreach($arglist->children as $arg) {
            if($arg instanceof \ast\Node
                && $arg->kind == \ast\AST_UNPACK
            ) {
                $unpack = true;
            }
        }

        // TODO: wha?
        // $fn = $func['scope'] ?? $func['name'];

        if(!$unpack
            && $argcount < $method->getNumberOfRequiredParameters()
        ) {
            $has_alternate_with_sufficient_parameters = false;

            // Hunt for an available alternate ID if necessary
            $alternate_id = 0;

            $method_fqsen =
                $method->getFQSEN()->withAlternateId(++$alternate_id);

            while($context->getCodeBase()->hasMethodWithFQSEN($method_fqsen)) {
                // Get the method with the given FQSEN
                $alt_method =
                    $context->getCodeBase()->getMethodByFQSEN(
                        $method_fqsen
                    );

                // See if the alternate has a good number of arguments
                if($argcount >= $alt_method->getNumberOfRequiredParameters()) {
                    $has_alternate_with_sufficient_parameters = true;
                    break;
                }

                // Otherwise, keep hunting for an alternate that
                // works
                $method_fqsen =
                    $method_fqsen->withAlternateId(++$alternate_id);
            }

            if(!$has_alternate_with_sufficient_parameters) {
                if($method->getContext()->isInternal()) {
                    // TODO: here
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

        /*
        if(!$varargs
            && $argcount > $method->getNumberOfParameters()) {
            $err = true;
            $alt = 1;
            // Check if there is an alternate signature that is ok
            while(!empty($functions["{$func['name']} $alt"])) {
                if($argcount > ($functions["{$func['name']} $alt"]['required']+$functions["{$func['name']} $alt"]['optional'])) $alt++;
                else { $err = false; break; }
            }
            // For method calls, we have no way of knowing the actual signature.
            // We may only have the base signature from an interface, for example
            // and the actual called method could have extra optional args
            if($err && $ast->kind != \ast\AST_METHOD_CALL) {
                $max = $method->getNumberOfParameters();
                if($method->getContext()->isInternal())
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getName()}() which only takes {$max} arg(s)",
                        $context->getFile(),
                        $node->lineno
                    );
                else
                    Log::err(
                        Log::EPARAM,
                        "call with $argcount arg(s) to {$method->getName()}() which only takes {$max} arg(s) defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()}",
                        $context->getFile(),
                        $node->lineno
                    );
            }
        }
        */

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
     * Emit a log message if the type of the given
     * node cannot be cast to the given type
     *
     * @return bool
     * True if the cast is possible, else false
     */
    private static function analyzeNodeUnionTypeCast(
        Node $node,
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
                sprintf($log_message, $arg_type),
                $context->getFile(),
                $context->getLineNumberStart()
            );
        }

        return $can_cast;
    }


}

