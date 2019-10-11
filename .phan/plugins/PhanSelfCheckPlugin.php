<?php declare(strict_types=1);

namespace Phan\Plugin\PhanSelfCheckPlugin;  // Don't pollute the global namespace

use ast\Node;
use Closure;
use InvalidArgumentException;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type\ArrayShapeType;
use Phan\Library\ConversionSpec;
use Phan\Library\StringUtil;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use function count;
use function is_string;

/**
 * This plugin checks for invalid calls to emitIssue, emitPluginIssue, Issue::maybeEmit(), etc.
 * This is useful for developing Phan plugins.
 *
 * This uses ConversionSpec as a heuristic to determine the number of arguments to format strings.
 * This currently does not try to check types of arguments.
 *
 * NOTE: This does not check Issue::fromType($typename)(...args)
 */
class PhanSelfCheckPlugin extends PluginV3 implements AnalyzeFunctionCallCapability
{
    const TooManyArgumentsForIssue = 'PhanPluginTooManyArgumentsForIssue';
    const TooFewArgumentsForIssue = 'PhanPluginTooFewArgumentsForIssue';
    const UnknownIssueType = 'PhanPluginUnknownIssueType';

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return Closure[]
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        /**
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>):void
         */
        $make_array_issue_callback = static function (int $fmt_index, int $arg_index) : Closure {
            /**
             * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                FunctionInterface $unused_function,
                array $args
            ) use (
                $fmt_index,
                $arg_index
) : void {
                if (\count($args) <= $fmt_index) {
                    return;
                }
                // TODO: Check for AST_UNPACK
                $issue_message_template = $args[$fmt_index];
                if ($issue_message_template instanceof Node) {
                    $issue_message_template = (new ContextNode($code_base, $context, $issue_message_template))->getEquivalentPHPScalarValue();
                }
                if (!is_string($issue_message_template)) {
                    return;
                }
                $issue_message_arg_count = self::computeArraySize($code_base, $context, $args[$arg_index] ?? null);
                if ($issue_message_arg_count === null) {
                    return;
                }
                self::checkIssueTemplateUsage($code_base, $context, $issue_message_template, $issue_message_arg_count);
            };
        };
        /**
         * @param int $type_index the index of a parameter expecting an issue type (e.g. PhanParamTooMany)
         * @param int $arg_index the index of an array parameter expecting sequential arguments. This is >= $type_index.
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>):void
         */
        $make_type_and_parameters_callback = static function (int $type_index, int $arg_index) : Closure {
            /**
             * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                FunctionInterface $function,
                array $args
            ) use (
                $type_index,
                $arg_index
) : void {
                if (\count($args) <= $type_index) {
                    return;
                }
                // TODO: Check for AST_UNPACK
                $issue_type = $args[$type_index];
                if ($issue_type instanceof Node) {
                    $issue_type = (new ContextNode($code_base, $context, $issue_type))->getEquivalentPHPScalarValue();
                }
                if (!is_string($issue_type)) {
                    return;
                }
                $issue = self::getIssueOrWarn($code_base, $context, $function, $issue_type);
                if (!$issue) {
                    return;
                }
                $issue_message_arg_count = self::computeArraySize($code_base, $context, $args[$arg_index] ?? null);
                if ($issue_message_arg_count === null) {
                    return;
                }
                self::checkIssueTemplateUsage($code_base, $context, $issue->getTemplate(), $issue_message_arg_count);
            };
        };
        /**
         * @param int $type_index the index of a parameter expecting an issue type (e.g. PhanParamTooMany)
         * @param int $arg_index the index of an array parameter expecting variable arguments. This is >= $type_index.
         * @return Closure(CodeBase, Context, FunctionInterface, list<mixed>):void
         */
        $make_type_and_varargs_callback = static function (int $type_index, int $arg_index) : Closure {
            /**
             * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
             */
            return static function (
                CodeBase $code_base,
                Context $context,
                FunctionInterface $function,
                array $args
            ) use (
                $type_index,
                $arg_index
) : void {
                if (\count($args) <= $type_index) {
                    return;
                }
                // TODO: Check for AST_UNPACK
                $issue_type = $args[$type_index];
                if ($issue_type instanceof Node) {
                    $issue_type = (new ContextNode($code_base, $context, $issue_type))->getEquivalentPHPScalarValue();
                }
                if (!is_string($issue_type)) {
                    return;
                }
                $issue = self::getIssueOrWarn($code_base, $context, $function, $issue_type);
                if (!$issue) {
                    return;
                }
                if ((\end($args)->kind ?? null) === \ast\AST_UNPACK) {
                    // give up
                    return;
                }
                // number of args passed to varargs. >= 0 if valid.
                $issue_message_arg_count = count($args) - $arg_index;
                if ($issue_message_arg_count < 0) {
                    // should already emit PhanParamTooFew
                    return;
                }
                self::checkIssueTemplateUsage($code_base, $context, $issue->getTemplate(), $issue_message_arg_count);
            };
        };
        /**
         * Analyzes a call to plugin->emitIssue($code_base, $context, $issue_type, $issue_message_fmt, $args)
         */
        $short_emit_issue_callback = $make_type_and_varargs_callback(0, 2);

        $results = [
            '\Phan\AST\ContextNode::emitIssue' => $short_emit_issue_callback,
            '\Phan\Issue::emit' => $make_type_and_varargs_callback(0, 3),
            '\Phan\Issue::emitWithParameters' => $make_type_and_parameters_callback(0, 3),
            '\Phan\Issue::maybeEmit' => $make_type_and_varargs_callback(2, 4),
            '\Phan\Issue::maybeEmitWithParameters' => $make_type_and_parameters_callback(2, 4),
            '\Phan\Analysis\BinaryOperatorFlagVisitor::emitIssue' => $short_emit_issue_callback,
            '\Phan\Language\Element\Comment\Builder::emitIssue' => $make_type_and_parameters_callback(0, 2),
        ];
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        $emit_plugin_issue_fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\Phan\PluginV3\IssueEmitter::emitPluginIssue');
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        $analysis_visitor_fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\Phan\AST\AnalysisVisitor::emitIssue');

        $emit_plugin_issue_callback = $make_array_issue_callback(3, 4);
        foreach ($code_base->getMethodSet() as $method) {
            $real_fqsen = $method->getRealDefiningFQSEN();
            if ($real_fqsen === $emit_plugin_issue_fqsen) {
                $results[(string)$method->getFQSEN()] = $emit_plugin_issue_callback;
            } elseif ($real_fqsen === $analysis_visitor_fqsen) {
                $results[(string)$method->getFQSEN()] = $short_emit_issue_callback;
            }
        }
        return $results;
    }

    private static function getIssueOrWarn(CodeBase $code_base, Context $context, FunctionInterface $function, string $issue_type) : ?Issue
    {
        try {
            return Issue::fromType($issue_type);
        } catch (InvalidArgumentException $_) {
            self::emitIssue(
                $code_base,
                $context,
                self::UnknownIssueType,
                'Unknown issue type {STRING_LITERAL} in a call to {METHOD}(). (may be a false positive - check if the version of Phan running PhanSelfCheckPlugin is the same version that the analyzed codebase is using)',
                [$issue_type, $function->getFQSEN()]
            );
            return null;
        }
    }

    private static function checkIssueTemplateUsage(CodeBase $code_base, Context $context, string $issue_message_template, int $issue_message_arg_count) : void
    {
        $issue_message_format_string = Issue::templateToFormatString($issue_message_template);
        $expected_arg_count = ConversionSpec::computeExpectedArgumentCount($issue_message_format_string);
        if ($expected_arg_count === $issue_message_arg_count) {
            return;
        }
        if ($issue_message_arg_count > $expected_arg_count) {
            self::emitIssue(
                $code_base,
                $context,
                self::TooManyArgumentsForIssue,
                'Too many arguments for issue {STRING_LITERAL}: expected {COUNT}, got {COUNT}',
                [StringUtil::jsonEncode($issue_message_template), $expected_arg_count, $issue_message_arg_count],
                Issue::SEVERITY_NORMAL
            );
        } else {
            self::emitIssue(
                $code_base,
                $context,
                self::TooFewArgumentsForIssue,
                'Too few arguments for issue {STRING_LITERAL}: expected {COUNT}, got {COUNT}',
                [StringUtil::jsonEncode($issue_message_template), $expected_arg_count, $issue_message_arg_count],
                Issue::SEVERITY_CRITICAL
            );
        }
    }

    /**
     * @param Node|mixed $arg
     */
    private static function computeArraySize(CodeBase $code_base, Context $context, $arg) : ?int
    {
        if ($arg === null) {
            return 0;
        }
        $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg);
        if ($union_type->typeCount() !== 1) {
            return null;
        }
        $types = $union_type->getTypeSet();
        $array_shape_type = \reset($types);
        if (!$array_shape_type instanceof ArrayShapeType) {
            return null;
        }
        $field_types = $array_shape_type->getFieldTypes();
        foreach ($field_types as $field_type) {
            if ($field_type->isPossiblyUndefined()) {
                return null;
            }
        }
        return count($field_types);
    }
}

return new PhanSelfCheckPlugin();
