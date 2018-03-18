<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use Phan\PluginV2\StopParamAnalysisException;
use Phan\PluginV2;
use Closure;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Analyze returning callables (function() : callable) for any callables that are returned as literals?
 * This would be difficult.
 */
final class MiscParamPlugin extends PluginV2 implements
    AnalyzeFunctionCallCapability
{
    /**
     * @return array<string,Closure>
     * @phan-return array<string,Closure(CodeBase,Context,FunctionInterface,array):void>
     */
    private function getAnalyzeFunctionCallClosuresStatic(CodeBase $code_base) : array
    {
        $stop_exception = new StopParamAnalysisException();

        /**
         * @return void
         */
        $min_max_callback = function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args
        ) {
            if (\count($args) !== 1) {
                return;
            }
            self::analyzeNodeUnionTypeCast(
                $args[0],
                $context,
                $code_base,
                ArrayType::instance(false)->asUnionType(),
                function (UnionType $node_type) use ($context, $function) {
                // "arg#1(values) is %s but {$function->getFQSEN()}() takes array when passed only one arg"
                    return Issue::fromType(Issue::ParamSpecial2)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        1,
                        'values',
                        (string)$node_type,
                        $function->getName(),
                        'array'
                        ]
                    );
                }
            );
        };
        /**
         * @return void
         */
        $array_udiff_callback = function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args
        ) {
            $argcount = \count($args);
            if ($argcount < 3) {
                return;
            }
            self::analyzeNodeUnionTypeCast(
                $args[$argcount - 1],
                $context,
                $code_base,
                CallableType::instance(false)->asUnionType(),
                function (UnionType $unused_node_type) use ($context, $function) {
                    // "The last argument to {$function->getFQSEN()} must be a callable"
                    return Issue::fromType(Issue::ParamSpecial3)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        (string)$function->getFQSEN(),
                        'callable'
                        ]
                    );
                }
            );

            for ($i=0; $i < ($argcount - 1); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $args[$i],
                    $context,
                    $code_base,
                    ArrayType::instance(false)->asUnionType(),
                    function (UnionType $node_type) use ($context, $function, $i) {
                        // "arg#".($i+1)." is %s but {$function->getFQSEN()}() takes array"
                        return Issue::fromType(Issue::ParamTypeMismatch)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            ($i+1),
                            (string)$node_type,
                            (string)$function->getFQSEN(),
                            'array'
                            ]
                        );
                    }
                );
            }
        };
        /**
         * @return void
         */
        $join_callback = function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args
        ) use ($stop_exception) {
            $argcount = \count($args);
            // (string glue, array pieces),
            // (array pieces, string glue) or
            // (array pieces)
            if ($argcount == 1) {
                self::analyzeNodeUnionTypeCast(
                    $args[0],
                    $context,
                    $code_base,
                    ArrayType::instance(false)->asUnionType(),
                    function (UnionType $unused_node_type) use ($context, $function) {
                        // "arg#1(pieces) is %s but {$function->getFQSEN()}() takes array when passed only 1 arg"
                        return Issue::fromType(Issue::ParamSpecial2)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                                1,
                                'pieces',
                                (string)$function->getFQSEN(),
                                'string',
                                'array'
                            ]
                        );
                    }
                );
            } elseif ($argcount == 2) {
                $arg1_type = UnionTypeVisitor::unionTypeFromNode(
                    $code_base,
                    $context,
                    $args[0]
                );

                $arg2_type = UnionTypeVisitor::unionTypeFromNode(
                    $code_base,
                    $context,
                    $args[1]
                );

                // TODO: better array checks
                if ($arg1_type->isExclusivelyArray()) {
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
                            (string)$function->getFQSEN(),
                            'string',
                            1,
                            'array'
                        );
                    }
                    throw $stop_exception;
                } elseif ($arg1_type->isType(StringType::instance(false))) {
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
                            (string)$function->getFQSEN(),
                            'array',
                            1,
                            'string'
                        );
                    }
                }
            }
        };
        /**
         * @return void
         */
        $array_uintersect_uassoc_callback = function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args
        ) {
            $argcount = \count($args);
            if ($argcount < 4) {
                return;
            }

            // The last 2 arguments must be a callable and there
            // can be a variable number of arrays before it
            self::analyzeNodeUnionTypeCast(
                $args[$argcount - 1],
                $context,
                $code_base,
                CallableType::instance(false)->asUnionType(),
                function (UnionType $unused_node_type) use ($context, $function) {
                    // "The last argument to {$function->getFQSEN()} must be a callable"
                    return Issue::fromType(Issue::ParamSpecial3)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        (string)$function->getFQSEN(),
                        'callable'
                        ]
                    );
                }
            );

            self::analyzeNodeUnionTypeCast(
                $args[$argcount - 2],
                $context,
                $code_base,
                CallableType::instance(false)->asUnionType(),
                function (UnionType $unused_node_type) use ($context, $function) {
                    // "The second last argument to {$function->getFQSEN()} must be a callable"
                    return Issue::fromType(Issue::ParamSpecial4)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        (string)$function->getFQSEN(),
                        'callable'
                        ]
                    );
                }
            );

            for ($i=0; $i < ($argcount-2); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $args[$i],
                    $context,
                    $code_base,
                    ArrayType::instance(false)->asUnionType(),
                    function (UnionType $node_type) use ($context, $function, $i) {
                    // "arg#".($i+1)." is %s but {$function->getFQSEN()}() takes array"
                        return Issue::fromType(Issue::ParamTypeMismatch)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            ($i+1),
                            (string)$node_type,
                            (string)$function->getFQSEN(),
                            'array'
                            ]
                        );
                    }
                );
            }
        };

        return [
            'array_udiff' => $array_udiff_callback,
            'array_diff_uassoc' => $array_udiff_callback,
            'array_uintersect_assoc' => $array_udiff_callback,
            'array_intersect_ukey' => $array_udiff_callback,

            'array_uintersect_uassoc' => $array_uintersect_uassoc_callback,

            'join' => $join_callback,
            'implode' => $join_callback,

            'min' => $min_max_callback,
            'max' => $min_max_callback,

        ];
    }

    /**
     * @return array<string,Closure>
     * @phan-return array<string,Closure(CodeBase,Context,FunctionInterface,array):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic($code_base);
        }
        return $analyzers;
    }

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
}
