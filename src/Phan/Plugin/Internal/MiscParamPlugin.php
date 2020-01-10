<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast;
use ast\Node;
use Closure;
use Phan\Analysis\AssignmentVisitor;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\RedundantCondition;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\AssociativeArrayType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\GenericArrayInterface;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\ListType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NonEmptyAssociativeArrayType;
use Phan\Language\Type\NonEmptyListType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Parse\ParseVisitor;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\StopParamAnalysisException;

use function count;

/**
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * TODO: Analyze returning callables (function() : callable) for any callables that are returned as literals?
 * This would be difficult.
 */
final class MiscParamPlugin extends PluginV3 implements
    AnalyzeFunctionCallCapability
{
    /**
     * @param list<Node|string|int|float> $args
     */
    private static function isInArrayCheckStrict(CodeBase $code_base, Context $context, array $args): bool
    {
        if (!isset($args[2])) {
            return false;
        }
        $type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2] ?? null);
        return !$type->isEmpty() && !$type->containsFalsey();
    }

    /**
     * @param list<Node|string|int|float> $args
     */
    private static function shouldWarnAboutImpossibleInArray(CodeBase $code_base, Context $context, array $args, ?UnionType $needle_type = null, ?UnionType $haystack_type = null): bool
    {
        $haystack_type = $haystack_type ?? UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
        if (!$haystack_type->hasRealTypeSet()) {
            return false;
        }
        if (!$haystack_type->hasRealTypeMatchingCallback(static function (Type $type): bool {
            return $type->isPossiblyTruthy();
        })) {
            return true;
        }

        $needle_type = $needle_type ?? UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
        if (!$needle_type->hasRealTypeSet()) {
            return false;
        }
        $is_strict = self::isInArrayCheckStrict($code_base, $context, $args);
        $has_iterable_type = false;
        foreach ($haystack_type->getRealTypeSet() as $type) {
            if (!($type instanceof IterableType)) {
                if ($type instanceof ScalarType) {
                    // ignore null, false, etc.
                    continue;
                }
                return false;
            }
            $element_type = $type->iterableValueUnionType($code_base);
            if (!$element_type || $element_type->isEmpty()) {
                return false;
            }
            $has_iterable_type = true;
            if ($needle_type->hasAnyTypeOverlap($code_base, $element_type)) {
                return false;
            }
            if (!$is_strict && $needle_type->hasAnyWeakTypeOverlap($element_type)) {
                return false;
            }
        }
        return $has_iterable_type;
    }

    /**
     * @param list<Node|string|int|float> $args
     */
    private static function shouldWarnAboutImpossibleArrayKeyExists(CodeBase $code_base, Context $context, array $args, ?UnionType $key_type = null, ?UnionType $array_type = null): bool
    {
        $array_type = $array_type ?? UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]);
        if (!$array_type->hasRealTypeSet()) {
            return false;
        }
        if (!$array_type->hasRealTypeMatchingCallback(static function (Type $type): bool {
            return $type->isPossiblyTruthy();
        })) {
            return true;
        }
        $key_type = $key_type ?? UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
        if (!$key_type->hasRealTypeSet()) {
            return false;
        }
        $key_type = $key_type->asRealUnionType();
        if ($key_type->hasMixedType()) {
            return false;
        }
        $key_can_be_int = $key_type->hasIntType();
        $key_can_be_string = $key_type->hasStringType();
        if (!$key_can_be_string && !$key_can_be_int) {
            // array_key_exists always returns false for anything except int or string.
            return true;
        }
        if ($key_can_be_string && $key_can_be_int) {
            // The key can be a string or an int - give up on checking.
            // TODO: Support checking unions of literal values,
            // e.g. array_key_exists(cond() ? 0 : 'string', ['other' => true])
            return false;
        }
        $key_value = $key_type->asSingleScalarValueOrNull();
        $key_value_as_int = \is_int($key_value) ? $key_value : (\is_string($key_value) ? \filter_var($key_value, \FILTER_VALIDATE_INT) : null);
        '@phan-var ?int|?string $key_value';  // inferred from $key_can_be_int||$key_can_be_string
        $has_array_type = false;
        foreach ($array_type->getRealTypeSet() as $type) {
            if (!($type instanceof ArrayType)) {
                if ($type instanceof ScalarType) {
                    // ignore null, false, etc.
                    continue;
                }
                return false;
            }
            $has_array_type = true;
            if (!$type instanceof GenericArrayInterface) {
                return false;
            }
            if ($type instanceof ArrayShapeType) {
                if ($type->isEmptyArrayShape()) {
                    continue;
                }
                if ($key_value !== null) {
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                    if (\array_key_exists($key_value, $type->getFieldTypes())) {
                        return false;
                    }
                    continue;
                }
                if (!$key_can_be_string && $type->getKeyType() === GenericArrayType::KEY_STRING) {
                    // Looking for int in an array shape with non-integer keys.
                    continue;
                }
            } elseif ($type instanceof ListType) {
                if ($key_value !== null) {
                    if (\is_int($key_value_as_int) && $key_value_as_int >= 0) {
                        return false;
                    }
                    continue;
                }
            }

            if ($type->getKeyType() === GenericArrayType::KEY_INT) {
                if (!$key_can_be_int) {
                    break;
                }
                if ($key_value_as_int === false) {
                    break;
                }
                if ($type instanceof ListType && $key_value_as_int < 0) {
                    break;
                }
            }
            return false;
        }
        return $has_array_type;
    }

    /**
     * Chooses an issue kind for an impossible check in in_array, depending on whether the in_array call was strict,
     * whether the arguments were constant, and whether this was in a loop or global scope.
     * @param non-empty-list<Node|string|int|float> $args
     */
    private static function issueKindForInArrayCheck(CodeBase $code_base, Context $context, array $args): string
    {
        $is_strict = self::isInArrayCheckStrict($code_base, $context, $args);
        $issue_type = $is_strict ? Issue::ImpossibleTypeComparison : Issue::SuspiciousWeakTypeComparison;
        $placeholder = new Node(ast\AST_ARRAY, 0, [
            new Node(ast\AST_ARRAY_ELEM, 0, ['key' => null, 'value' => $args[0]], 0),
            new Node(ast\AST_ARRAY_ELEM, 0, ['key' => null, 'value' => $args[1]], 0),
        ], 0);
        return RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($placeholder, $context, $issue_type);
    }

    /**
     * Chooses an issue kind for an impossible check in array_key_exists,
     * depending on whether the arguments were constant, and whether this was in a loop or global scope.
     * @param non-empty-list<Node|string|int|float> $args
     */
    private static function issueKindForArrayKeyExistsCheck(Context $context, array $args): string
    {
        $placeholder = new Node(ast\AST_ARRAY, 0, [
            new Node(ast\AST_ARRAY_ELEM, 0, ['key' => null, 'value' => $args[0]], 0),
            new Node(ast\AST_ARRAY_ELEM, 0, ['key' => null, 'value' => $args[1]], 0),
        ], 0);
        return RedundantCondition::chooseSpecificImpossibleOrRedundantIssueKind($placeholder, $context, Issue::ImpossibleTypeComparison);
    }

    /**
     * Based on RedundantConditionCallVisitor->emitIssueForBinaryOp.
     *
     * Emits warning about in_array checks that always return false.
     * @param non-empty-list<Node|string|float> $args
     * @suppress PhanAccessMethodInternal
     */
    private static function emitIssueForInArray(CodeBase $code_base, Context $context, array $args, ?Node $node): void
    {
        [$needle_node, $haystack_node] = $args;
        $needle = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $needle_node);
        $haystack = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $haystack_node);
        $haystack_string = $haystack->iterableValueUnionType($code_base)->__toString();
        $issue_args = [
            ASTReverter::toShortString($needle_node),
            $needle,
            'elements of ' . ASTReverter::toShortString($haystack_node),
            $haystack_string !== '' ? $haystack_string : '(no types)'
        ];

        if ($context->isInLoop()) {
            $needle_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $needle_node);
            $haystack_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $haystack_node);
            if ($needle_type_fetcher || $haystack_type_fetcher) {
                // @phan-suppress-next-line PhanAccessMethodInternal
                $context->deferCheckToOutermostLoop(static function (Context $context_after_loop) use ($code_base, $context, $args, $issue_args, $node, $haystack, $needle, $needle_type_fetcher, $haystack_type_fetcher): void {
                    if ($needle_type_fetcher) {
                        $needle = ($needle_type_fetcher($context_after_loop) ?? $needle);
                    }
                    if ($haystack_type_fetcher) {
                        $haystack = ($haystack_type_fetcher($context_after_loop) ?? $haystack);
                    }
                    // XXX this will have false positives if variables are unset in the loop.
                    if (!self::shouldWarnAboutImpossibleInArray($code_base, $context_after_loop, $args, $needle, $haystack)) {
                        return;
                    }
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        self::issueKindForInArrayCheck($code_base, $context, $args),
                        $node->lineno ?? $context->getLineNumberStart(),
                        ...$issue_args
                    );
                });
                return;
            }
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            self::issueKindForInArrayCheck($code_base, $context, $args),
            $node->lineno ?? $context->getLineNumberStart(),
            ...$issue_args
        );
    }

    /**
     * Based on RedundantConditionCallVisitor->emitIssueForBinaryOp.
     *
     * Emits warning about array_key_exists checks that always return false.
     * @param non-empty-list<Node|string|float> $args
     * @suppress PhanAccessMethodInternal
     */
    private static function emitIssueForArrayKeyExists(CodeBase $code_base, Context $context, array $args, ?Node $node): void
    {
        [$key_node, $array_node] = $args;
        $key_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $key_node);
        $array_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $array_node);
        $array_string = $array_type->iterableKeyUnionType($code_base)->__toString();
        $issue_args = [
            ASTReverter::toShortString($key_node),
            $key_type,
            'keys of ' . ASTReverter::toShortString($array_node),
            $array_string !== '' ? $array_string : '(no types)'
        ];

        if ($context->isInLoop()) {
            $key_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $key_node);
            $array_type_fetcher = RedundantCondition::getLoopNodeTypeFetcher($code_base, $array_node);
            if ($key_type_fetcher || $array_type_fetcher) {
                // @phan-suppress-next-line PhanAccessMethodInternal
                $context->deferCheckToOutermostLoop(static function (Context $context_after_loop) use ($code_base, $context, $args, $issue_args, $node, $key_type, $array_type, $key_type_fetcher, $array_type_fetcher): void {
                    // XXX this will have false positives if variables are unset in the loop.
                    if ($key_type_fetcher) {
                        $key_type = $key_type_fetcher($context_after_loop) ?? $key_type;
                    }
                    if ($array_type_fetcher) {
                        $array_type = $array_type_fetcher($context_after_loop) ?? $array_type;
                    }
                    if (!self::shouldWarnAboutImpossibleArrayKeyExists($code_base, $context_after_loop, $args, $key_type, $array_type)) {
                        return;
                    }
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        self::issueKindForArrayKeyExistsCheck($context, $args),
                        $node->lineno ?? $context->getLineNumberStart(),
                        ...$issue_args
                    );
                });
                return;
            }
        }
        Issue::maybeEmit(
            $code_base,
            $context,
            self::issueKindForArrayKeyExistsCheck($context, $args),
            $node->lineno ?? $context->getLineNumberStart(),
            ...$issue_args
        );
    }

    /**
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,array,?Node):void>
     */
    private static function getAnalyzeFunctionCallClosuresStatic(): array
    {
        $stop_exception = new StopParamAnalysisException();

        /**
         * @param list<Node|int|float|string> $args
         */
        $min_max_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args,
            ?Node $_
        ): void {
            if (\count($args) !== 1) {
                return;
            }
            if (($args[0]->kind ?? null) === ast\AST_UNPACK) {
                // min(...$var)
                return;
            }
            self::analyzeNodeUnionTypeCast(
                $args[0],
                $context,
                $code_base,
                ArrayType::instance(false)->asPHPDocUnionType(),
                static function (UnionType $node_type) use ($context, $function): IssueInstance {
                    // "arg#1(values) is %s but {$function->getFQSEN()}() takes array when passed only one arg"
                    return Issue::fromType(Issue::ParamSpecial2)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        1,
                        'values',
                        (string)$node_type,
                        $function->getRepresentationForIssue(),
                        'array'
                        ]
                    );
                }
            );
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_udiff_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args,
            ?Node $_
        ): void {
            $argcount = \count($args);
            if ($argcount < 3) {
                return;
            }
            self::analyzeNodeUnionTypeCast(
                $args[$argcount - 1],
                $context,
                $code_base,
                CallableType::instance(false)->asPHPDocUnionType(),
                static function (UnionType $unused_node_type) use ($context, $function): IssueInstance {
                    // "The last argument to {$function->getFQSEN()} must be a callable"
                    return Issue::fromType(Issue::ParamSpecial3)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        $function->getRepresentationForIssue(),
                        'callable'
                        ]
                    );
                }
            );

            for ($i = 0; $i < ($argcount - 1); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $args[$i],
                    $context,
                    $code_base,
                    ArrayType::instance(false)->asPHPDocUnionType(),
                    static function (UnionType $node_type) use ($context, $function, $i): IssueInstance {
                        // "arg#".($i+1)." is %s but {$function->getFQSEN()}() takes array"
                        return Issue::fromType(Issue::ParamTypeMismatch)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            ($i + 1),
                            (string)$node_type,
                            $function->getRepresentationForIssue(),
                            'array'
                            ]
                        );
                    }
                );
            }
        };

        /**
         * @param list<Node|int|float|string> $args
         * @return void
         * @throws StopParamAnalysisException
         * to prevent Phan's default incorrect analysis of a call to join()
         */
        $join_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args,
            ?Node $_
        ) use ($stop_exception): void {
            $argcount = \count($args);
            // (string glue, string[] pieces),
            // (string[] pieces, string glue) or
            // (string[] pieces)
            if ($argcount === 1) {
                if (($args[0]->kind ?? null) === ast\AST_UNPACK) {
                    // implode(...$var)
                    return;
                }
                self::analyzeNodeUnionTypeCastStringArrayLike(
                    $args[0],
                    $context,
                    $code_base,
                    static function (UnionType $node_type) use ($context, $function): IssueInstance {
                        // "arg#1(pieces) is %s but {$function->getFQSEN()}() takes array when passed only 1 arg"
                        return Issue::fromType(Issue::ParamSpecial2)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                                1,
                                'pieces',
                                $node_type->asNonLiteralType(),
                                $function->getRepresentationForIssue(),
                                'string[]'
                            ]
                        );
                    }
                );
                throw $stop_exception;
            } elseif ($argcount === 2) {
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
                    $did_warn = false;
                    if (!$arg2_type->canCastToUnionType(
                        StringType::instance(false)->asPHPDocUnionType()
                    )) {
                        $did_warn = true;
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::ParamSpecial1,
                            $context->getLineNumberStart(),
                            2,
                            'glue',
                            (string)$arg2_type->asNonLiteralType(),
                            $function->getRepresentationForIssue(),
                            'string',
                            1,
                            'array'
                        );
                    }
                    if (!self::canCastToStringArrayLike($code_base, $context, $arg1_type)) {
                        $did_warn = true;
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::TypeMismatchArgumentInternal,
                            $context->getLineNumberStart(),
                            1,
                            'pieces',
                            $arg1_type,
                            $function->getRepresentationForIssue(),
                            'string[]'
                        );
                    }
                    if (!$did_warn) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::CompatibleImplodeOrder,
                            $context->getLineNumberStart(),
                            $function->getRepresentationForIssue(),
                            (string)$arg1_type->asNonLiteralType(),
                            (string)$arg2_type->asNonLiteralType()
                        );
                    }
                    throw $stop_exception;
                } elseif ($arg1_type->isNonNullStringType()) {
                    if (!$arg2_type->canCastToUnionType(
                        ArrayType::instance(false)->asPHPDocUnionType()
                    )) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::ParamSpecial1,
                            $context->getLineNumberStart(),
                            2,
                            'pieces',
                            (string)$arg2_type->asNonLiteralType(),
                            $function->getRepresentationForIssue(),
                            'string[]',
                            1,
                            'string'
                        );
                    } elseif (!self::canCastToStringArrayLike($code_base, $context, $arg2_type)) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::TypeMismatchArgumentInternal,
                            $context->getLineNumberStart(),
                            2,
                            'pieces',
                            $arg2_type,
                            $function->getRepresentationForIssue(),
                            'string[]'
                        );
                    }
                    throw $stop_exception;
                }
            }
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_uintersect_uassoc_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args,
            ?Node $_
        ): void {
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
                CallableType::instance(false)->asPHPDocUnionType(),
                static function (UnionType $unused_node_type) use ($context, $function): IssueInstance {
                    // "The last argument to {$function->getFQSEN()} must be a callable"
                    return Issue::fromType(Issue::ParamSpecial3)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        $function->getRepresentationForIssue(),
                        'callable'
                        ]
                    );
                }
            );

            self::analyzeNodeUnionTypeCast(
                $args[$argcount - 2],
                $context,
                $code_base,
                CallableType::instance(false)->asPHPDocUnionType(),
                static function (UnionType $unused_node_type) use ($context, $function): IssueInstance {
                    // "The second last argument to {$function->getFQSEN()} must be a callable"
                    return Issue::fromType(Issue::ParamSpecial4)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [
                        $function->getRepresentationForIssue(),
                        'callable'
                        ]
                    );
                }
            );

            for ($i = 0; $i < ($argcount - 2); $i++) {
                self::analyzeNodeUnionTypeCast(
                    $args[$i],
                    $context,
                    $code_base,
                    ArrayType::instance(false)->asPHPDocUnionType(),
                    static function (UnionType $node_type) use ($context, $function, $i): IssueInstance {
                    // "arg#".($i+1)." is %s but {$function->getFQSEN()}() takes array"
                        return Issue::fromType(Issue::ParamTypeMismatch)(
                            $context->getFile(),
                            $context->getLineNumberStart(),
                            [
                            ($i + 1),
                            (string)$node_type,
                            $function->getRepresentationForIssue(),
                            'array'
                            ]
                        );
                    }
                );
            }
        };

        /**
         * @param Node|int|string|float|null $node
         * @return ?Variable the variable
         */
        $get_variable = static function (
            CodeBase $code_base,
            Context $context,
            $node
        ): ?Variable {
            if (!$node instanceof Node) {
                return null;
            }
            try {
                return (new ContextNode(
                    $code_base,
                    $context,
                    $node
                ))->getVariableStrict();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $context,
                    $exception->getIssueInstance()
                );
                return null;
            } catch (NodeException $_) {
                return null;
            }
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $array_add_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $function,
            array $args,
            ?Node $_
        ): void {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: support properties, like AssignmentVisitor
            if (count($args) < 2) {
                return;
            }
            $modified_array_node = $args[0];
            if (!($modified_array_node instanceof Node)) {
                return;
            }
            $lineno = $modified_array_node->lineno;
            $dim_node = new ast\Node(
                ast\AST_DIM,
                $lineno,
                ['expr' => $modified_array_node, 'dim' => null],
                0
            );
            $new_context = $context;
            for ($i = 1; $i < \count($args); $i++) {
                // TODO: check for variadic here and in other plugins
                // E.g. unfold_args(args)
                $expr_node = $args[$i];
                $right_inner_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $expr_node);
                // TODO add a way to append vs prepend values when `$x[] = expr;` is treated
                // as assigning to a specific offset instead of adding list<ExprT> to the union type.
                $right_type = $right_inner_type->asNonEmptyListTypes();

                $new_context = (new AssignmentVisitor(
                    $code_base,
                    $new_context,
                    $dim_node,
                    $right_type,
                    1
                ))->__invoke($modified_array_node);
            }
            if ($function->getName() === 'array_unshift' &&
                    $modified_array_node->kind === ast\AST_VAR) {
                $variable = (new ConditionVisitor($code_base, $new_context))->getVariableFromScope($modified_array_node, $new_context);
                if ($variable) {
                    $variable->setUnionType($variable->getUnionType()->withIntegerKeyArraysAsLists());
                    $new_context->addScopeVariable($variable);
                }
            }
            // Hackish: copy properties from this
            $context->setScope($new_context->getScope());
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $array_remove_single_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($get_variable): void {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 1) {
                return;
            }
            $arg_node = $args[0];
            if (!$arg_node instanceof Node) {
                return;
            }
            $variable = $get_variable($code_base, $context, $arg_node);
            if (!$variable) {
                return;
            }
            $variable = clone($variable);
            $context->addScopeVariable($variable);
            $old_type = $variable->getUnionType();
            if (!$old_type->containsFalsey()) {
                // @phan-suppress-next-line PhanUndeclaredProperty
                $arg_node->__phan_is_nonempty = true;
            }

            $variable->setUnionType(
                $old_type->withFlattenedTopLevelArrayShapeTypeInstances()
                         ->withPossiblyEmptyArrays()
            );
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $array_splice_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($get_variable): void {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 4) {
                return;
            }
            $variable = $get_variable($code_base, $context, $args[0]);
            if (!$variable) {
                return;
            }
            $variable = clone($variable);
            $context->addScopeVariable($variable);

            // TODO: Support array_splice('x', $offset, $length, $notAnArray)
            // TODO: handle empty array
            $added_types = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[3])->genericArrayTypes();
            $added_types = $added_types->withFlattenedTopLevelArrayShapeTypeInstances();

            $old_types = $variable->getUnionType()->withFlattenedTopLevelArrayShapeTypeInstances();

            $variable->setUnionType($old_types->withUnionType($added_types->withIntegerKeyArraysAsLists()));
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $sort_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($get_variable): void {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 1) {
                return;
            }
            $variable = $get_variable($code_base, $context, $args[0]);
            if (!$variable) {
                return;
            }

            // TODO: handle empty array
            $new_types = $variable->getUnionType()
                ->withFlattenedTopLevelArrayShapeTypeInstances()
                ->asMappedListUnionType(/** @return list<Type> */ static function (Type $type): array {
                    if ($type instanceof ListType) {
                        return [$type];
                    }
                    if ($type instanceof GenericArrayType) {
                        if ($type->isDefinitelyNonEmptyArray()) {
                            return [NonEmptyListType::fromElementType($type->genericArrayElementType(), $type->isNullable(), $type->getKeyType())];
                        }
                        return [ListType::fromElementType($type->genericArrayElementType(), $type->isNullable(), $type->getKeyType())];
                    }
                    if ($type instanceof IterableType) {
                        $result = [];
                        $class = $type instanceof GenericArrayInterface && $type->isDefinitelyNonEmptyArray() ? NonEmptyListType::class : ListType::class;
                        foreach ($type->genericArrayElementUnionType()->getTypeSet() as $element_type) {
                            $result[] = $class::fromElementType($element_type, $type->isNullable(), $type->getKeyType());
                        }
                        return $result ?: [$class::fromElementType(MixedType::instance(false), $type->isNullable(), $type->getKeyType())];
                    }
                    return [$type];
                });

            $variable->setUnionType($new_types);
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $associative_sort_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($get_variable): void {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 1) {
                return;
            }
            $variable = $get_variable($code_base, $context, $args[0]);
            if (!$variable) {
                return;
            }

            // TODO: handle empty array
            $new_types = $variable->getUnionType()
                ->withFlattenedTopLevelArrayShapeTypeInstances()
                ->asMappedListUnionType(/** @return list<Type> */ static function (Type $type): array {
                    if ($type instanceof AssociativeArrayType) {
                        return [$type];
                    }
                    if ($type instanceof GenericArrayType) {
                        if ($type->isDefinitelyNonEmptyArray()) {
                            return [NonEmptyAssociativeArrayType::fromElementType($type->genericArrayElementType(), $type->isNullable(), $type->getKeyType())];
                        }
                        return [AssociativeArrayType::fromElementType($type->genericArrayElementType(), $type->isNullable(), $type->getKeyType())];
                    }
                    if ($type instanceof IterableType) {
                        $result = [];
                        foreach ($type->genericArrayElementUnionType()->getTypeSet() as $element_type) {
                            $result[] = AssociativeArrayType::fromElementType($element_type, $type->isNullable(), $type->getKeyType());
                        }
                        return $result ?: [AssociativeArrayType::fromElementType(MixedType::instance(false), $type->isNullable(), $type->getKeyType())];
                    }
                    return [$type];
                });

            $variable->setUnionType($new_types);
        };

        /**
         * @param list<Node|int|float|string> $args
         * TODO: Could make unused variable detection more precise for https://github.com/phan/phan/issues/1812 , but low priority.
         */
        $extract_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ): void {
            // TODO: support nested adds, like AssignmentVisitor
            // TODO: Could be more specific for arrays with known length and order
            if (count($args) < 1) {
                return;
            }
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $array_shape_types = [];
            foreach ($union_type->getTypeSet() as $type) {
                if ($type instanceof ArrayShapeType) {
                    $array_shape_types[] = $type;
                }
            }
            if (count($array_shape_types) === 0) {
                return;
            }
            // TODO: Could be more nuanced and account for possibly undefined types in the combination.

            // TODO: Handle unexpected types of flags and prefix and warn, low priority
            if (isset($args[1])) {
                $flags = (new ContextNode($code_base, $context, $args[1]))->getEquivalentPHPScalarValue();
                if (!\is_int($flags)) {
                    // Could warn here, low priority
                    $flags = null;
                }
            } else {
                $flags = null;
            }

            $prefix = isset($args[2]) ? (new ContextNode($code_base, $context, $args[2]))->getEquivalentPHPScalarValue() : null;

            $shape = ArrayShapeType::union($array_shape_types);
            if (!\is_scalar($prefix)) {
                $prefix = '';
            }
            $prefix = (string)$prefix;
            $scope = $context->getScope();

            foreach ($shape->getFieldTypes() as $field_name => $field_type) {
                if (!\is_string($field_name)) {
                    continue;
                }
                $add_variable = static function (string $name) use ($context, $field_type, $scope): void {
                    if (!Variable::isValidIdentifier($name)) {
                        return;
                    }
                    if (Variable::isSuperglobalVariableWithName($name)) {
                        return;
                    }
                    $scope->addVariable(new Variable(
                        $context,
                        $name,
                        $field_type,
                        0
                    ));
                };
                // TODO: Ignore superglobals

                // Some parts of this are probably wrong - EXTR_OVERWRITE and EXTR_SKIP are probably the most common?
                switch ($flags & ~\EXTR_REFS) {
                    default:
                    case \EXTR_OVERWRITE:
                        $add_variable($field_name);
                        break;
                    case \EXTR_SKIP:
                        if ($scope->hasVariableWithName($field_name)) {
                            break;
                        }
                        $add_variable($field_name);
                        break;
                    // TODO: Do all of these behave like EXTR_OVERWRITE or like EXTR_SKIP?
                    case \EXTR_PREFIX_SAME:
                        if ($scope->hasVariableWithName($field_name)) {
                            $field_name = $prefix . $field_name;
                        }
                        $add_variable($field_name);
                        break;
                    case \EXTR_PREFIX_ALL:
                        $field_name = $prefix . $field_name;
                        $add_variable($field_name);
                        break;
                    case \EXTR_PREFIX_INVALID:
                        if (!Variable::isValidIdentifier($field_name)) {
                            $field_name = $prefix . $field_name;
                        }
                        $add_variable($field_name);
                        break;
                    case \EXTR_IF_EXISTS:
                        if ($scope->hasVariableWithName($field_name)) {
                            $add_variable($field_name);
                        }
                        break;
                    case \EXTR_PREFIX_IF_EXISTS:
                        if ($scope->hasVariableWithName($field_name) && $prefix !== '') {
                            $add_variable($prefix . $field_name);
                        }
                        break;
                }
            }
        };

        /**
         * Most of the work was already done in ParseVisitor
         * @param list<Node|int|float|string> $args
         * @see \Phan\Parse\ParseVisitor::analyzeDefine()
         */
        $define_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ): void {
            if (count($args) < 2) {
                return;
            }
            $name = $args[0];
            $value = $args[1];
            if (isset($args[2])) {
                $case_sensitive_arg_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[2]);
                if (!$case_sensitive_arg_type->isType(FalseType::instance(false))) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::DeprecatedCaseInsensitiveDefine,
                        $args[2]->lineno ?? $context->getLineNumberStart()
                    );
                }
            }
            if (\is_scalar($name) && (\is_scalar($value) || $value->kind === \ast\AST_CONST)) {
                // We already parsed this in ParseVisitor
                return;
            }
            if ($name instanceof Node) {
                try {
                    $name_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $name, false);
                } catch (IssueException $_) {
                    // If this is really an issue, we'll emit it in the analysis phase when we have all of the element definitions.
                    return;
                }
                $name = $name_type->asSingleScalarValueOrNull();
            }

            if (!\is_string($name)) {
                return;
            }
            ParseVisitor::addConstant(
                $code_base,
                $context,
                $context->getLineNumberStart(),
                $name,
                $args[1],
                0,
                '',
                false,
                true
            );
        };

        /**
         * @param list<Node|int|float|string> $args
         */
        $class_alias_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ): void {
            if (count($args) < 2) {
                return;
            }

            $class_alias_first_param = $args[0];

            if ($class_alias_first_param instanceof Node) {
                try {
                    $name_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $class_alias_first_param, false);
                } catch (IssueException $_) {
                    return;
                }

                $class_alias_first_param = $name_type->asSingleScalarValueOrNull();
            }

            if (\is_string($class_alias_first_param)) {
                try {
                    $first_param_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_alias_first_param);
                    if ($code_base->hasClassWithFQSEN($first_param_fqsen)) {
                        $class = $code_base->getClassByFQSEN($first_param_fqsen);
                        if ($class->isPHPInternal()) {
                            Issue::maybeEmit(
                                $code_base,
                                $context,
                                Issue::ParamMustBeUserDefinedClassname,
                                $args[0]->lineno ?? $context->getLineNumberStart(),
                                $class->getName()
                            );
                        }
                    }
                } catch (FQSENException $_) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeComparisonToInvalidClass,
                        $context->getLineNumberStart(),
                        $class_alias_first_param
                    );
                }
            }
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $in_array_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $node
        ): void {
            if (count($args) < 2) {
                return;
            }
            if (!self::shouldWarnAboutImpossibleInArray($code_base, $context, $args)) {
                return;
            }
            self::emitIssueForInArray($code_base, $context, $args, $node);
        };
        /**
         * @param list<Node|int|float|string> $args
         */
        $array_key_exists_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $node
        ): void {
            if (count($args) < 2) {
                return;
            }
            if (!self::shouldWarnAboutImpossibleArrayKeyExists($code_base, $context, $args)) {
                return;
            }
            self::emitIssueForArrayKeyExists($code_base, $context, $args, $node);
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

            'array_splice' => $array_splice_callback,
            // Convert arrays to lists
            'sort' => $sort_callback,
            'rsort' => $sort_callback,
            'usort' => $sort_callback,
            'natcasesort' => $sort_callback,
            'natsort' => $sort_callback,
            'shuffle' => $sort_callback,

            'asort' => $associative_sort_callback,
            'arsort' => $associative_sort_callback,
            'uasort' => $associative_sort_callback,
            'ksort' => $associative_sort_callback,
            'krsort' => $associative_sort_callback,
            'uksort' => $associative_sort_callback,

            'extract' => $extract_callback,

            'join' => $join_callback,
            'implode' => $join_callback,

            'min' => $min_max_callback,
            'max' => $min_max_callback,

            'define' => $define_callback,

            'class_alias' => $class_alias_callback,

            'in_array' => $in_array_callback,
            'array_search' => $in_array_callback,
            'array_key_exists' => $array_key_exists_callback,
        ];
    }

    /**
     * @param Codebase $code_base @phan-unused-param
     * @return array<string,Closure>
     * @phan-return array<string,Closure(CodeBase,Context,FunctionInterface,array):void>
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $analyzers;
    }

    /**
     * @param Node|int|string|float|null $node
     * @param Closure(UnionType):IssueInstance $issue_instance
     */
    private static function analyzeNodeUnionTypeCast(
        $node,
        Context $context,
        CodeBase $code_base,
        UnionType $cast_type,
        Closure $issue_instance
    ): bool {

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
     * @param Node|int|string|float|null $node
     * @param Closure(UnionType):IssueInstance $issue_instance
     */
    private static function analyzeNodeUnionTypeCastStringArrayLike(
        $node,
        Context $context,
        CodeBase $code_base,
        Closure $issue_instance
    ): bool {

        // Get the type of the node
        $node_type = UnionTypeVisitor::unionTypeFromNode(
            $code_base,
            $context,
            $node,
            true
        );

        // See if it can be cast to the given type
        if (self::canCastToStringArrayLike($code_base, $context, $node_type)) {
            return true;
        }

        // If it can't, emit the log message
        Issue::maybeEmitInstance(
            $code_base,
            $context,
            $issue_instance($node_type)
        );

        return false;
    }

    /**
     * Sadly, MyStringable[] is frequently used, so we need this check.
     */
    private static function canCastToStringArrayLike(CodeBase $code_base, Context $context, UnionType $union_type): bool
    {
        if ($union_type->canCastToUnionType(
            UnionType::fromFullyQualifiedPHPDocString('string[]|int[]')
        )) {
            return true;
        }
        return $union_type->genericArrayElementTypes()->hasClassWithToStringMethod($code_base, $context);
    }
}
