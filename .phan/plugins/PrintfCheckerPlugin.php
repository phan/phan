<?php declare(strict_types=1);

namespace Phan\Plugin\PrintfCheckerPlugin;  // Don't pollute the global namespace

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeFunctionCallCapability;

use ast\Node;
use ast;

use function implode;
use function var_export;

/**
 * This plugin checks for invalid format strings and invalid uses of format strings in printf and sprintf, etc.
 * e.g. for printf("literal format %s", $arg)
 *
 * This uses ConversionSpec as a best effort at determining the the positions used by PHP format strings.
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
class PrintfCheckerPlugin extends PluginV2 implements AnalyzeFunctionCallCapability
{

    // Pylint error codes for emitted issues.
    const ERR_UNTRANSLATED_USE_ECHO                = 1300;
    const ERR_UNTRANSLATED_NONE_USED               = 1301;
    const ERR_UNTRANSLATED_NONEXISTENT             = 1302;
    const ERR_UNTRANSLATED_UNUSED                  = 1303;
    const ERR_UNTRANSLATED_NOT_PERCENT             = 1304;
    const ERR_UNTRANSLATED_INCOMPATIBLE_SPECIFIER  = 1305;
    const ERR_UNTRANSLATED_INCOMPATIBLE_ARGUMENT   = 1306;  // E.g. passing a string where an int is expected
    const ERR_UNTRANSLATED_INCOMPATIBLE_ARGUMENT_WEAK = 1307;  // E.g. passing an int where a string is expected
    const ERR_UNTRANSLATED_WIDTH_INSTEAD_OF_POSITION = 1308; // e.g. _('%1s'). Change to _('%1$1s' if you really mean that the width is 1, add positions for others ('%2$s', etc.)
    const ERR_TRANSLATED_INCOMPATIBLE              = 1309;
    const ERR_TRANSLATED_HAS_MORE_ARGS             = 1311;

    /**
     * People who have translations may subclass this plugin and return a mapping from other locales to those locales translations of $fmt_str.
     * @return string[] mapping locale to the translation (e.g. ['fr_FR' => 'Bonjour'] for $fmt_str == 'Hello')
     * @suppress PhanPluginUnusedVariable
     */
    protected static function gettextForAllLocales(string $fmt_str)
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
     * @param int|string|float|Node|array $astNode
     * @return ?PrimitiveValue
     */
    protected function astNodeToPrimitive(CodeBase $code_base, Context $context, $astNode)
    {
        // Base case: convert primitive tokens such as numbers and strings.
        if (!($astNode instanceof Node)) {
            return new PrimitiveValue($astNode);
        }
        switch ($astNode->kind) {
            case \ast\AST_CONST:
                $nameNode = $astNode->children['name'];
                if ($nameNode->kind === \ast\AST_NAME) {
                    $name = $nameNode->children['name'];
                    if (\strcasecmp($name, '__DIR__') === 0) {
                        // Relative to the directory of that file... Hopefully doesn't contain a format specifier
                        return new PrimitiveValue('(__DIR__ literal)');
                    } elseif (\strcasecmp($name, '__FILE__') === 0) {
                        // Relative to the directory of that file... Hopefully doesn't contain a format specifier
                        return new PrimitiveValue('(__FILE__ literal)');
                    } elseif (\defined($name)) {
                        // TODO: This can be an array, which is almost definitely wrong in printf contexts
                        // FIXME use GlobalConstant to retrieve the literal's value
                        $value = \constant($name);
                        if (!\is_scalar($value)) {
                            return null;
                        }
                        return new PrimitiveValue($value);
                    }
                }
                return null;
        // TODO: Resolve class constant access when those are format strings. Same for PregRegexCheckerPlugin.
            case \ast\AST_CALL:
                $nameNode = $astNode->children['expr'];
                if ($nameNode->kind === \ast\AST_NAME) {
                    // TODO: Use Phan's function resolution?
                    // TODO: ngettext?
                    $name = $nameNode->children['name'];
                    if ($name === '_' || strcasecmp($name, 'gettext') === 0) {
                        $childArg = $astNode->children['args']->children[0] ?? null;
                        if ($childArg === null) {
                            return null;
                        }
                        $prim = self::astNodeToPrimitive($code_base, $context, $childArg);
                        if ($prim === null) {
                            return null;
                        }
                        return new PrimitiveValue($prim->value, true);
                    }
                }
                return null;
            case \ast\AST_BINARY_OP:
                if ($astNode->flags !== ast\flags\BINARY_CONCAT) {
                    return null;
                }
                $left = $this->astNodeToPrimitive($code_base, $context, $astNode->children['left']);
                if ($left === null) {
                    return null;
                }
                $right = $this->astNodeToPrimitive($code_base, $context, $astNode->children['right']);
                if ($right === null) {
                    return null;
                }
                return $this->concatenateToPrimitive($left, $right);
        }
        // We don't know how to convert this to a primitive, give up.
        // (Subclasses may add their own logic first, then call self::astNodeToPrimitive)
        return null;
    }

    /**
     * Convert a primitive and a sequence of tokens to a primitive formed by
     * concatenating strings.
     *
     * @param PrimitiveValue $left the value on the left.
     * @param PrimitiveValue $right the value on the right.
     * @return ?PrimitiveValue
     */
    protected function concatenateToPrimitive(PrimitiveValue $left, PrimitiveValue $right)
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

    /**
     * @return \Closure[]
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        /**
         * Analyzes a printf-like function with a format directive in the first position.
         * @return void
         */
        $printf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
            if (\count($args) < 1) {
                return;
            }
            // TODO: Resolve global constants and class constants?
            // TODO: Check for AST_UNPACK
            $pattern = $args[0];
            if ($pattern instanceof Node) {
                $pattern = (new ContextNode($code_base, $context, $pattern))->getEquivalentPHPScalarValue();
            }
            $remaining_args = \array_slice($args, 1);
            $this->analyzePrintfPattern($code_base, $context, $function, $pattern, $remaining_args);
        };
        /**
         * Analyzes a printf-like function with a format directive in the first position.
         * @return void
         */
        $fprintf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
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
         * @return void
         */
        $vprintf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
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
         * @return void
         */
        $vfprintf_callback = function (
            CodeBase $code_base,
            Context $context,
            Func $function,
            array $args
        ) {
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

    protected function encodeString(string $str) : string
    {
        $result = \json_encode($str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
     * @param Node|string|int $pattern_node
     * @param null|Node[]|string[]|int[] $arg_nodes arguments following the format string. Null if the arguments could not be determined.
     * @return void
     */
    protected function analyzePrintfPattern(CodeBase $code_base, Context $context, FunctionInterface $function, $pattern_node, $arg_nodes)
    {
        // Given a node, extract the printf directive and whether or not it could be translated
        $primitive_for_fmtstr = $this->astNodeToPrimitive($code_base, $context, $pattern_node);
        if ($primitive_for_fmtstr === null) {
            // TODO: Add a verbose option
            return;
        }
        // Make sure that the untranslated format string is being used correctly.
        // If the format string will be translated, also check the translations.
        //
        // Outputs any errors found to log and stdout.
        // Check that the untranslated format string is being used correctly.

        $fmt_str = $primitive_for_fmtstr->value;
        $is_translated = $primitive_for_fmtstr->is_translated;
        $specs = ConversionSpec::extract_all($fmt_str);
        $fmt_str = (string)$fmt_str;
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
         * @param string[] $issue_message_args
         * The arguments for this issue format.
         * If this array is empty, $issue_message_args is kept in place
         *
         * @param int $severity
         * A value from the set {Issue::SEVERITY_LOW,
         * Issue::SEVERITY_NORMAL, Issue::SEVERITY_HIGH}.
         *
         * @param int $issue_type_id An issue id for pylint
         */
        $emit_issue = function (string $issue_type, string $issue_message_format, array $issue_message_args, int $severity, int $issue_type_id) use ($code_base, $context) {
            $this->emitIssue(
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

        // Check for extra or missing arguments
        if (\is_array($arg_nodes) && \count($arg_nodes) === 0) {
            if (count($specs) > 0) {
                $largest_positional = \max(\array_keys($specs));
                $examples = [];
                foreach ($specs[$largest_positional] as $example_spec) {
                    $examples[] = $this->encodeString($example_spec->directive);
                }
                // emit issues with 1-based offsets
                $emit_issue(
                    'PhanPluginPrintfNonexistentArgument',
                    'Format string {STRING_LITERAL} refers to nonexistent argument #{INDEX} in {STRING_LITERAL}',
                    [$this->encodeString($fmt_str), $largest_positional, \implode(',', $examples)],
                    Issue::SEVERITY_NORMAL,
                    self::ERR_UNTRANSLATED_NONEXISTENT
                );
            }
            $replacement_function_name = \in_array($function->getName(), ['vprintf', 'fprintf', 'vfprintf']) ? 'fwrite' : 'echo';
            $emit_issue(
                "PhanPluginPrintfNoArguments",
                "No format string arguments are given for {STRING_LITERAL}, consider using {FUNCTION} instead",
                [$this->encodeString($fmt_str), $replacement_function_name],
                Issue::SEVERITY_LOW,
                self::ERR_UNTRANSLATED_USE_ECHO
            );
            return;
        }
        if (count($specs) == 0) {
            $emit_issue(
                'PhanPluginPrintfNoSpecifiers',
                'None of the formatting arguments passed alongside format string {STRING_LITERAL} are used',
                [$this->encodeString($fmt_str)],
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
                    $examples[] = $this->encodeString($example_spec->directive);
                }
                // emit issues with 1-based offsets
                $emit_issue(
                    'PhanPluginPrintfNonexistentArgument',
                    'Format string {STRING_LITERAL} refers to nonexistent argument #{INDEX} in {STRING_LITERAL}',
                    [$this->encodeString($fmt_str), $largest_positional, \implode(',', $examples)],
                    Issue::SEVERITY_NORMAL,
                    self::ERR_UNTRANSLATED_NONEXISTENT
                );
            } elseif ($largest_positional < count($arg_nodes)) {
                $emit_issue(
                    'PhanPluginPrintfUnusedArgument',
                    'Format string {STRING_LITERAL} does not use provided argument #{INDEX}',
                    [$this->encodeString($fmt_str), $largest_positional + 1],
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
                if ($spec->padding_char === ' ' && ($spec->width === '' || empty($spec->position))) {
                    // Warn about "100% dollars" but not about "100%1$ 2dollars" (If both position and width were parsed, assume the padding was intentional)
                    $emit_issue(
                        'PhanPluginPrintfNotPercent',
                        "Format string {STRING_LITERAL} contains something that is not a percent sign, it will be treated as a format string '{STRING_LITERAL}' with padding. Use {DETAILS} for a literal percent sign, or '{STRING_LITERAL}' to be less ambiguous",
                        [$this->encodeString($fmt_str), $spec->directive, '%%', $canonical],
                        Issue::SEVERITY_NORMAL,
                        self::ERR_UNTRANSLATED_NOT_PERCENT
                    );
                }
                if ($is_translated && !empty($spec->width) &&
                        ($spec->padding_char === '' || $spec->padding_char === ' ')) {
                    $intended_string = $spec->toCanonicalStringWithWidthAsPosition();
                    $emit_issue(
                        'PhanPluginPrintfWidthNotPosition',
                        "Format string {STRING_LITERAL} is specifying a width({STRING_LITERAL}) instead of a position({STRING_LITERAL})",
                        [$this->encodeString($fmt_str), $this->encodeString($canonical), $this->encodeString($intended_string)],
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
                    [$this->encodeString($fmt_str), $i, implode(',', array_keys($types))],
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
                $expected_union_type = new UnionType();
                foreach ($expected_set as $type_name => $_) {
                    $expected_union_type->addType(Type::fromFullyQualifiedString($type_name));
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
                    } catch (CodeBaseException $e) {
                        // Swallow "Cannot find class", go on to emit issue.
                    }
                    if ($can_cast_to_string) {
                        continue;
                    }
                }

                $expected_union_type_string = (string)$expected_union_type;
                if ($this->canWeakCast($actual_union_type, $expected_set)) {
                    // This can be resolved by casting the arg to (string) manually in printf.
                    $emit_issue(
                        'PhanPluginPrintfIncompatibleArgumentTypeWeak',
                        'Format string {STRING_LITERAL} refers to argument #{INDEX} as {DETAILS}, so type {TYPE} is expected. However, {FUNCTION} was passed the type {TYPE} (which is weaker than {TYPE})',
                        [
                            $this->encodeString($fmt_str),
                            $i,
                            $this->getSpecStringsRepresentation($spec_group),
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
                            $this->encodeString($fmt_str),
                            $i,
                            $this->getSpecStringsRepresentation($spec_group),
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
    private function getSpecStringsRepresentation(array $specs) : string
    {
        return \implode(',', \array_unique(\array_map(function (ConversionSpec $spec) {
            return $spec->directive;
        }, $specs)));
    }

    private function canWeakCast(UnionType $actual_union_type, array $expected_set)
    {
        if (isset($expected_set['string'])) {
            static $string_weak_types;
            if ($string_weak_types === null) {
                $string_weak_types = UnionType::fromFullyQualifiedString('int|string|float');
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
     * @return void
     */
    protected function validateTranslations(CodeBase $code_base, Context $context, string $fmt_str, array $types_of_arg)
    {
        $translations = static::gettextForAllLocales($fmt_str);
        foreach ($translations as $locale => $translated_fmt_str) {
            // Skip untranslated or equal strings.
            if ($translated_fmt_str === $fmt_str) {
                continue;
            }
            // Compare the translated specs for a given position to the existing spec.
            $translated_specs = ConversionSpec::extract_all($translated_fmt_str);
            foreach ($translated_specs as $i => $spec_group) {
                $expected = $types_of_arg[$i] ?? [];
                foreach ($spec_group as $spec) {
                    $canonical = $spec->toCanonicalString();
                    if (!isset($expected[$canonical])) {
                        $expected_types = empty($expected) ? 'unused'
                                                           : implode(',', array_keys($expected));

                        if ($expected_types !== 'unused') {
                            $severity = Issue::SEVERITY_NORMAL;
                            $issue_type_id = self::ERR_TRANSLATED_INCOMPATIBLE;
                            $issue_type = 'PhanPluginPrintfTranslatedIncompatible';
                        } else {
                            $severity = Issue::SEVERITY_NORMAL;
                            $issue_type_id = self::ERR_TRANSLATED_HAS_MORE_ARGS;
                            $issue_type = 'PhanPluginPrintfTranslatedHasMoreArgs';
                        }
                        $this->emitIssue(
                            $code_base,
                            $context,
                            $issue_type,
                            'Translated string {STRING_LITERAL} has local {DETAILS} which refers to argument #{INDEX} as {STRING_LITERAL}, but the original format string treats it as {DETAILS} (ORIGINAL: {STRING_LITERAL}, TRANSLATION: {STRING_LITERAL})',
                            [
                                $this->encodeString($fmt_str),
                                $locale,
                                $i,
                                $canonical,
                                $expected_types,
                                $this->encodeString($fmt_str),
                                $this->encodeString($translated_fmt_str),
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
 * An object representing a conversion specifier of a format string, such as "%1$d".
 */
class ConversionSpec
{
    /** @var string Original text of the directive */
    public $directive;
    /** @var ?int Which argument this refers to, starting from 1 */
    public $position;
    /** @var string Character used for padding (commonly confused with $position) */
    public $padding_char;
    /** @var string indicates which side is used for alignment */
    public $alignment;
    /** @var string minimum width of output */
    public $width;         // Minimum width of output.
    /** @var string Type to print (s,d,f,etc.) */
    public $arg_type;

    /**
     * Create a conversion specifier from a match.
     * @param array $match groups in a match.
     */
    protected function __construct(array $match)
    {
        list($this->directive, $position_str, $this->padding_char, $this->alignment, $this->width, $unused_precision, $this->arg_type) = $match;
        if ($position_str !== "") {
            $this->position = \intval(\substr($position_str, 0, -1));
        }
    }

    // A padding string regex may be a space or 0.
    // Alternate padding specifiers may be specified by prefixing it with a single quote.
    const padding_string_regex_part ='[0 ]?|\'.';

    /**
     * Based on https://secure.php.net/manual/en/function.sprintf.php
     */
    const format_string_inner_regex_part =
        '%'  // Every format string begins with a percent
        . '(\d+\$)?'  // Optional n$ position specifier must go immediately after percent
        . '(' . self::padding_string_regex_part . ')'  // optional padding specifier
        . '([+-]?)' // optional alignment specifier
        . '(\d*)'  // optional width specifier
        . '(\.\d*)?'   // Optional precision specifier in the form of a period followed by an optional decimal digit string
        . '([bcdeEfFgGosuxX])';  // A type specifier


    const format_string_regex = '/%%|' . self::format_string_inner_regex_part . '/';

    /**
     * Extract a list of directives from a format string.
     * @param string $fmt_str a format string to extract directives from.
     * @return ConversionSpec[][] array(int position => array of ConversionSpec referring to arg at that position)
     */
    public static function extract_all($fmt_str) : array
    {
        // echo "format is $fmt_str\n";
        $directives = [];
        \preg_match_all(self::format_string_regex, (string) $fmt_str, $matches, PREG_SET_ORDER);
        $unnamed_count = 0;
        foreach ($matches as $match) {
            if ($match[0] === '%%') {
                continue;
            }
            $directive = new self($match);
            if (!isset($directive->position)) {
                $directive->position = ++$unnamed_count;
            }
            $directives[$directive->position][] = $directive;
        }
        \ksort($directives);
        return $directives;
    }

    /**
     * @return string an unambiguous way of referring to this conversion spec.
     */
    public function toCanonicalString() : string
    {
        return '%' . $this->position . '$' . $this->padding_char . $this->alignment . $this->width . $this->arg_type;
    }

    /**
     * @return string the conversion spec if the width was used as a position instead.
     */
    public function toCanonicalStringWithWidthAsPosition() : string
    {
        return '%' . $this->width . '$' . $this->padding_char . $this->alignment . $this->arg_type;
    }
    const ARG_TYPE_LOOKUP = [
        'b' => 'int',
        'c' => 'int',
        'd' => 'int',
        'e' => 'float',
        'E' => 'float',
        'f' => 'float',
        'F' => 'float',
        'g' => 'float',
        'G' => 'float',
        'o' => 'int',
        's' => 'string',
        'u' => 'int',
        'x' => 'int',
        'X' => 'int',
    ];

    /**
     * @return string the name of the union type expected for the arg for this conversion spec
     */
    public function getExpectedUnionTypeName() : string
    {
        return self::ARG_TYPE_LOOKUP[$this->arg_type] ?? 'string';
    }
}

/**
 * Represents the information we have about the result of evaluating an expression.
 * Currently, used only for printf arguments.
 */
class PrimitiveValue
{
    /** @var int|string|float|null The primitive value of the expression if it could be determined. */
    public $value;
    /** @var bool Whether or not the expression value was translated. */
    public $is_translated;

    /**
     * @param array|int|string|float|null $value
     */
    public function __construct($value, bool $is_translated = false)
    {
        $this->value = $value;
        $this->is_translated = $is_translated;
    }
}

return new PrintfCheckerPlugin();
