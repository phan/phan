<?php declare(strict_types=1);
namespace Phan\Plugin\Internal;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use Phan\PluginV2\StopParamAnalysisException;
use Phan\PluginV2;
use Closure;

use function count;

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
    private function getAnalyzeFunctionCallClosuresStatic() : array
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

        /** @return string */
        $get_variable_name = function (
            CodeBase $code_base,
            Context $context,
            $node
        ) {
            try {
                $variable_name = (new ContextNode(
                    $code_base,
                    $context,
                    $node
                ))->getVariableName();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $context,
                    $exception->getIssueInstance()
                );
                return '';
            }
            return $variable_name;
        };

        /**
         * @return void
         */
        $array_add_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args
        ) use ($get_variable_name) {
            // TODO: support nested adds, like AssignmentVisitor
            if (count($args) < 2) {
                return;
            }
            $variable_name = $get_variable_name($code_base, $context, $args[0]);
            // Don't analyze variables when we can't determine their names.
            if ($variable_name === '') {
                return;
            }
            if (!$context->getScope()->hasVariableWithName($variable_name)) {
                return;
            }
            $element_types = UnionType::empty();
            for ($i = 1; $i < \count($args); $i++) {
                // TODO: check for variadic here and in other plugins
                // E.g. unfold_args(args)
                $node = $args[$i];
                $element_types = $element_types->withUnionType(UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node));
            }
            $variable = $context->getScope()->getVariableByName($variable_name);
            $variable->setUnionType($variable->getUnionType()->nonNullableClone()->withUnionType(
                $element_types->elementTypesToGenericArray(GenericArrayType::KEY_INT)
            ));
        };

        /**
         * @return void
         */
        $array_remove_single_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args
        ) use ($get_variable_name) {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 1) {
                return;
            }
            $variable_name = $get_variable_name($code_base, $context, $args[0]);
            // Don't analyze variables when we can't determine their names.
            if ($variable_name === '') {
                return;
            }
            if (!$context->getScope()->hasVariableWithName($variable_name)) {
                return;
            }

            $variable = $context->getScope()->getVariableByName($variable_name);
            $variable->setUnionType($variable->getUnionType()->withFlattenedArrayShapeTypeInstances());
        };

        $array_splice_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args
        ) use ($get_variable_name) {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 4) {
                return;
            }
            $variable_name = $get_variable_name($code_base, $context, $args[0]);
            // Don't analyze variables when we can't determine their names.
            if ($variable_name === '') {
                return;
            }
            if (!$context->getScope()->hasVariableWithName($variable_name)) {
                return;
            }

            // TODO: Support array_splice('x', $offset, $length, $notAnArray)
            // TODO: handle empty array
            $added_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[3])->genericArrayTypes();
            $added_types = $added_types->withFlattenedArrayShapeTypeInstances();

            $variable = $context->getScope()->getVariableByName($variable_name);
            $old_types = $variable->getUnionType()->withFlattenedArrayShapeTypeInstances();

            $variable->setUnionType($old_types->withUnionType($added_types));
        };

        return [
            'array_udiff' => $array_udiff_callback,
            'array_diff_uassoc' => $array_udiff_callback,
            'array_uintersect_assoc' => $array_udiff_callback,
            'array_intersect_ukey' => $array_udiff_callback,

            'array_uintersect_uassoc' => $array_uintersect_uassoc_callback,

            'array_push' => $array_add_callback,
            'array_pop' => $array_remove_single_callback,
            'array_shift' => $array_remove_single_callback,
            'array_unshift' => $array_add_callback,

            'array_splice' => $array_splice_callback,  // TODO: If this callback ever does anything other than flatten, then create a different callback

            'join' => $join_callback,
            'implode' => $join_callback,

            'min' => $min_max_callback,
            'max' => $min_max_callback,

            // TODO: sort and usort should convert array<string,T> to array<int,T> (same for array shapes)
        ];
    }

    /**
     * @param Codebase $code_base @phan-unused-param
     * @return array<string,Closure>
     * @phan-return array<string,Closure(CodeBase,Context,FunctionInterface,array):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic();
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
