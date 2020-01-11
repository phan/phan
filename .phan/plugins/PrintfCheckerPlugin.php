<?php

declare(strict_types=1);

namespace Phan\Plugin\PrintfCheckerPlugin;

// Don't pollute the global namespace

use ast;
use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\LiteralStringType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Library\ConversionSpec;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\PluginV3\ReturnTypeOverrideCapability;
use Throwable;

use function count;
use function implode;
use function is_object;
use function is_string;
use function strcasecmp;
use function var_export;

/**
 * This plugin checks for invalid format strings and invalid uses of format strings in printf and sprintf, etc.
 * e.g. for printf("literal format %s", $arg)
 *
 * This uses ConversionSpec as a heuristic to determine the positions used by PHP format strings.
 * Some edge cases may have been overlooked.
 *
 * This validates strings of the form
 * -    constant strings, such as '%d of %s'
 * -    TODO: _(str) and gettext(str)
 * -    TODO: Better resolution of global constants and class constants
 *
 * This analyzes printf, sprintf, and fprintf.
 *
 * TODO: Add optional verbose warnings about unanalyzable strings
 * TODO: Check if arg can cast to string.
 */
class PrintfCheckerPlugin extends PluginV3 implements AnalyzeFunctionCallCapability, ReturnTypeOverrideCapability
{

    // Pylint error codes for emitted issues.
    private const ERR_UNTRANSLATED_USE_ECHO                = 1300;
    private const ERR_UNTRANSLATED_NONE_USED               = 1301;
    private const ERR_UNTRANSLATED_NONEXISTENT             = 1302;
    private const ERR_UNTRANSLATED_UNUSED                  = 1303;
    private const ERR_UNTRANSLATED_NOT_PERCENT             = 1304;
    private const ERR_UNTRANSLATED_INCOMPATIBLE_SPECIFIER  = 1305;
    private const ERR_UNTRANSLATED_INCOMPATIBLE_ARGUMENT   = 1306;  // E.g. passing a string where an int is expected
    private const ERR_UNTRANSLATED_INCOMPATIBLE_ARGUMENT_WEAK = 1307;  // E.g. passing an int where a string is expected
    private const ERR_UNTRANSLATED_WIDTH_INSTEAD_OF_POSITION = 1308; // e.g. _('%1s'). Change to _('%1$1s' if you really mean that the width is 1, add positions for others ('%2$s', etc.)
    private const ERR_UNTRANSLATED_UNKNOWN_FORMAT_STRING   = 1310;
    private const ERR_TRANSLATED_INCOMPATIBLE              = 1309;
    private const ERR_TRANSLATED_HAS_MORE_ARGS             = 1311;

    /**
     * People who have translations may subclass this plugin and return a mapping from other locales to those locales translations of $fmt_str.
     * @param string $fmt_str @phan-unused-param
     * @return string[] mapping locale to the translation (e.g. ['fr_FR' => 'Bonjour'] for $fmt_str == 'Hello')
     */
    protected static function gettextForAllLocales(string $fmt_str): array
    {
        return [];
    }

    /**
     * Convert an expression(a list of tokens) to a primitive.
     * People who have custom such as methods or functions to fetch translations
     * may subclass this plugin and override this method to add checks for AST_CALL (foo()), AST_METHOD_CALL(MyClass::getTranslation($id), etc.)
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param bool|int|string|float|Node|array|null $ast_node
     */
    protected function astNodeToPrimitive(CodeBase $code_base, Context $context, $ast_node): ?PrimitiveValue
    {
        // Base case: convert primitive tokens such as numbers and strings.
        if (!($ast_node instanceof Node)) {
            return new PrimitiveValue($ast_node);
        }
        switch ($ast_node->kind) {
            // TODO: Resolve class constant access when those are format strings. Same for PregRegexCheckerPlugin.
            case \ast\AST_CALL:
                $name_node = $ast_node->children['expr'];
                if ($name_node instanceof Node && $name_node->kind === \ast\AST_NAME) {
                    // TODO: Use Phan's function resolution?
                    // TODO: ngettext?
                    $name = $name_node->children['name'];
                    if (!\is_string($name)) {
                        break;
                    }
                    if ($name === '_' || strcasecmp($name, 'gettext') === 0) {
                        $child_arg = $ast_node->children['args']->children[0] ?? null;
                        if ($child_arg === null) {
                            break;
                        }
                        $prim = self::astNodeToPrimitive($code_base, $context, $child_arg);
                        if ($prim === null) {
                            break;
                        }
                        return new PrimitiveValue($prim->value, true);
                    }
                }
                break;
            case \ast\AST_BINARY_OP:
                if ($ast_node->flags !== ast\flags\BINARY_CONCAT) {
                    break;
                }
                $left = $this->astNodeToPrimitive($code_base, $context, $ast_node->children['left']);
                if ($left === null) {
                    break;
                }
                $right = $this->astNodeToPrimitive($code_base, $context, $ast_node->children['right']);
                if ($right === null) {
                    break;
                }
                $result = self::concatenateToPrimitive($left, $right);
                if ($result) {
                    return $result;
                }
                break;
        }
        $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $ast_node);
        $result = $union_type->asSingleScalarValueOrNullOrSelf();

        if (!is_object($result)) {
            return new PrimitiveValue($result);
        }
        $scalar_union_types = $union_type->asScalarValues();
        if (!$scalar_union_types) {
            // We don't know how to convert this to a primitive, give up.
            // (Subclasses may add their own logic first, then call self::astNodeToPrimitive)
            return null;
        }
        $known_specs = null;
        $first_str = null;
        foreach ($union_type->getTypeSet() as $type) {
            if (!$type instanceof LiteralStringType || $type->isNullable()) {
                return null;
            }
            $str = $type->getValue();
            $new_specs = ConversionSpec::extractAll($str);
            if (\is_array($known_specs)) {
                if ($known_specs != $new_specs) {
                    // We have different specs, e.g. %s and %d, %1$s and %2$s, etc.
                    // TODO: Could allow differences in padding or alignment
                    return null;
                }
            } else {
                $known_specs = $new_specs;
                $first_str = $str;
            }
        }
        return new PrimitiveValue($first_str);
    }

    /**
     * Convert a primitive and a sequence of tokens to a primitive formed by
     * concatenating strings.
     *
     * @param PrimitiveValue $left the value on the left.
     * @param PrimitiveValue $right the value on the right.
     */
    protected static function concatenateToPrimitive(PrimitiveValue $left, PrimitiveValue $right): ?PrimitiveValue
    {
        // Combining untranslated strings with anything will cause problems.
        if ($left->is_translated) {
            return null;
        }
        if ($right->is_translated) {
            return null;
        }
        $str = $left->value . $right->value;
        return new PrimitiveValue($str);
    }

    public function getReturnTypeOverrides(CodeBase $unused_code_base): array
    {
        $string_union_type = StringType::instance(false)->asPHPDocUnionType();
        /**
         * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
         */
        $sprintf_handler = static function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) use ($string_union_type): UnionType {
            if (count($args) < 1) {
                return FalseType::instance(false)->asRealUnionType();
            }
            $union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
            $format_strings = [];
            foreach ($union_type->getTypeSet() as $type) {
                if (!$type instanceof LiteralStringType) {
                    return $string_union_type;
                }
                $format_strings[] = $type->getValue();
            }
            if (count($format_strings) === 0) {
                return $string_union_type;
            }
            $result_union_type = UnionType::empty();
            foreach ($format_strings as $format_string) {
                $min_width = 0;
                foreach (ConversionSpec::extractAll($format_string) as $spec_group) {
                    foreach ($spec_group as $spec) {
                        $min_width += ($spec->width ?: 0);
                    }
                }
                if (!LiteralStringType::canRepresentStringOfLength($min_width)) {
                    return $string_union_type;
                }
                $sprintf_args = [];
                for ($i = 1; $i < count($args); $i++) {
                    $arg = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[$i])->asSingleScalarValueOrNullOrSelf();
                    if (is_object($arg)) {
                        return $string_union_type;
                    }
                    $sprintf_args[] = $arg;
                }
                try {
                    $result = \with_disabled_phan_error_handler(
                        /** @return string|false */
                        static function () use ($format_string, $sprintf_args) {
                            // @phan-suppress-next-line PhanPluginPrintfVariableFormatString
                            return @\vsprintf($format_string, $sprintf_args);
                        }
                    );
                } catch (Throwable $e) {
                    // PHP 8 throws ValueError for too few arguments to vsprintf
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeErrorInInternalCall,
                        $args[0]->lineno ?? $context->getLineNumberStart(),
                        $function->getName(),
                        $e->getMessage()
                    );
                    // TODO: When PHP 8.0 stable is out, replace this with string?
                    $result = false;
                }
                $result_union_type = $result_union_type->withType(Type::fromObject($result));
            }
            return $result_union_type;
        };
        return [
            'sprintf'                     => $sprintf_handler,
        ];
    }

    /**
     * @param CodeBase $code_base @phan-unused-param
     * @return \Closure[]
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        /**
         * Analyzes a printf-like function with a format directive in the first position.
         * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
         */
        $printf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ): void {
            // TODO: Resolve global constants and class constants?
            // TODO: Check for AST_UNPACK
            $pattern = $args[0] ?? null;
            if ($pattern === null) {
                return;
            }
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPScalarValue();
            }
            $remaining_args = \array_slice($args, 1);
            $this->analyzePrintfPattern($code_base, $context, $function, $pattern, $remaining_args);
        };
        /**
         * Analyzes a printf-like function with a format directive in the first position.
         * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
         */
        $fprintf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ): void {
            if (\count($args) < 2) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            // TODO: Check for AST_UNPACK
            $pattern = $args[1];
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPValue();
            }
            $remaining_args = \array_slice($args, 2);
            $this->analyzePrintfPattern($code_base, $context, $function, $pattern, $remaining_args);
        };
        /**
         * Analyzes a printf-like function with a format directive in the first position.
         * @param list<Node|int|string|float> $args
         */
        $vprintf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ): void {
            if (\count($args) < 2) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            // TODO: Check for AST_UNPACK
            $pattern = $args[0];
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPScalarValue();
            }
            $format_args_node = $args[1];
            $format_args = (new ContextNode($code_base, $context, $format_args_node))->getEquivalentPHPValue();
            $this->analyzePrintfPattern($code_base, $context, $function, $pattern, \is_array($format_args) ? $format_args : null);
        };
        /**
         * Analyzes a printf-like function with a format directive in the first position.
         * @param list<Node|string|int|float> $args the nodes for the arguments to the invocation
         */
        $vfprintf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ): void {
            if (\count($args) < 3) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            // TODO: Check for AST_UNPACK
            $pattern = $args[1];
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPScalarValue();
            }
            $format_args_node = $args[2];
            $format_args = (new ContextNode($code_base, $context, $format_args_node))->getEquivalentPHPValue();
            $this->analyzePrintfPattern($code_base, $context, $function, $pattern, \is_array($format_args) ? $format_args : null);
        };
        return [
            // call
            'printf'     => $printf_callback,
            'sprintf'    => $printf_callback,
            'fprintf'    => $fprintf_callback,
            'vprintf'    => $vprintf_callback,
            'vsprintf'   => $vprintf_callback,
            'vfprintf'   => $vfprintf_callback,
        ];
    }

    protected static function encodeString(string $str): string
    {
        $result = \json_encode($str, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if ($result !== false) {
            return $result;
        }
        return var_export($str, true);
    }

    /**
     * Analyzes a printf pattern, emitting issues if necessary
     * @param CodeBase $code_base
     * @param Context $context
     * @param FunctionInterface $function
     * @param Node|array|string|float|int|bool|resource|null $pattern_node
     * @param ?(Node|string|int|float)[] $arg_nodes arguments following the format string. Null if the arguments could not be determined.
     * @suppress PhanPartialTypeMismatchArgument TODO: refactor into smaller functions
     */
    protected function analyzePrintfPattern(CodeBase $code_base, Context $context, FunctionInterface $function, $pattern_node, $arg_nodes): void
    {
        // Given a node, extract the printf directive and whether or not it could be translated
        $primitive_for_fmtstr = $this->astNodeToPrimitive($code_base, $context, $pattern_node);
        /**
         * @param string $issue_type
         * A name for the type of issue such as 'PhanPluginMyIssue'
         *
         * @param string $issue_message_format
         * The complete issue message format string to emit such as
         * 'class with fqsen {CLASS} is broken in some fashion' (preferred)
         * or 'class with fqsen %s is broken in some fashion'
         * The list of placeholders for between braces can be found
         * in \Phan\Issue::uncolored_format_string_for_template.
         *
         * @param list<string|float|int> $issue_message_args
         * The arguments for this issue format.
         * If this array is empty, $issue_message_args is kept in place
         *
         * @param int $severity
         * A value from the set {Issue::SEVERITY_LOW,
         * Issue::SEVERITY_NORMAL, Issue::SEVERITY_HIGH}.
         *
         * @param int $issue_type_id An issue id for pylint
         */
        $emit_issue = static function (string $issue_type, string $issue_message_format, array $issue_message_args, int $severity, int $issue_type_id) use ($code_base, $context): void {
            self::emitIssue(
                $code_base,
                $context,
                $issue_type,
                $issue_message_format,
                $issue_message_args,
                $severity,
                Issue::REMEDIATION_B,
                $issue_type_id
            );
        };
        if ($primitive_for_fmtstr === null) {
            $emit_issue(
                'PhanPluginPrintfVariableFormatString',
                'Code {CODE} has a dynamic format string that could not be inferred by Phan',
                [ASTReverter::toShortString($pattern_node)],
                Issue::SEVERITY_LOW,
                self::ERR_UNTRANSLATED_UNKNOWN_FORMAT_STRING
            );
            if (\is_array($arg_nodes) && count($arg_nodes) === 0) {
                $replacement_function_name = \in_array($function->getName(), ['vprintf', 'fprintf', 'vfprintf'], true) ? 'fwrite' : 'echo';
                $emit_issue(
                    "PhanPluginPrintfNoArguments",
                    "No format string arguments are given for {STRING_LITERAL}, consider using {FUNCTION} instead",
                    ['(unknown)', $replacement_function_name],
                    Issue::SEVERITY_LOW,
                    self::ERR_UNTRANSLATED_USE_ECHO
                );
                return;
            }
            // TODO: Add a verbose option
            return;
        }
        // Make sure that the untranslated format string is being used correctly.
        // If the format string will be translated, also check the translations.

        $fmt_str = $primitive_for_fmtstr->value;
        $is_translated = $primitive_for_fmtstr->is_translated;
        $specs = is_string($fmt_str) ? ConversionSpec::extractAll($fmt_str) : [];
        $fmt_str = (string)$fmt_str;

        // Check for extra or missing arguments
        if (\is_array($arg_nodes) && \count($arg_nodes) === 0) {
            if (count($specs) > 0) {
                $largest_positional = \max(\array_keys($specs));
                $examples = [];
                foreach ($specs[$largest_positional] as $example_spec) {
                    $examples[] = self::encodeString($example_spec->directive);
                }
                // emit issues with 1-based offsets
                $emit_issue(
                    'PhanPluginPrintfNonexistentArgument',
                    'Format string {STRING_LITERAL} refers to nonexistent argument #{INDEX} in {STRING_LITERAL}. This will be an ArgumentCountError in PHP 8',
                    [self::encodeString($fmt_str), $largest_positional, \implode(',', $examples)],
                    Issue::SEVERITY_CRITICAL,
                    self::ERR_UNTRANSLATED_NONEXISTENT
                );
            }
            $replacement_function_name = \in_array($function->getName(), ['vprintf', 'fprintf', 'vfprintf'], true) ? 'fwrite' : 'echo';
            $emit_issue(
                "PhanPluginPrintfNoArguments",
                "No format string arguments are given for {STRING_LITERAL}, consider using {FUNCTION} instead",
                [self::encodeString($fmt_str), $replacement_function_name],
                Issue::SEVERITY_LOW,
                self::ERR_UNTRANSLATED_USE_ECHO
            );
            return;
        }
        if (count($specs) === 0) {
            $emit_issue(
                'PhanPluginPrintfNoSpecifiers',
                'None of the formatting arguments passed alongside format string {STRING_LITERAL} are used',
                [self::encodeString($fmt_str)],
                Issue::SEVERITY_LOW,
                self::ERR_UNTRANSLATED_NONE_USED
            );
            return;
        }

        if (\is_array($arg_nodes)) {
            $largest_positional = \max(\array_keys($specs));
            if ($largest_positional > \count($arg_nodes)) {
                $examples = [];
                foreach ($specs[$largest_positional] as $example_spec) {
                    $examples[] = self::encodeString($example_spec->directive);
                }
                // emit issues with 1-based offsets
                $emit_issue(
                    'PhanPluginPrintfNonexistentArgument',
                    'Format string {STRING_LITERAL} refers to nonexistent argument #{INDEX} in {STRING_LITERAL}',
                    [self::encodeString($fmt_str), $largest_positional, \implode(',', $examples)],
                    Issue::SEVERITY_NORMAL,
                    self::ERR_UNTRANSLATED_NONEXISTENT
                );
            } elseif ($largest_positional < count($arg_nodes)) {
                $emit_issue(
                    'PhanPluginPrintfUnusedArgument',
                    'Format string {STRING_LITERAL} does not use provided argument #{INDEX}',
                    [self::encodeString($fmt_str), $largest_positional + 1],
                    Issue::SEVERITY_NORMAL,
                    self::ERR_UNTRANSLATED_UNUSED
                );
            }
        }

        /** @var string[][] maps argument position to a list of possible canonical strings (e.g. '%1$d') for that argument */
        $types_of_arg = [];

        // Check format string alone for common signs of problems.
        // E.g. "% s", "%1$d %1$s"
        foreach ($specs as $i => $spec_group) {
            $types = [];
            foreach ($spec_group as $spec) {
                $canonical = $spec->toCanonicalString();
                $types[$canonical] = true;
                if ((\strlen($spec->padding_char) > 0 || \strlen($spec->alignment)) && ($spec->width === '' || !$spec->position)) {
                    // Warn about "100% dollars" but not about "100%1$ 2dollars" (If both position and width were parsed, assume the padding was intentional)
                    $emit_issue(
                        'PhanPluginPrintfNotPercent',
                        // phpcs:ignore Generic.Files.LineLength.MaxExceeded
                        "Format string {STRING_LITERAL} contains something that is not a percent sign, it will be treated as a format string '{STRING_LITERAL}' with padding of \"{STRING_LITERAL}\" and alignment of '{STRING_LITERAL}' but no width. Use {DETAILS} for a literal percent sign, or '{STRING_LITERAL}' to be less ambiguous",
                        [self::encodeString($fmt_str), $spec->directive, $spec->padding_char, $spec->alignment, '%%', $canonical],
                        Issue::SEVERITY_NORMAL,
                        self::ERR_UNTRANSLATED_NOT_PERCENT
                    );
                }
                if ($is_translated && $spec->width &&
                        ($spec->padding_char === '' || $spec->padding_char === ' ')
                ) {
                    $intended_string = $spec->toCanonicalStringWithWidthAsPosition();
                    $emit_issue(
                        'PhanPluginPrintfWidthNotPosition',
                        "Format string {STRING_LITERAL} is specifying a width({STRING_LITERAL}) instead of a position({STRING_LITERAL})",
                        [self::encodeString($fmt_str), self::encodeString($canonical), self::encodeString($intended_string)],
                        Issue::SEVERITY_NORMAL,
                        self::ERR_UNTRANSLATED_WIDTH_INSTEAD_OF_POSITION
                    );
                }
            }

            $types_of_arg[$i] = $types;
            if (count($types) > 1) {
                // May be an off by one error in the format string.
                $emit_issue(
                    'PhanPluginPrintfIncompatibleSpecifier',
                    'Format string {STRING_LITERAL} refers to argument #{INDEX} in different ways: {DETAILS}',
                    [self::encodeString($fmt_str), $i, implode(',', \array_keys($types))],
                    Issue::SEVERITY_LOW,
                    self::ERR_UNTRANSLATED_INCOMPATIBLE_SPECIFIER
                );
            }
        }

        if (\is_array($arg_nodes)) {
            foreach ($specs as $i => $spec_group) {
                // $arg_nodes is a 0-based array, $spec_group is 1-based.
                $arg_node = $arg_nodes[$i - 1] ?? null;
                if (!isset($arg_node)) {
                    continue;
                }
                $actual_union_type = UnionTypeVisitor::unionTypeFromNode($code_base, $context, $arg_node);
                if ($actual_union_type->isEmpty()) {
                    // Nothing to check.
                    continue;
                }

                $expected_set = [];
                foreach ($spec_group as $spec) {
                    $type_name = $spec->getExpectedUnionTypeName();
                    $expected_set[$type_name] = true;
                }
                $expected_union_type = UnionType::empty();
                foreach ($expected_set as $type_name => $_) {
                    // @phan-suppress-next-line PhanThrowTypeAbsentForCall getExpectedUnionTypeName should only return valid union types
                    $expected_union_type = $expected_union_type->withType(Type::fromFullyQualifiedString($type_name));
                }
                if ($actual_union_type->canCastToUnionType($expected_union_type)) {
                    continue;
                }
                if (isset($expected_set['string'])) {
                    $can_cast_to_string = false;
                    // Allow passing objects with __toString() to printf whether or not strict types are used in the caller.
                    // TODO: Move into a common helper method?
                    try {
                        foreach ($actual_union_type->asExpandedTypes($code_base)->asClassList($code_base, $context) as $clazz) {
                            if ($clazz->hasMethodWithName($code_base, '__toString')) {
                                $can_cast_to_string = true;
                                break;
                            }
                        }
                    } catch (CodeBaseException $_) {
                        // Swallow "Cannot find class", go on to emit issue.
                    }
                    if ($can_cast_to_string) {
                        continue;
                    }
                }

                $expected_union_type_string = (string)$expected_union_type;
                if (self::canWeakCast($actual_union_type, $expected_set)) {
                    // This can be resolved by casting the arg to (string) manually in printf.
                    $emit_issue(
                        'PhanPluginPrintfIncompatibleArgumentTypeWeak',
                        // phpcs:ignore Generic.Files.LineLength.MaxExceeded
                        'Format string {STRING_LITERAL} refers to argument #{INDEX} as {DETAILS}, so type {TYPE} is expected. However, {FUNCTION} was passed the type {TYPE} (which is weaker than {TYPE})',
                        [
                            self::encodeString($fmt_str),
                            $i,
                            self::getSpecStringsRepresentation($spec_group),
                            $expected_union_type_string,
                            $function->getName(),
                            (string)$actual_union_type,
                            $expected_union_type_string,
                        ],
                        Issue::SEVERITY_LOW,
                        self::ERR_UNTRANSLATED_INCOMPATIBLE_ARGUMENT_WEAK
                    );
                } else {
                    // This can be resolved by casting the arg to (int) manually in printf.
                    $emit_issue(
                        'PhanPluginPrintfIncompatibleArgumentType',
                        'Format string {STRING_LITERAL} refers to argument #{INDEX} as {DETAILS}, so type {TYPE} is expected, but {FUNCTION} was passed incompatible type {TYPE}',
                        [
                            self::encodeString($fmt_str),
                            $i,
                            self::getSpecStringsRepresentation($spec_group),
                            $expected_union_type_string,
                            $function->getName(),
                            (string)$actual_union_type,
                        ],
                        Issue::SEVERITY_LOW,
                        self::ERR_UNTRANSLATED_INCOMPATIBLE_ARGUMENT
                    );
                }
            }
        }

        // Make sure the translations are compatible with this format string.
        // In order to take advantage of the ability to analyze translations, override gettextForAllLocales
        if ($is_translated) {
            $this->validateTranslations($code_base, $context, $fmt_str, $types_of_arg);
        }
    }

    /**
     * @param ConversionSpec[] $specs
     */
    private static function getSpecStringsRepresentation(array $specs): string
    {
        return \implode(',', \array_unique(\array_map(static function (ConversionSpec $spec): string {
            return $spec->directive;
        }, $specs)));
    }

    /**
     * @param array<string,true> $expected_set the types being checked for the ability to weakly cast to
     */
    private static function canWeakCast(UnionType $actual_union_type, array $expected_set): bool
    {
        if (isset($expected_set['string'])) {
            static $string_weak_types;
            if ($string_weak_types === null) {
                $string_weak_types = UnionType::fromFullyQualifiedPHPDocString('int|string|float');
            }
            return $actual_union_type->canCastToUnionType($string_weak_types);
        }
        // We already allow int->float conversion
        return false;
    }

    /**
     * TODO: Finish testing this.
     *
     * By default, this is a no-op, unless gettextForAllLocales is overridden in a subclass
     *
     * Check that the translations of the format string $fmt_str
     * are compatible with the untranslated format string.
     *
     * In virtually all cases, the conversions specifiers should be
     * identical to the conversion specifier (apart from whether or not
     * position is explicitly stated)
     *
     * Emits issues.
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param string $fmt_str
     * @param ConversionSpec[][] $types_of_arg contains array of ConversionSpec for
     *                                         each position in the untranslated format string.
     */
    protected static function validateTranslations(CodeBase $code_base, Context $context, string $fmt_str, array $types_of_arg): void
    {
        $translations = static::gettextForAllLocales($fmt_str);
        foreach ($translations as $locale => $translated_fmt_str) {
            // Skip untranslated or equal strings.
            if ($translated_fmt_str === $fmt_str) {
                continue;
            }
            // Compare the translated specs for a given position to the existing spec.
            $translated_specs = ConversionSpec::extractAll($translated_fmt_str);
            foreach ($translated_specs as $i => $spec_group) {
                $expected = $types_of_arg[$i] ?? [];
                foreach ($spec_group as $spec) {
                    $canonical = $spec->toCanonicalString();
                    if (!isset($expected[$canonical])) {
                        $expected_types = $expected ? implode(',', \array_keys($expected))
                                                    : 'unused';

                        if ($expected_types !== 'unused') {
                            $severity = Issue::SEVERITY_NORMAL;
                            $issue_type_id = self::ERR_TRANSLATED_INCOMPATIBLE;
                            $issue_type = 'PhanPluginPrintfTranslatedIncompatible';
                        } else {
                            $severity = Issue::SEVERITY_NORMAL;
                            $issue_type_id = self::ERR_TRANSLATED_HAS_MORE_ARGS;
                            $issue_type = 'PhanPluginPrintfTranslatedHasMoreArgs';
                        }
                        self::emitIssue(
                            $code_base,
                            $context,
                            $issue_type,
                            // phpcs:ignore Generic.Files.LineLength.MaxExceeded
                            'Translated string {STRING_LITERAL} has local {DETAILS} which refers to argument #{INDEX} as {STRING_LITERAL}, but the original format string treats it as {DETAILS} (ORIGINAL: {STRING_LITERAL}, TRANSLATION: {STRING_LITERAL})',
                            [
                                self::encodeString($fmt_str),
                                $locale,
                                $i,
                                $canonical,
                                $expected_types,
                                self::encodeString($fmt_str),
                                self::encodeString($translated_fmt_str),
                            ],
                            $severity,
                            Issue::REMEDIATION_B,
                            $issue_type_id
                        );
                    }
                }
            }
        }
    }
}

/**
 * Represents the information we have about the result of evaluating an expression.
 * Currently, used only for printf arguments.
 */
class PrimitiveValue
{
    /** @var array|int|string|float|bool|null The primitive value of the expression if it could be determined. */
    public $value;
    /** @var bool Whether or not the expression value was translated. */
    public $is_translated;

    /**
     * @param array|int|string|float|bool|null $value
     */
    public function __construct($value, bool $is_translated = false)
    {
        $this->value = $value;
        $this->is_translated = $is_translated;
    }
}

return new PrintfCheckerPlugin();
