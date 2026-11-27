<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Closure;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\FQSENException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Flags;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Scope\TemplateScope;
use Phan\Language\Type;
use Phan\Language\Type\GenericMultiType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Library\FileCacheEntry;
use Phan\Library\None;
use Phan\Library\Option;
use Phan\Library\Some;
use Phan\Plugin\ConfigPluginSet;
use Phan\Suggestion;

use function count;

/**
 * This constructs comments from doc comments (or other comment types).
 *
 * @internal - For use only by Comment.php
 */
final class Builder
{
    /** @var string the original raw comment */
    public $comment;
    /** @var list<string> the list of lines of the doc comment */
    public $lines;
    /** @var int count($this->lines) */
    public $comment_lines_count;
    /** @var CodeBase The code base within which we're operating. */
    public $code_base;
    /** @var Context the context of the parser at the comment we're reading */
    public $context;
    /** @var int the line number of the element this doc comment belongs to */
    public $lineno;
    /** @var int an enum value from Comment::ON_* */
    public $comment_type;
    /** @var list<Parameter> the list of extracted (at)var annotations*/
    public $variable_list = [];
    /** @var list<Parameter> the list of extracted (at)param annotations */
    public $parameter_list = [];
    /** @var array<string,TemplateType> the list of extracted (at)template annotations */
    public $template_type_list = [];
    /** @var Option<Type> the (at)inherits annotation */
    public $inherited_type;
    /** @var list<Type> the list of (at)implements annotations with template parameters */
    public $implemented_types = [];

    /** @var array<string,string> */
    private const TAG_ALIAS_MAP = [
        'psalm-template' => 'template',
        'psalm-template-covariant' => 'template-covariant',
        'psalm-template-contravariant' => 'template-contravariant',
        'phpstan-template' => 'template',
        'phpstan-template-covariant' => 'template-covariant',
        'phpstan-template-contravariant' => 'template-contravariant',
        'psalm-require-extends' => 'require-extends',
        'psalm-require-implements' => 'require-implements',
        'psalm-immutable' => 'immutable',
        'phpstan-assert-if-true' => 'phan-assert-if-true',
        'phpstan-assert-if-false' => 'phan-assert-if-false',
        'psalm-assert-if-true' => 'phan-assert-if-true',
        'psalm-assert-if-false' => 'phan-assert-if-false',
        'phpstan-type' => 'phan-type',
        'psalm-type' => 'phan-type',
        'phpstan-import-type' => 'phan-import-type',
        'psalm-import-type' => 'phan-import-type',
    ];
    /** @var list<Type> the list of (at)use annotations with template parameters for traits */
    public $used_trait_types = [];
    // TODO: Warn about multiple (at)returns
    /** @var ?ReturnComment the (at)return annotation details */
    public $return_comment;
    /**
     * @var array<string,int> the set of issue names from (at)suppress annotations
     */
    public $suppress_issue_set = [];
    /** @var list<Property> the list of (at)property annotations (and property-read, property-write) */
    public $magic_property_list = [];
    /** @var list<Method> the list of (at)method annotations */
    public $magic_method_list = [];
    /** @var Option<Type> the type a closure will be bound to */
    public $closure_scope;
    /** @var int combination of flags from \Phan\Flags */
    public $comment_flags = 0;
    /** @var array<string,mixed> annotations for Phan that override the standardized version of those annotations. Used for compatibility with other tools. */
    public $phan_overrides = [];

    /**
     * @var list<Parameter> the parameters of (at)phpstan-param and (at)psalm-param annotations that Phan could parse.
     * These replace the types of (at)param, and are replaced by (at)phan-param.
     */
    private $phpstan_param_list = [];

    /** @var list<Parameter> the variables of (at)phpstan-var and (at)psalm-var annotations that Phan could parse. */
    private $phpstan_var_list = [];

    /** @var ?ReturnComment the type of an (at)phpstan-return or (at)psalm-return annotation that is not conditional and that Phan could parse. */
    private $phpstan_return_comment = null;

    /** @var list<array{0:int,1:string}> the line numbers and lines of the conditional (at)phpstan-return annotations */
    private $phpstan_conditional_return_lines = [];

    /** @var bool whether an (at)phpstan-param, (at)phpstan-var or (at)phpstan-return annotation was ignored because Phan could not parse all of it */
    private $has_ignored_phpstan_annotation = false;

    /** @var array<string,true> the names of the template types that were only declared with (at)phpstan-template */
    private $phpstan_template_names = [];
    /** @var UnionType the union type of the set of (at)throws annotations */
    public $throw_union_type;
    /** @var array<string,Assertion> assertions about each parameter */
    public $param_assertion_map = [];

    /** @var list<ConditionalAssertion> the (at)phan-assert-if-true/(at)phan-assert-if-false annotations (also phpstan-, psalm-) */
    public $conditional_assertion_list = [];

    /** @var list<string> the names of the type aliases that this comment declares ((at)phan-type, (at)phpstan-type, (at)psalm-type) */
    private $declared_type_alias_names = [];

    /** @var list<Type> */
    private $required_extends_types = [];

    /** @var list<Type> */
    private $required_implements_types = [];

    /** @var list<CustomAnnotation> the annotations that plugins declared (CustomAnnotationCapability) */
    private $custom_annotations = [];

    /** @var ?int the line of the last line of the comment in the file (see guessCustomAnnotationLineLocation()) */
    private $last_line_location = null;

    /** @var bool did we add template types already */
    protected $did_add_template_types;

    /**
     * A list of issues detected in the comment being built.
     * This is stored instead of immediately emitting the issue because later lines might suppress these issues.
     *
     * @var list<array{0:string,1:int,2:list<mixed>,3:?Suggestion}>
     */
    private $issues = [];

    public function __construct(
        string $comment,
        CodeBase $code_base,
        Context $context,
        int $lineno,
        int $comment_type,
        bool $did_add_template_types = false
    ) {
        $this->comment = $comment;
        $this->lines = \explode("\n", self::reduceMultiline($comment));
        $this->comment_lines_count = \count($this->lines);
        $this->code_base = $code_base;
        $this->context = $context;
        $this->lineno = $lineno;
        $this->comment_type = $comment_type;
        $this->did_add_template_types = $did_add_template_types;

        $this->inherited_type = None::instance();
        $this->return_comment = null;
        $this->closure_scope = None::instance();
        $this->throw_union_type = UnionType::empty();
    }

    /** @internal */
    public const PARAM_COMMENT_REGEX =
        '/@(?:phan-|psalm-|(?i:phpstan-))?(param|var)\b\s*(' . UnionType::union_type_regex . ')?(?:\s*(\.\.\.)?\s*&?(?:\\$' . self::WORD_REGEX . '))?/';

    /** @internal */
    public const UNUSED_PARAM_COMMENT_REGEX =
        '/@(?:phan-)?unused-param\b\s*(' . UnionType::union_type_regex . ')?(?:\s*(\.\.\.)?\s*&?(?:\\$' . self::WORD_REGEX . '))/';

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @param bool $is_var
     * True if this is parsing a variable, false if parsing a parameter.
     *
     * @return Parameter
     * A Parameter associated with a line that has a var
     * or param reference.
     *
     * TODO: account for difference between (at)var and (at)param
     */
    private function parameterFromCommentLine(
        string $line,
        bool $is_var,
        int $i
    ): Parameter {
        $matched = \preg_match(self::PARAM_COMMENT_REGEX, $line, $match);
        // Parse https://docs.phpdoc.org/references/phpdoc/tags/param.html
        // Exceptions: Deliberately allow "&" in "@param int &$x" when documenting references.
        // Warn if there is neither a union type nor a variable
        if ($matched && (isset($match[2]) || isset($match[17]))) {
            if (!isset($match[2])) {
                return new Parameter('', UnionType::empty(), $this->guessActualLineLocation($i));
            }
            if (!$is_var && !isset($match[17])) {
                $this->checkParamWithoutVarName($line, $match[0], $match[2], $i);
            }
            $original_type = $match[2];

            $is_variadic = ($match[16] ?? '') === '...';

            if ($is_var && $is_variadic) {
                $variable_name = '';  // "@var int ...$x" is nonsense and invalid phpdoc.
            } else {
                $variable_name = $match[17] ?? '';
                if ($is_var && $variable_name === '' && $this->comment_type === Comment::ON_PROPERTY) {
                    $end_offset = (int)\strpos($line, $match[0]) + \strlen($match[0]);
                    $char_at_end_offset = $line[$end_offset] ?? ' ';
                    if (\ord($char_at_end_offset) > 32 && !\preg_match('@^\*+/$@D', \substr($line, $end_offset))) {  // Not a control character or space
                        $this->emitIssue(
                            Issue::UnextractableAnnotationSuffix,
                            $this->guessActualLineLocation($i),
                            \trim($line),
                            $original_type,
                            $char_at_end_offset
                        );
                    }
                }
            }
            // Fix typos or non-standard phpdoc tags, according to the user's configuration.
            // Does nothing by default.
            $type = self::rewritePHPDocType($original_type);

            // If the type looks like a variable name, make it an
            // empty type so that other stuff can match it. We can't
            // just skip it or we'd mess up the parameter order.
            if (!str_starts_with($type, '$')) {
                $union_type =
                    UnionType::fromStringInContext(
                        $type,
                        $this->context,
                        Type::FROM_PHPDOC,
                        $this->code_base
                    );
            } else {
                $union_type = UnionType::empty();
            }
            $is_output_parameter = str_contains($line, '@phan-output-reference');
            $is_ignored_parameter = str_contains($line, '@phan-ignore-reference');
            $is_mandatory_in_phpdoc = str_contains($line, '@phan-mandatory-param');
            if ($is_mandatory_in_phpdoc) {
                // @phan-suppress-next-line PhanAccessMethodInternal
                $this->code_base->recordSawMandatoryParamAnnotation();
            }

            return new Parameter(
                $variable_name,
                $union_type,
                $this->guessActualLineLocation($i),
                $is_variadic,
                false,  // has_default_value
                $is_output_parameter,
                $is_ignored_parameter,
                $is_mandatory_in_phpdoc
            );
        }

        // Don't warn about @param $x Description of $x goes here
        // TODO: extract doc comment of @param &$x?
        // TODO: Use the right for the name of the comment parameter?
        //       (don't see a benefit, would create a type if it was (at)var on a function-like)
        if (!\preg_match('/@(param|var)\s+(\.\.\.)?\s*(\\$\S+)/', $line)) {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                \trim($line)
            );
        }

        return new Parameter('', UnionType::empty());
    }

    /** @internal */
    public const RETURN_COMMENT_REGEX = '/@(?:phan-|psalm-|phpstan-)?(?:real-)?return\s+(&\s*)?(' . UnionType::union_type_regex_or_this . ')/';
    /** @internal */
    public const RETURN_OR_THROWS_COMMENT_REGEX = '/@(?:phan-|psalm-|phpstan-)?(?:real-)?(?:return|throws)\s+(&\s*)?(' . UnionType::union_type_regex_or_this . ')/';

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return UnionType
     * The declared return or throws type
     */
    private function returnOrThrowsTypeFromCommentLine(
        string $line,
        int $i
    ): UnionType {
        $union_type_string = '';

        if (\preg_match(self::RETURN_OR_THROWS_COMMENT_REGEX, $line, $match)) {
            $union_type_string = $match[2];
            $raw_match = $match[0];
            $end_offset = (int)\strpos($line, $raw_match) + \strlen($raw_match);
            $char_at_end_offset = $line[$end_offset] ?? ' ';
            if (\ord($char_at_end_offset) > 32 && !\preg_match('@^\*+/$@D', \substr($line, $end_offset))) {  // Not a control character or space
                $this->emitIssue(
                    Issue::UnextractableAnnotationSuffix,
                    $this->guessActualLineLocation($i),
                    \trim($line),
                    $union_type_string,
                    $char_at_end_offset
                );
            }
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                \trim($line)
            );
        }
        // Not emitting any issues about failing to extract, e.g. `@return - Description of what this returns` is a valid comment.
        $union_type_string = self::rewritePHPDocType($union_type_string);

        return UnionType::fromStringInContext(
            $union_type_string,
            $this->context,
            Type::FROM_PHPDOC,
            $this->code_base
        );
    }

    /**
     * Matches the start of a conditional return type annotation, e.g. `(at)return ($x is null ? A : B)`.
     * Anchored to the tag at the start of the comment line, and deliberately cheap:
     * ordinary (at)return annotations fail at the lookahead.
     * @internal
     */
    public const CONDITIONAL_RETURN_START_REGEX = '/^[\s\/*]*@(?i:phan-|psalm-|phpstan-)?(?i:return)\s+(?=\(\s*\$)/';

    private const CONDITIONAL_HEAD_REGEX = '/\G\(\s*\$' . self::WORD_REGEX . '\s+is\s+(not\s+)?(' . UnionType::union_type_regex_or_this . ')\s*\?\s*/';
    private const CONDITIONAL_LEAF_REGEX = '/\G(' . UnionType::union_type_regex_or_this . ')/';
    private const CONDITIONAL_NESTED_LOOKAHEAD_REGEX = '/\G\(\s*\$/';
    private const CONDITIONAL_SEPARATOR_REGEX = '/\G\s*:\s*/';
    private const CONDITIONAL_END_REGEX = '/\G\s*\)/';

    /**
     * Parses a PHPStan/Psalm-style conditional return type such as `(at)return ($x is null ? A : B)`.
     *
     * Only `$param is [not] Type` conditions are supported (not conditions on template type names),
     * and either branch may be another conditional.
     *
     * @return ?ConditionalReturnType null if $line is not a conditional return type, or could not be parsed as one.
     * Callers should then fall back to the regular (at)return parsing, which emits the usual issues.
     */
    private function parseConditionalReturnFromCommentLine(string $line, int $i): ?ConditionalReturnType
    {
        if (!\preg_match(self::CONDITIONAL_RETURN_START_REGEX, $line, $match, \PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $offset = $match[0][1] + \strlen($match[0][0]);
        $conditional = $this->parseConditionalAt($line, $offset, $this->guessActualLineLocation($i));
        if (!$conditional) {
            return null;
        }
        $char_at_end_offset = $line[$offset] ?? ' ';
        if (\ord($char_at_end_offset) > 32 && !\preg_match('@^\*+/$@D', \substr($line, $offset))) {  // Not a control character or space
            $this->emitIssue(
                Issue::UnextractableAnnotationSuffix,
                $this->guessActualLineLocation($i),
                \trim($line),
                (string)$conditional,
                $char_at_end_offset
            );
        }
        return $conditional;
    }

    /**
     * The end of an array shape that can have other keys: `array{a: int, ...}`, `array{a: int, ...<string, mixed>}`
     */
    private const OPEN_SHAPE_REGEX = '/,\s*\.\.\.(?:\s*<[^<>{}]*>)?\s*\}/';

    /**
     * @return bool whether a name in $type_string is a type alias of this file.
     * (What the alias stands for may be a type that Phan reads as a wider one: `@phpstan-type Num numeric-string`)
     */
    private function usesTypeAlias(string $type_string): bool
    {
        // (Not a name in a literal string: `'Num'`)
        if (\preg_match_all('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', self::withoutQuotedLiterals($type_string), $matches) > 0) {
            foreach ($matches[0] as $name) {
                if ($this->context->getTypeAlias($name) !== null) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Matches the types in a type string that Phan reads as a wider type, or does not check completely as a condition:
     * `numeric-string`, `literal-string` and `lowercase-string` (string), `int<1, 10>` (int), `Foo::BAR` (mixed),
     * and `class-string<Foo>` (the class that a string names is not compared with `Foo`).
     * `numeric` is read as `int|float|string`, which every string is.
     * @internal
     */
    public const APPROXIMATED_TYPE_REGEX = '/::|\bint(?:-range)?\s*<|\b(?:non-empty-)?(?:numeric|literal|lowercase)-string\b|\b(?:class|interface|trait|enum)-string\b|(?<![-\\\\$\'"a-zA-Z0-9_])(?-i:numeric)(?![-a-zA-Z0-9_\\\\]|\s*\??:(?!:))/i';

    /**
     * Parses `($name is [not] Type ? Branch : Branch)` starting at $offset.
     * @param int $offset the offset of the opening parenthesis. On success, this is advanced past the closing parenthesis.
     * @param int $lineno the line of the annotation, recorded on the result
     */
    private function parseConditionalAt(string $line, int &$offset, int $lineno): ?ConditionalReturnType
    {
        if (!\preg_match(self::CONDITIONAL_HEAD_REGEX, $line, $match, 0, $offset)) {
            return null;
        }
        $param_name = $match[1];
        $negated = ($match[2] ?? '') !== '';
        if (str_contains($match[3], '...') && \preg_match(self::OPEN_SHAPE_REGEX, $match[3])) {
            // `$args is array{fields: 'ids', ...}`: an array shape that can have other keys is read as the array shape or any array,
            // which every array is. The condition is not one that Phan can check.
            return null;
        }
        $condition = $this->unionTypeFromConditionalPart($match[3]);
        // (`numeric-string` and `lowercase-string` are read as `string`, `int<1, max>` as `int`, `Foo::BAR` as `mixed`)
        $is_condition_approximated = \preg_match(self::APPROXIMATED_TYPE_REGEX, self::withoutQuotedLiterals($match[3])) > 0 || $this->usesTypeAlias($match[3]);
        $offset += \strlen($match[0]);

        $if_true = $this->parseConditionalBranchAt($line, $offset, $lineno);
        if ($if_true === null) {
            return null;
        }
        if (!\preg_match(self::CONDITIONAL_SEPARATOR_REGEX, $line, $match, 0, $offset)) {
            return null;
        }
        $offset += \strlen($match[0]);
        $if_false = $this->parseConditionalBranchAt($line, $offset, $lineno);
        if ($if_false === null) {
            return null;
        }
        if (!\preg_match(self::CONDITIONAL_END_REGEX, $line, $match, 0, $offset)) {
            return null;
        }
        $offset += \strlen($match[0]);
        return new ConditionalReturnType($param_name, $condition, $negated, $if_true, $if_false, $lineno, $is_condition_approximated);
    }

    /**
     * Parses one branch of a conditional: either a nested conditional or a union type.
     * @param int $offset advanced past the branch on success
     */
    private function parseConditionalBranchAt(string $line, int &$offset, int $lineno): UnionType|ConditionalReturnType|null
    {
        if (\preg_match(self::CONDITIONAL_NESTED_LOOKAHEAD_REGEX, $line, $unused_match, 0, $offset)) {
            return $this->parseConditionalAt($line, $offset, $lineno);
        }
        if (!\preg_match(self::CONDITIONAL_LEAF_REGEX, $line, $match, 0, $offset)) {
            return null;
        }
        $offset += \strlen($match[0]);
        return $this->unionTypeFromConditionalPart($match[1]);
    }

    private function unionTypeFromConditionalPart(string $type_string): UnionType
    {
        return UnionType::fromStringInContext(
            self::rewritePHPDocType($type_string),
            $this->context,
            Type::FROM_PHPDOC,
            $this->code_base
        );
    }

    private static function rewritePHPDocType(
        string $original_type
    ): string {
        // TODO: Would need to pass in CodeBase to emit an issue:
        $type = Config::getValue('phpdoc_type_mapping')[\strtolower($original_type)] ?? null;
        if (\is_string($type)) {
            return $type;
        }
        return $original_type;
    }


    /**
     * This should be uncommon: $line is a parameter for which a parameter name could not be parsed
     */
    private function checkParamWithoutVarName(
        string $line,
        string $raw_match,
        string $union_type_string,
        int $i
    ): void {

        $match_offset = \strpos($line, $raw_match);
        $end_offset = $match_offset + \strlen($raw_match);

        $char_at_end_offset = $line[$end_offset] ?? ' ';
        $issue_line = $this->guessActualLineLocation($i);
        if (\ord($char_at_end_offset) > 32) {  // Not a control character or space
            $this->emitIssue(
                Issue::UnextractableAnnotationSuffix,
                $issue_line,
                \trim($line),
                $union_type_string,
                $char_at_end_offset
            );
        }

        $this->emitIssue(
            Issue::UnextractableAnnotationElementName,
            $issue_line,
            \trim($line),
            $union_type_string
        );
    }

    /**
     * Extracts information from the doc comment instance,
     * parses it, and creates a Comment representing the extracted information.
     */
    public function build(): Comment
    {
        $comment = $this->buildInner();
        // (Also when there are none: in the daemon, a file that changed must not keep the aliases it had)
        if ($this->comment_type === Comment::ON_CLASS && $this->context->isInClassScope() && ($this->declared_type_alias_names || $this->code_base->hasTypeAliasesOfClass($this->context->getClassFQSEN()))) {
            // For `@phpstan-import-type Name from ThisClass` in other files
            $type_alias_map = [];
            foreach ($this->declared_type_alias_names as $alias_name) {
                $type = $this->context->getTypeAlias($alias_name);
                if ($type !== null) {
                    $type_alias_map[$alias_name] = $type;
                }
            }
            $this->code_base->setTypeAliasesOfClass($this->context->getClassFQSEN(), $type_alias_map);
        }
        return $comment;
    }

    private function buildInner(): Comment
    {
        foreach ($this->lines as $i => $line) {
            if (!str_contains($line, '@')) {
                continue;
            }
            // https://docs.phpdoc.org/2.9/guides/docblocks.html
            // > A tag always starts on a new line with an at-sign (@) followed by the name of the tag.
            // > Between the start of the line and the tag’s name (including at-sign) there may be one or more spaces or tabs.
            $line = \trim($line);
            $trimmed = \preg_replace('/^\/?[*\s]+/', '', $line);
            if (($trimmed[0] ?? '') !== '@') {
                continue;
            }
            $this->parseCommentLine($i, $line, $trimmed);
        }

        if (\count($this->template_type_list)) {
            if (!$this->did_add_template_types) {
                return $this->buildWithTemplateTypes();
            }
        }
        if ($this->issues) {
            $this->emitDeferredIssues();
        }

        $this->applyPHPStanAnnotations();
        if (!$this->comment_flags &&
            !$this->return_comment &&
            !$this->parameter_list &&
            !$this->variable_list &&
            !$this->template_type_list &&
            $this->inherited_type instanceof None &&
            !$this->implemented_types &&
            !$this->used_trait_types &&
            !$this->required_extends_types &&
            !$this->required_implements_types &&
            !$this->suppress_issue_set &&
            !$this->magic_property_list &&
            !$this->magic_method_list &&
            !$this->phan_overrides &&
            $this->closure_scope instanceof None &&
            $this->throw_union_type->isEmpty() &&
            !$this->param_assertion_map &&
            !$this->conditional_assertion_list &&
            !$this->custom_annotations
        ) {
            // Don't create an extra object if the string contained `@` but nothing of use was actually extracted.
            return NullComment::instance();
        }
        // Only the comments with annotations that plugins declared have a property for them.
        $comment_class = $this->custom_annotations ? CommentWithCustomAnnotations::class : Comment::class;
        $comment = new $comment_class(
            $this->comment_flags,
            $this->variable_list,
            $this->parameter_list,
            \array_values($this->template_type_list),
            $this->inherited_type,
            $this->implemented_types,
            $this->used_trait_types,
            $this->required_extends_types,
            $this->required_implements_types,
            $this->return_comment,
            $this->suppress_issue_set,
            $this->magic_property_list,
            $this->magic_method_list,
            $this->phan_overrides,
            $this->closure_scope,
            $this->throw_union_type,
            $this->param_assertion_map,
            // NOTE: The code base and context are used for emitting issues, and are not saved
            $this->code_base,
            $this->context
        );
        if ($comment instanceof CommentWithCustomAnnotations && $this->custom_annotations) {
            $comment->setCustomAnnotations($this->custom_annotations);
        }
        if ($this->conditional_assertion_list) {
            $comment->setConditionalAssertionList($this->conditional_assertion_list);
        }
        return $comment;
    }

    private function buildWithTemplateTypes(): Comment
    {
        $old_scope = $this->context->getScope();
        $new_scope = new TemplateScope($old_scope, $this->template_type_list);
        $new_context = $this->context->withScope($new_scope);
        // $result = Type::fromStringInContext('T', $new_context, Type::FROM_PHPDOC, $this->code_base);
        return (new self(
            $this->comment,
            $this->code_base,
            $new_context,
            $this->lineno,
            $this->comment_type,
            true
        ))->build();
    }

    private function parseCommentLine(int $i, string $line, string $trimmed): void
    {
        // https://secure.php.net/manual/en/regexp.reference.internal-options.php
        // (?i) makes this case-sensitive, (?-1) makes it case-insensitive
        // phpcs:ignore Generic.Files.LineLength.MaxExceeded
        // Support both regular tags ("@something") and inline versions of tags ("optional_prefix {@something}").
        if (\preg_match('/(?:^|{)@((?i)param|deprecated|var|return|throws|throw|returns|inherits|extends|implements|use|require-extends|require-implements|suppress|unused-param|no-named-arguments|phan-[a-z0-9_-]*|(?:phpstan|psalm)-assert-if-(?:true|false)|(?:phpstan|psalm)-(?:import-)?type|phpstan-(?:return|param|var|template(?:-(?:co|contra)variant)?)|psalm-(?:template(?:-(?:co|contra)variant)?|param|var|return|require-extends|require-implements|immutable)(?-i)|method|property|property-read|property-write|abstract|template(?:-(?:co|contra)variant)?|PhanClosureScope|readonly|immutable|mixin|seal-(?:methods|properties))(?:[^a-zA-Z0-9_\x7f-\xff-]|$)/D', $trimmed, $matches)) {
            $case_sensitive_type = $matches[1];
            $type = \strtolower($case_sensitive_type);
            $type = self::TAG_ALIAS_MAP[$type] ?? $type;

            switch ($type) {
                case 'param':
                    $this->parseParamLine($i, $line);
                    break;
                case 'unused-param':
                    $this->parseUnusedParamLine($i, $line);
                    break;
                case 'var':
                    $this->maybeParseVarLine($i, $line);
                    break;
                case 'template':
                case 'template-covariant': // Enforces covariant template variance semantics.
                case 'template-contravariant':
                    $this->maybeParseTemplateType($i, $line, $type);
                    break;
                case 'inherits':
                case 'extends':
                    $this->maybeParseInherits($i, $line, $type);
                    break;
                case 'implements':
                    $this->maybeParseImplements($i, $line);
                    break;
                case 'use':
                    $this->maybeParseUse($i, $line);
                    break;
                case 'require-extends':
                    $this->maybeParseRequireExtends($i, $line);
                    break;
                case 'require-implements':
                    $this->maybeParseRequireImplements($i, $line);
                    break;
                case 'return':
                    $this->maybeParseReturn($i, $line);
                    break;
                case 'phpstan-return':
                    $this->maybeParsePHPStanReturn($i, $line);
                    break;
                case 'psalm-return':
                    // A conditional type is read like one of `@return`, and reported if Phan cannot parse it.
                    if (\preg_match(self::CONDITIONAL_RETURN_START_REGEX, $line)) {
                        $this->maybeParseReturn($i, $line);
                    } else {
                        $this->maybeParsePHPStanReturn($i, $line);
                    }
                    break;
                case 'phpstan-param':
                case 'psalm-param':
                    $this->maybeParsePHPStanParam($i, $line);
                    break;
                case 'phpstan-var':
                case 'psalm-var':
                    $this->maybeParsePHPStanVar($i, $line);
                    break;
                case 'returns':
                    $this->emitIssue(
                        Issue::MisspelledAnnotation,
                        $this->guessActualLineLocation($i),
                        '@returns',
                        'Did you mean @return?'
                    );
                    break;
                case 'throws':
                    $this->maybeParseThrows($i, $line);
                    break;
                case 'throw':
                    $this->emitIssue(
                        Issue::MisspelledAnnotation,
                        $this->guessActualLineLocation($i),
                        '@throw',
                        'Did you mean @throws?'
                    );
                    break;
                case 'suppress':
                    $this->maybeParseSuppress($i, $line);
                    break;
                case 'property':
                case 'property-read':
                case 'property-write':
                    $this->maybeParseProperty($i, $line);
                    break;
                case 'method':
                    $this->maybeParseMethod($i, $line);
                    break;
                case 'phanclosurescope':
                case 'phan-closure-scope':
                    $this->maybeParsePhanClosureScope($i, $line);
                    break;
                case 'readonly':
                    $this->setPhanAccessFlag($i, false, 'readonly');
                    break;
                case 'immutable':
                    // The same as (at)phan-immutable: the properties of the class cannot be modified after the object is created.
                    // ((at)psalm-immutable is an alias)
                    $this->setPhanAccessFlag($i, false, 'immutable');
                    break;
                case 'mixin':
                    $this->parseMixin($i, $line, 'mixin');
                    break;
                case 'seal-properties':
                    if ($this->checkCompatible('@seal-properties', [Comment::ON_CLASS], $i)) {
                        $this->comment_flags |= Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES;
                    }
                    return;
                case 'seal-methods':
                    if ($this->checkCompatible('@seal-methods', [Comment::ON_CLASS], $i)) {
                        $this->comment_flags |= Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS;
                    }
                    return;
                case 'deprecated':
                    if (\preg_match('/@deprecated\b/', $line, $match)) {
                        $this->comment_flags |= Flags::IS_DEPRECATED;
                    }
                    break;
                case 'abstract':
                    if (\preg_match('/@abstract\b/', $line, $match)) {
                        $this->comment_flags |= Flags::IS_PHPDOC_ABSTRACT;
                    }
                    break;
                case 'no-named-arguments':
                    if ($this->checkCompatible('@no-named-arguments', Comment::ON_CLASS_OR_FUNCTIONLIKE, $i)) {
                        $this->comment_flags |= Flags::NO_NAMED_ARGUMENTS;
                    }
                    break;
                default:
                    if (str_starts_with($type, 'phan-')) {
                        $this->maybeParsePhanCustomAnnotation($i, $line, $type, $case_sensitive_type, $trimmed);
                    }
                    break;
            }
        }

        if (str_contains($line, '@internal')) {
            if (\preg_match('/@internal\b/', $line, $match)) {
                $this->comment_flags |= Flags::IS_NS_INTERNAL;
            }
        }

        if (str_contains($line, 'verride')) {
            if (\preg_match('/@([Oo]verride)\b/', $line, $match)) {
                // TODO: split class const and global const.
                if ($this->checkCompatible('@override', [Comment::ON_METHOD, Comment::ON_CONST, Comment::ON_PROPERTY], $i)) {
                    $this->comment_flags |= Flags::IS_OVERRIDE_INTENDED;
                }
            }
        }
    }

    private function parseMixin(int $i, string $line, string $annotation_name): void
    {
        if (!Config::getValue('read_mixin_annotations')) {
            return;
        }
        if (!$this->checkCompatible('@' . $annotation_name, [Comment::ON_CLASS], $i)) {
            return;
        }
        $type = $this->typeFromCommentLine($line);
        if ($type) {
            // TODO: Warn about invalid mixins
            if ($type->isObjectWithKnownFQSEN()) {
                $this->phan_overrides['mixin'][] = $type;
            } else {
                $this->emitIssue(
                    Issue::InvalidMixin,
                    $this->guessActualLineLocation($i),
                    $type
                );
            }
        }
    }

    /**
     * Add the type alias mapping to $context for the current namespace block.
     */
    public static function addTypeAliasMapping(CodeBase $code_base, Context $context, string $alias_name, string $union_type_string, string $line = ''): void
    {
        if (Type::isInternalTypeString($alias_name, Type::FROM_PHPDOC)) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::TypeAliasInternalTypeConflict,
                $context->getLineNumberStart(),
                $alias_name,
                $union_type_string
            );
            return;
        }
        $union_type = UnionType::fromStringInContext(
            $union_type_string,
            $context,
            Type::FROM_PHPDOC,
            $code_base
        );
        if ($union_type->typeCount() === 0) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::CommentUnextractableTypeAlias,
                $context->getLineNumberStart(),
                $line
            );
            return;
        }
        $context->addTypeAlias(
            $alias_name,
            GenericMultiType::fromTypeSet($union_type->getTypeSet())
        );
    }

    /**
     * @internal
     */
    public const PHAN_TYPE_ALIAS_REGEX = '/@phan-type\s+' . self::WORD_REGEX . '\s*=\s*(' . UnionType::union_type_regex . ')/';

    /**
     * `(at)phpstan-type Name Type`, `(at)phpstan-type Name = Type` and `(at)psalm-type Name = Type` (the `=` is optional for both)
     * @internal
     */
    public const FOREIGN_TYPE_ALIAS_REGEX = '/@(?:phpstan|psalm)-type\s+' . self::WORD_REGEX . '(?:\s*=\s*|\s+)(' . UnionType::union_type_regex . ')/i';


    private function parsePhanType(int $i, string $line): void
    {
        $lineno = $this->guessActualLineLocation($i);
        if (!\preg_match(self::PHAN_TYPE_ALIAS_REGEX, $line, $matches) && !\preg_match(self::FOREIGN_TYPE_ALIAS_REGEX, $line, $matches)) {
            $this->emitIssue(
                Issue::CommentUnextractableTypeAlias,
                $lineno,
                $line
            );

            return;
        }
        $alias_name = $matches[1];
        $union_type_string = $matches[2];
        self::addTypeAliasMapping($this->code_base, (clone $this->context)->withLineNumberStart($lineno), $alias_name, $union_type_string, $line);
        $this->declared_type_alias_names[] = $alias_name;
    }

    /**
     * `(at)phpstan-import-type Name from ClassName` and `(at)phpstan-import-type Name from ClassName as OtherName` (also phan-, psalm-)
     * @internal
     */
    public const IMPORT_TYPE_ALIAS_REGEX = '/@(?:phan|phpstan|psalm)-import-type\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s+from\s+(\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*)(?:\s+as\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?/i';

    private function parseImportType(int $i, string $line): void
    {
        $lineno = $this->guessActualLineLocation($i);
        $local_name = self::importTypeAlias($this->code_base, (clone $this->context)->withLineNumberStart($lineno), $line);
        if ($local_name === null) {
            $this->emitIssue(
                Issue::CommentUnextractableTypeAlias,
                $lineno,
                $line
            );
        } elseif ($local_name !== '') {
            // The class also passes the alias on to the classes that import it from this class.
            $this->declared_type_alias_names[] = $local_name;
        }
    }

    /**
     * Adds the type alias that $line imports from another class to $context.
     *
     * If the alias cannot be found (e.g. the file of the class is not found), the name becomes an alias of `mixed`,
     * so that it is not taken for the name of a class.
     *
     * @return ?string the name of the alias in this file, '' if the name cannot be used (an issue was emitted),
     * or null if the alias was not found
     */
    private static function importTypeAlias(CodeBase $code_base, Context $context, string $line): ?string
    {
        if (!\preg_match(self::IMPORT_TYPE_ALIAS_REGEX, $line, $parts)) {
            return null;
        }
        $alias_name = $parts[1];
        $local_name = $parts[3] ?? $alias_name;
        if (Type::isInternalTypeString($local_name, Type::FROM_PHPDOC)) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::TypeAliasInternalTypeConflict,
                $context->getLineNumberStart(),
                $local_name,
                $alias_name . ' from ' . $parts[2]
            );
            return '';
        }
        // (Looked up also when the file already has an alias of this name: a comment of a class with templates is parsed twice,
        // and the first time adds the fallback for an import that is not found)
        $type = null;
        try {
            // The name of the class is resolved like a name in the code: `DTO\Provider` is relative to the namespace.
            $class_fqsen = $parts[2][0] === '\\'
                ? FullyQualifiedClassName::fromFullyQualifiedString($parts[2])
                : FullyQualifiedClassName::fromNameInContext($parts[2], $context);
            $type = TypeAliasImporter::getTypeAliasOfClass($code_base, $class_fqsen, $alias_name, $context->getFile());
        } catch (FQSENException) {
            // Not the name of a class
        }
        // (Does nothing if the file already has an alias of this name)
        $context->addTypeAlias($local_name, $type ?? MixedType::instance(false));
        return $type !== null ? $local_name : null;
    }

    /**
     * Returns the type aliases that a doc comment of a class declares, without parsing the rest of the comment.
     * (For TypeAliasImporter. The aliases are also added to $context)
     *
     * @param ?FullyQualifiedClassName $class_fqsen if set, the aliases are also recorded for this class in $code_base as they are found
     * @return array<string,Type>
     * @internal
     */
    public static function extractTypeAliases(CodeBase $code_base, Context $context, string $doc_comment, ?FullyQualifiedClassName $class_fqsen = null): array
    {
        if (\stripos($doc_comment, '-type') === false) {
            return [];
        }
        $result = [];
        foreach (\explode("\n", self::reduceMultiline($doc_comment)) as $line) {
            if (\stripos($line, '-type') === false) {
                continue;
            }
            // As in build(): a tag starts the line. (`Example: @phpstan-type Token int` in the text is not a declaration)
            if ((\preg_replace('/^\/?[*\s]+/', '', \trim($line))[0] ?? '') !== '@') {
                continue;
            }
            if (\preg_match(self::PHAN_TYPE_ALIAS_REGEX, $line, $matches) || \preg_match(self::FOREIGN_TYPE_ALIAS_REGEX, $line, $matches)) {
                self::addTypeAliasMapping($code_base, $context, $matches[1], $matches[2], $line);
                $type = $context->getTypeAlias($matches[1]);
                if ($type !== null) {
                    $result[$matches[1]] = $type;
                    if ($class_fqsen !== null) {
                        // Recorded as soon as it is known: an import further down can lead to a class that imports this alias.
                        $code_base->setTypeAliasesOfClass($class_fqsen, $result);
                    }
                }
            } elseif (\stripos($line, '-import-type') !== false) {
                $local_name = self::importTypeAlias($code_base, $context, $line);
                if (\is_string($local_name) && $local_name !== '') {
                    $type = $context->getTypeAlias($local_name);
                    if ($type !== null) {
                        $result[$local_name] = $type;
                        if ($class_fqsen !== null) {
                            $code_base->setTypeAliasesOfClass($class_fqsen, $result);
                        }
                    }
                }
            }
        }
        return $result;
    }

    private function parseParamLine(int $i, string $line): void
    {
        if (!$this->checkCompatible('@param', Comment::FUNCTION_LIKE, $i)) {
            return;
        }
        $param = self::parameterFromCommentLine($line, false, $i);
        $this->parameter_list[] = $param;
    }

    private function parseUnusedParamLine(int $i, string $line): void
    {
        if (!$this->checkCompatible('@unused-param', Comment::FUNCTION_LIKE, $i)) {
            return;
        }
        if (\preg_match(self::UNUSED_PARAM_COMMENT_REGEX, $line, $match)) {
            // Currently only used in VariableTrackerPlugin
            $name = $match[16];
            $this->phan_overrides['unused-param'][$name] = $name;
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                \trim($line)
            );
        }
    }

    private function maybeParseVarLine(int $i, string $line): void
    {
        if (!$this->checkCompatible('@var', Comment::HAS_VAR_ANNOTATION, $i)) {
            return;
        }
        $comment_var = self::parameterFromCommentLine($line, true, $i);
        if (\in_array($this->comment_type, Comment::FUNCTION_LIKE, true)) {
            if ($comment_var->getName() !== '') {
                $this->variable_list[] = $comment_var;
            } else {
                $this->emitIssue(
                    Issue::UnextractableAnnotation,
                    $this->guessActualLineLocation($i),
                    \trim($line)
                );
            }
        } else {
            $this->variable_list[] = $comment_var;
        }
    }

    private function maybeParseTemplateType(int $i, string $line, string $tag_name): void
    {
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            if ($this->checkCompatible("@$tag_name", Comment::HAS_TEMPLATE_ANNOTATION, $i)) {
                $template_info = self::templateTypeFromCommentLine($line);
                if ($template_info) {
                    [$template_identifier, $variance, $constraint_string] = $template_info;
                    $constraint_union_type = null;
                    if ($constraint_string !== null && $constraint_string !== '') {
                        $constraint_union_type = UnionType::fromStringInContext(
                            $constraint_string,
                            $this->context,
                            Type::FROM_PHPDOC,
                            $this->code_base
                        );
                    }
                    $template_type = TemplateType::instanceForId($template_identifier, false, $constraint_union_type, $variance);
                    $name = $template_type->getName();
                    $is_phpstan = \stripos($line, '@phpstan-template') !== false;
                    if (isset($this->template_type_list[$name])) {
                        if ($is_phpstan) {
                            // The same template type for another tool: the first declaration is used.
                            return;
                        }
                        if (!isset($this->phpstan_template_names[$name])) {
                            $this->emitIssue(
                                Issue::TemplateTypeDuplicate,
                                $this->guessActualLineLocation($i),
                                (string)$template_type
                            );
                        }
                    }
                    $this->template_type_list[$name] = $template_type;
                    if ($is_phpstan) {
                        $this->phpstan_template_names[$name] = true;
                    } else {
                        // Also declared for Phan or Psalm, which was read before `@phpstan-template` was.
                        unset($this->phpstan_template_names[$name]);
                    }
                }
            }
        }
    }

    private function maybeParseInherits(int $i, string $line, string $type): void
    {
        if (!$this->checkCompatible('@' . $type, [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $this->inherited_type = $this->inheritsFromCommentLine($line);
        }
    }

    private function maybeParsePhanInherits(int $i, string $line, string $type): void
    {
        if (!$this->checkCompatible('@' . $type, [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $this->phan_overrides['inherits'] = $this->inheritsFromCommentLine($line);
        }
    }

    private function maybeParseImplements(int $i, string $line): void
    {
        if (!$this->checkCompatible('@implements', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $type = $this->typeFromCommentLine($line);
            if ($type !== null) {
                $this->implemented_types[] = $type;
            }
        }
    }

    private function maybeParsePhanImplements(int $i, string $line): void
    {
        if (!$this->checkCompatible('@phan-implements', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $type = $this->typeFromCommentLine($line);
            if ($type !== null) {
                $this->phan_overrides['implements'][] = $type;
            }
        }
    }

    private function maybeParseUse(int $i, string $line): void
    {
        if (!$this->checkCompatible('@use', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $type = $this->typeFromCommentLine($line);
            if ($type !== null) {
                $this->used_trait_types[] = $type;
            }
        }
    }

    private function maybeParseRequireExtends(int $i, string $line): void
    {
        if (!$this->checkCompatible('@require-extends', [Comment::ON_CLASS], $i)) {
            return;
        }
        $type = $this->requiredTypeFromCommentLine($i, $line);
        if ($type !== null) {
            $this->required_extends_types[] = $type;
        }
    }

    private function maybeParseRequireImplements(int $i, string $line): void
    {
        if (!$this->checkCompatible('@require-implements', [Comment::ON_CLASS], $i)) {
            return;
        }
        $type = $this->requiredTypeFromCommentLine($i, $line);
        if ($type !== null) {
            $this->required_implements_types[] = $type;
        }
    }

    /**
     * @return ?Type the class or interface named by a `@require-extends` or `@require-implements` line.
     * Null, with an issue, if the line has no type or a type that is not a class name (e.g. `int` or `static`).
     */
    private function requiredTypeFromCommentLine(int $i, string $line): ?Type
    {
        // `self` and `parent` are checked before the type is resolved: in a class or interface, they are resolved to its name or the name of its parent.
        if (\preg_match('/@[\w-]+\s+(' . Type::type_regex . ')/', $line, $match)
            && !\in_array(\strtolower(\ltrim($match[1], '\\')), ['self', 'static', 'parent'], true)
        ) {
            $type = Type::fromStringInContext($match[1], $this->context, Type::FROM_PHPDOC, $this->code_base);
            if ($type->isObjectWithKnownFQSEN() && !$type->isSelfType() && !$type->isStaticType()) {
                return $type;
            }
        }
        $this->emitIssue(
            Issue::UnextractableAnnotation,
            $this->guessActualLineLocation($i),
            \trim($line)
        );
        return null;
    }

    private function maybeParsePhanUse(int $i, string $line): void
    {
        if (!$this->checkCompatible('@phan-use', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $type = $this->typeFromCommentLine($line);
            if ($type !== null) {
                $this->phan_overrides['use'][] = $type;
            }
        }
    }

    /**
     * @internal
     */
    public const ASSERT_REGEX = '/@phan-assert(?:(-true-condition|-false-condition)|\s+(!?)(' . UnionType::union_type_regex . '))\s+\$' . self::WORD_REGEX . '/';
    private function assertFromCommentLine(string $line): ?Assertion
    {
        if (!\preg_match(self::ASSERT_REGEX, $line, $match)) {
            return null;
        }
        $extra_text = $match[1];
        if (\strlen($extra_text) > 0) {
            $assertion_type = $extra_text === '-true-condition' ? Assertion::IS_TRUE : Assertion::IS_FALSE;
            $union_type = UnionType::empty();
        } else {
            $assertion_type = $match[2] === '!' ? Assertion::IS_NOT_OF_TYPE : Assertion::IS_OF_TYPE;
            $type_string = $match[3];
            $union_type = UnionType::fromStringInContext(
                $type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
        }
        $param_name = $match[17];

        return new Assertion($union_type, $param_name, $assertion_type);
    }

    /**
     * @param string $line
     * An individual line of a comment, starting with a '@' tag, assumed to have been validated already
     *
     * @return ?Type
     * A type documented on this line, or null if not found
     */
    private function typeFromCommentLine(string $line): ?Type
    {
        if (\preg_match('/@[\w-]+\s+(' . Type::type_regex . ')/', $line, $match)) {
            return Type::fromStringInContext(
                $match[1],
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
        }
        return null;
    }

    private function maybeParsePhanAssert(int $i, string $line): void
    {
        if (!$this->checkCompatible('@phan-assert', Comment::FUNCTION_LIKE, $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        $assert = $this->assertFromCommentLine($line);
        if ($assert) {
            $this->param_assertion_map[$assert->param_name] = $assert;
        }
    }

    /**
     * @internal
     */
    public const CONDITIONAL_ASSERT_REGEX = '/@(phan|phpstan|psalm)-assert-if-(true|false)\s+(=?!?|!=)(' . UnionType::union_type_regex . ')\s+\$(' . self::WORD_REGEX . ')(?=\s|$)/i';

    /**
     * Parses `(at)phan-assert-if-true T $param`, `(at)phpstan-assert-if-false !T $param`, etc.
     *
     * As for PHPStan, the opposite is assumed when the function returns the other value, unless the type is written as `=T`.
     * For the psalm- form, nothing is assumed for the other value.
     *
     * Only assertions about a parameter are supported, not about `$this->prop` or `$this->method()`.
     */
    private function maybeParseConditionalAssert(int $i, string $line): void
    {
        // (`$this->prop`, `$param->method()` etc. are not matched: only a parameter can be asserted)
        if (!\preg_match(self::CONDITIONAL_ASSERT_REGEX, $line, $match)) {
            $this->checkCompatible('@phan-assert-if-true', Comment::FUNCTION_LIKE, $i);
            return;
        }
        $if_true = \strtolower($match[2]) === 'true';
        if (!$this->checkCompatible('@' . \strtolower($match[1]) . '-assert-if-' . ($if_true ? 'true' : 'false'), Comment::FUNCTION_LIKE, $i)) {
            return;
        }
        $union_type = UnionType::fromStringInContext(
            $match[4],
            $this->context,
            Type::FROM_PHPDOC,
            $this->code_base
        );
        if ($union_type->isEmpty()) {
            return;
        }
        // A type that Phan reads as a wider one (`numeric` and `numeric-string` are any string) can be asserted,
        // but its opposite cannot: a value that is not numeric can still be a string.
        // (Also an array shape that can have other keys, which is read as the array shape or any array,
        // and a type alias, which can stand for any of these)
        $is_approximated = \preg_match(self::APPROXIMATED_TYPE_REGEX, self::withoutQuotedLiterals($match[4])) > 0
            || (str_contains($match[4], '...') && \preg_match(self::OPEN_SHAPE_REGEX, $match[4]))
            || $this->usesTypeAlias($match[4]);
        $is_negated = \str_contains($match[3], '!');
        if ($is_approximated && $is_negated) {
            return;
        }
        $this->conditional_assertion_list[] = new ConditionalAssertion(
            $union_type,
            $match[\count($match) - 1],
            $if_true,
            $is_negated,
            !$is_approximated && !\str_contains($match[3], '=') && \strtolower($match[1]) !== 'psalm'
        );
    }

    private function setPhanAccessFlag(int $i, bool $write_only, string $name): void
    {
        // Make sure support for generic types is enabled
        if ($this->comment_type === Comment::ON_PROPERTY) {
            $this->comment_flags |= ($write_only ? Flags::IS_WRITE_ONLY : Flags::IS_READ_ONLY);
            return;
        }
        if ($write_only) {
            $this->checkCompatible("@$name", [Comment::ON_PROPERTY], $i);
            return;
        }
        if ($this->checkCompatible("@$name", [Comment::ON_PROPERTY, Comment::ON_CLASS], $i)) {
            $this->comment_flags |= Flags::IS_READ_ONLY;
        }
    }

    private function maybeParseReturn(int $i, string $line): void
    {
        if (!$this->checkCompatible('@return', Comment::FUNCTION_LIKE, $i)) {
            return;
        }
        $return_comment = $this->return_comment;
        $issue_count = \count($this->issues);
        $conditional = $this->parseConditionalReturnFromCommentLine($line, $i);
        if ($conditional) {
            if (\count($this->issues) !== $issue_count || \preg_match('/\)\s*[|&]/', $line)) {
                // A part of the annotation was not understood, such as the `|null` of `($x is int ? A : B)|null`:
                // the branches are still used for the type of a call, but not to decide that a call does not return.
                $conditional = $conditional->asApproximated();
            }
            $new_type = $conditional->asFlattenedUnionType();
        } else {
            $new_type = $this->returnOrThrowsTypeFromCommentLine($line, $i);
        }
        if ($return_comment) {
            // e.g. `@return A|B|false` followed by `@psalm-return ($x is null ? A : B)`:
            // the flattened type is the union of both, and `false` is possible for every call of the conditional.
            $old_type = $return_comment->getType();
            $return_comment->setType($old_type->withUnionType($new_type));
            $old_conditional = $return_comment->getConditional();
            if ($conditional && !$old_conditional) {
                $return_comment->setConditional($conditional->withPlainAnnotationTypes($old_type));
            } elseif (!$conditional && $old_conditional) {
                $return_comment->setConditional($old_conditional->withPlainAnnotationTypes($new_type));
            } elseif ($conditional && $old_conditional) {
                // Two conditionals: keep the first one's structure, and treat the second one's types as always possible.
                // (Not if they are the same, e.g. one conditional as `@psalm-return` and as `@phpstan-return`:
                // that would add the types of one branch to the other, and to a branch that is `never`)
                if (!$conditional->isSameAs($old_conditional)) {
                    $return_comment->setConditional($old_conditional->withTypesAddedToLeaves($new_type));
                }
            }
        } else {
            $this->return_comment = new ReturnComment($new_type, $this->guessActualLineLocation($i), $conditional);
        }
    }

    /**
     * `(at)phpstan-return` is only used if Phan can parse all of it.
     * A plain type replaces the type of `(at)return` (also for `(at)psalm-return`).
     * A conditional type (`(at)phpstan-return ($args is array{exit: false} ? void : never)`) is read like one of `(at)return`.
     *
     * Anything else is ignored without an issue: the annotation is written for another tool,
     * in a syntax that Phan only knows in part, and the function still has its `(at)return`.
     */
    private function maybeParsePHPStanReturn(int $i, string $line): void
    {
        if (!\in_array($this->comment_type, Comment::FUNCTION_LIKE, true)) {
            return;
        }
        if (!\preg_match(self::CONDITIONAL_RETURN_START_REGEX, $line)) {
            // A plain type replaces the type of `@return`, like `@phan-return` (which is preferred to it).
            $line = $this->withTypeContinuationLines($i, $line);
            $type = $this->parseOnlyIfUnderstood(function () use ($line, $i): UnionType {
                return $this->returnOrThrowsTypeFromCommentLine($line, $i);
            }, $line);
            if ($type instanceof UnionType && !$type->isEmpty() && self::isTypeFollowedByWhitespace(self::RETURN_OR_THROWS_COMMENT_REGEX, $line)) {
                $this->phpstan_return_comment = new ReturnComment($type, $this->guessActualLineLocation($i));
            } else {
                $this->has_ignored_phpstan_annotation = true;
            }
            return;
        }
        // Applied after the other annotations of the comment were read (applyPHPStanAnnotations()):
        // with `@phpstan-template`, it is only used if all of the `@phpstan-` annotations are.
        $this->phpstan_conditional_return_lines[] = [$i, $line];
    }

    /**
     * @return bool whether Phan parses all of this conditional `(at)phpstan-return`, without approximating a type.
     * Not if it has something that Phan does not parse or only approximates, since a branch may decide that a call does not return:
     * - a type after the closing bracket (`(...) | null`; without the whitespace, the parser reports it);
     * - a type that Phan reads as a wider one (APPROXIMATED_TYPE_REGEX).
     */
    private function isPHPStanConditionalReturnUnderstood(int $i, string $line): bool
    {
        // (The types are looked for in the conditional type, not in the literal strings of it or in the description after it)
        if (\preg_match('/\)\s*[|&]/', $line) || \preg_match(self::APPROXIMATED_TYPE_REGEX, self::conditionalTypeTextOf($line))) {
            return false;
        }
        $issues = $this->issues;
        $conditional = $this->parseConditionalReturnFromCommentLine($line, $i);
        // (An issue means that a part of the annotation was not understood, such as the `|null` of `($x is int ? A : B)|null`)
        $is_understood = $conditional !== null && \count($this->issues) === \count($issues);
        $this->issues = $issues;
        // (A name that Phan does not know as a type alias is read as a class)
        return $is_understood && !DeclaredClassIndex::conditionalHasUndeclaredClass($this->code_base, $conditional);
    }

    /**
     * @return string $type_string with every literal string replaced by `''`
     */
    private static function withoutQuotedLiterals(string $type_string): string
    {
        if (!str_contains($type_string, "'") && !str_contains($type_string, '"')) {
            return $type_string;
        }
        return \preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"[^"]*"/', "''", $type_string) ?? $type_string;
    }

    /**
     * @return string the conditional type of an annotation line, from its first `(` to the matching `)`, without its literal strings.
     * (All of the line from the `(` if the brackets do not match)
     */
    private static function conditionalTypeTextOf(string $line): string
    {
        $start = \strpos($line, '(');
        if ($start === false) {
            return $line;
        }
        $text = self::withoutQuotedLiterals(\substr($line, $start));
        $depth = 0;
        $length = \strlen($text);
        for ($i = 0; $i < $length; $i++) {
            if ($text[$i] === '(') {
                $depth++;
            } elseif ($text[$i] === ')' && --$depth === 0) {
                return \substr($text, 0, $i + 1);
            }
        }
        return $text;
    }

    private function applyPHPStanConditionalReturn(int $i, string $line, bool $is_understood): void
    {
        $return_comment = $this->return_comment;
        $old_conditional = $return_comment ? $return_comment->getConditional() : null;
        if ($return_comment && $old_conditional) {
            // e.g. the same conditional as `@psalm-return` and as `@phpstan-return`: the first one is used.
            // (A second conditional would add its types to every branch of the first one)
            $issues = $this->issues;
            $conditional = $this->parseConditionalReturnFromCommentLine($line, $i);
            $this->issues = $issues;
            if (!$conditional || !$conditional->isSameAs($old_conditional)) {
                // The two annotations say different things: the first one is not relied on to decide that a call does not return.
                $return_comment->setConditional($old_conditional->asApproximated());
            }
            return;
        }
        if ($is_understood) {
            $this->maybeParseReturn($i, $line);
        }
    }

    /**
     * Parses the part of an `(at)phpstan-param`, `(at)phpstan-var` or non-conditional `(at)phpstan-return` annotation (also psalm-)
     * with the given parser, and discards the result unless Phan understood all of it.
     *
     * These annotations are written for another tool, in a syntax that Phan only knows in part
     * (`key-of<T>`, `int-mask<1, 2>`, `($x is int ? A : B) $param`, ...), and the element still has its plain annotation.
     * Nothing is reported for the part of an `(at)phpstan-` annotation that is not understood.
     * An `(at)phpstan-` annotation with a class that is not declared in the files that Phan parses is not understood either.
     * It is reported for `(at)psalm-`, which Phan has always read.
     *
     * @param Closure():(Parameter|UnionType) $parse
     * @return Parameter|UnionType|null null if a part of the annotation was not understood
     */
    private function parseOnlyIfUnderstood(Closure $parse, string $line): Parameter|UnionType|null
    {
        $issues = $this->issues;
        // @phan-suppress-next-line PhanAccessPropertyInternal
        $unparsed_closure_param_count = Type::$unparsed_closure_param_count;
        $result = $parse();
        // (A parameter of a closure type that is not parsed is left out without an issue: `callable(int-mask<1, 2>): void`)
        // @phan-suppress-next-line PhanAccessPropertyInternal
        $is_understood = \count($this->issues) === \count($issues) && Type::$unparsed_closure_param_count === $unparsed_closure_param_count;
        // (Reported for `@psalm-`, as it was before these annotations replaced the plain type)
        if (\stripos($line, '@psalm-') === false) {
            $this->issues = $issues;
        }
        if ($is_understood && \stripos($line, '@phpstan-') !== false) {
            // A name that Phan does not know as a type alias (e.g. one of `typeAliases` in phpstan.neon) is read as a class.
            // (For `@psalm-`, the class is reported as undeclared, as before)
            $is_understood = !DeclaredClassIndex::hasUndeclaredClass($this->code_base, $result instanceof Parameter ? $result->getUnionType() : $result);
        }
        if (!$is_understood) {
            $this->has_ignored_phpstan_annotation = true;
            return null;
        }
        return $result;
    }

    /**
     * @return bool whether the type that $regex matches in $line is followed by whitespace or by the end of the line, and not by more of a type.
     * (Not for `key-of<Foo>`, of which only `key` is a type that Phan knows, and not for `int | (...)`)
     */
    private static function isTypeFollowedByWhitespace(string $regex, string $line): bool
    {
        if (!\preg_match($regex, $line, $match, \PREG_OFFSET_CAPTURE)) {
            return false;
        }
        $rest = \substr($line, $match[0][1] + \strlen($match[0][0]));
        if (\preg_match('/^\s*[|&]/', $rest)) {
            // The type goes on: `int | ($x is null ? null : string)`
            return false;
        }
        return $rest === '' || \ord($rest[0]) <= 32 || \preg_match('@^\*+/$@D', $rest) > 0;
    }

    /**
     * @return string $line, followed by the lines after it that continue a union or intersection type:
     *
     *     (at)phpstan-return array{file: string}
     *                    |array{error: string}
     */
    private function withTypeContinuationLines(int $i, string $line): string
    {
        while (\preg_match('/^[\s*]*([|&].*)$/D', $this->lines[++$i] ?? '', $match)) {
            $line = \rtrim($line) . \rtrim($match[1]);
        }
        return $line;
    }

    /**
     * `(at)phpstan-param T $x` and `(at)psalm-param T $x` replace the type of `(at)param`, like `(at)phan-param` (which is preferred to them).
     * It is ignored if Phan does not understand all of it.
     */
    private function maybeParsePHPStanParam(int $i, string $line): void
    {
        if (!\in_array($this->comment_type, Comment::FUNCTION_LIKE, true)) {
            return;
        }
        $line = $this->withTypeContinuationLines($i, $line);
        $param = $this->parseOnlyIfUnderstood(function () use ($line, $i): Parameter {
            return $this->parameterFromCommentLine($line, false, $i);
        }, $line);
        // (Without a name, the type was not followed by the variable: `callable(T): bool $predicate`)
        if ($param instanceof Parameter && $param->getName() !== '' && !$param->getUnionType()->isEmpty()) {
            $this->phpstan_param_list[] = $param;
        } else {
            $this->has_ignored_phpstan_annotation = true;
        }
    }

    /**
     * `(at)phpstan-var T` and `(at)phpstan-var T $x` (also psalm-) replace the type of `(at)var`, like `(at)phan-var` (which is preferred to them).
     * It is ignored if Phan does not understand all of it.
     */
    private function maybeParsePHPStanVar(int $i, string $line): void
    {
        if (!\in_array($this->comment_type, Comment::HAS_VAR_ANNOTATION, true)) {
            return;
        }
        $line = $this->withTypeContinuationLines($i, $line);
        $comment_var = $this->parseOnlyIfUnderstood(function () use ($line, $i): Parameter {
            return $this->parameterFromCommentLine($line, true, $i);
        }, $line);
        if (!($comment_var instanceof Parameter) || $comment_var->getUnionType()->isEmpty()) {
            return;
        }
        if ($comment_var->getName() === '') {
            // In a function, a variable is needed. Elsewhere, the type has to be all of the annotation.
            if (\in_array($this->comment_type, Comment::FUNCTION_LIKE, true) || !self::isTypeFollowedByWhitespace(self::PARAM_COMMENT_REGEX, $line)) {
                $this->has_ignored_phpstan_annotation = true;
                return;
            }
        }
        $this->phpstan_var_list[] = $comment_var;
    }

    /**
     * @return bool whether this comment of a property or constant has one `(at)phpstan-var` (or psalm-) and at most one `(at)var`,
     * which do not name different elements (`@var int $a` and `@phpstan-var string $b` for `public $a, $b;`)
     */
    private function isOneVarAnnotationForTheSameElement(): bool
    {
        if (\count($this->phpstan_var_list) !== 1 || \count($this->variable_list) > 1 || \in_array($this->comment_type, Comment::FUNCTION_LIKE, true)) {
            return false;
        }
        $plain_name = $this->variable_list ? \reset($this->variable_list)->getName() : '';
        $name = $this->phpstan_var_list[0]->getName();
        if ($plain_name !== '' && $name !== '' && $plain_name !== $name) {
            return false;
        }
        // (`@psalm-var int $a` and `@phan-var string $b` for `public $a, $b;` are for different elements, too)
        foreach (self::parametersOf($this->phan_overrides['var'] ?? []) as $phan_var) {
            $phan_name = $phan_var->getName();
            if ($phan_name !== '' && $name !== '' && $phan_name !== $name) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool whether an `(at)param`, `(at)var` or `(at)return` annotation of this comment has a template type
     */
    private function hasTemplateTypeInPlainAnnotations(): bool
    {
        foreach ($this->parameter_list as $parameter) {
            if ($parameter->getUnionType()->hasTemplateTypeRecursive()) {
                return true;
            }
        }
        foreach ($this->variable_list as $variable) {
            if ($variable->getUnionType()->hasTemplateTypeRecursive()) {
                return true;
            }
        }
        return $this->return_comment !== null && $this->return_comment->getType()->hasTemplateTypeRecursive();
    }

    /**
     * @param mixed $list an entry of $this->phan_overrides
     * @return list<Parameter>
     */
    private static function parametersOf(mixed $list): array
    {
        $result = [];
        if (\is_array($list)) {
            foreach ($list as $parameter) {
                if ($parameter instanceof Parameter) {
                    $result[] = $parameter;
                }
            }
        }
        return $result;
    }

    /**
     * Adds what was read from `(at)phpstan-param`, `(at)phpstan-var` and `(at)phpstan-return` to the overrides,
     * of which the `(at)phan-` annotations are applied last.
     */
    private function applyPHPStanAnnotations(): void
    {
        $understood_conditional_returns = [];
        foreach ($this->phpstan_conditional_return_lines as [$i, $line]) {
            $is_understood = $this->isPHPStanConditionalReturnUnderstood($i, $line);
            $understood_conditional_returns[] = $is_understood;
            if (!$is_understood) {
                $this->has_ignored_phpstan_annotation = true;
            }
        }
        if ($this->phpstan_template_names && $this->has_ignored_phpstan_annotation) {
            // e.g. `@phpstan-template T`, `@phpstan-param T $value` and `@phpstan-return (T is string ? string : T)`,
            // of which Phan does not read the last one: the parameter would be a T that the return type does not have.
            // The annotations of the comment depend on each other, so none of them is used.
            // (The template types stay if a plain annotation uses one)
            if (!$this->hasTemplateTypeInPlainAnnotations()) {
                foreach ($this->phpstan_template_names as $name => $_) {
                    unset($this->template_type_list[$name]);
                }
            }
            return;
        }
        foreach ($this->phpstan_conditional_return_lines as $index => [$i, $line]) {
            $this->applyPHPStanConditionalReturn($i, $line, $understood_conditional_returns[$index]);
        }
        if ($this->phpstan_param_list) {
            // The flags of the `@param` are kept: `@param string &$x @phan-output-reference` and `@phpstan-param non-empty-string $x`.
            $plain_params = [];
            foreach ($this->parameter_list as $plain_param) {
                $plain_params[$plain_param->getName()] = $plain_param;
            }
            $params = [];
            foreach ($this->phpstan_param_list as $param) {
                $plain_param = $plain_params[$param->getName()] ?? null;
                if ($plain_param && ($plain_param->isOutputReference() || $plain_param->isIgnoredReference() || $plain_param->isMandatoryInPHPDoc())) {
                    $param = new Parameter(
                        $param->getName(),
                        $param->getUnionType(),
                        $param->getLineno(),
                        $param->isVariadic(),
                        false,
                        $param->isOutputReference() || $plain_param->isOutputReference(),
                        $param->isIgnoredReference() || $plain_param->isIgnoredReference(),
                        $param->isMandatoryInPHPDoc() || $plain_param->isMandatoryInPHPDoc()
                    );
                }
                $params[] = $param;
            }
            // (The last one of a name is used)
            $this->phan_overrides['param'] = \array_merge($params, self::parametersOf($this->phan_overrides['param'] ?? []));
        }
        if ($this->isOneVarAnnotationForTheSameElement()) {
            // The annotations of a property or constant are for the same element, whether or not they name it:
            // `@var array` and `@phpstan-var list<int> $rows`.
            if (!self::parametersOf($this->phan_overrides['var'] ?? [])) {
                $this->variable_list = $this->phpstan_var_list;
            }
        } elseif ($this->phpstan_var_list) {
            $phan_vars = self::parametersOf($this->phan_overrides['var'] ?? []);
            $phan_names = [];
            foreach ($phan_vars as $phan_var) {
                $phan_names[$phan_var->getName()] = true;
            }
            // Before the `@phan-var` annotations: an annotation that replaces no `@var` is added in this order,
            // and `@phpstan-var int $a` is like a `@var` (the annotations of `public $a, $b;` are matched by position).
            $vars = [];
            foreach ($this->phpstan_var_list as $phpstan_var) {
                if (!isset($phan_names[$phpstan_var->getName()])) {
                    $vars[] = $phpstan_var;
                }
            }
            $this->phan_overrides['var'] = \array_merge($vars, $phan_vars);
        }
        if ($this->phpstan_return_comment && !isset($this->phan_overrides['return'])) {
            // (A conditional return type of `@return` or `@psalm-return` is kept: it says more than a plain type)
            if (!$this->return_comment || !$this->return_comment->getConditional()) {
                $this->phan_overrides['return'] = $this->phpstan_return_comment;
            }
        }
    }

    private function maybeParseThrows(int $i, string $line): void
    {
        if (!$this->checkCompatible('@throws', Comment::FUNCTION_LIKE, $i)) {
            return;
        }
        $this->throw_union_type = $this->throw_union_type->withUnionType(
            $this->returnOrThrowsTypeFromCommentLine($line, $i)
        );
    }

    private function maybeParseSuppress(int $i, string $line): void
    {
        $suppress_issue_types = $this->suppressIssuesFromCommentLine($line);
        if (count($suppress_issue_types) > 0) {
            foreach ($suppress_issue_types as $issue_type) {
                $this->suppress_issue_set[$issue_type] = 0;
            }
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                \trim($line)
            );
        }
    }

    private function maybeParseProperty(int $i, string $line): void
    {
        if (!$this->checkCompatible('@property', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for magic properties is enabled.
        if (Config::getValue('read_magic_property_annotations')) {
            $magic_property = $this->magicPropertyFromCommentLine($line, $i);
            if ($magic_property !== null) {
                $this->magic_property_list[] = $magic_property;
            }
        }
    }

    private function maybeParseMethod(int $i, string $line): void
    {
        // Make sure support for magic methods is enabled.
        if (Config::getValue('read_magic_method_annotations')) {
            if (!$this->checkCompatible('@method', [Comment::ON_CLASS], $i)) {
                return;
            }
            $magic_method = $this->magicMethodFromCommentLine($line, $i);
            if ($magic_method !== null) {
                $this->magic_method_list[] = $magic_method;
            }
        }
    }

    private function maybeParsePhanClosureScope(int $i, string $line): void
    {
        // TODO: different type for closures
        if ($this->checkCompatible('@phan-closure-scope', Comment::FUNCTION_LIKE, $i)) {
            $this->closure_scope = $this->getPhanClosureScopeFromCommentLine($line, $i);
        }
    }

    /**
     * @param string $trimmed the line without the leading `/**`, `*` and whitespace, starting with the first tag of the line
     */
    private function maybeParsePhanCustomAnnotation(int $i, string $line, string $type, string $case_sensitive_type, string $trimmed): void
    {
        switch ($type) {
            case 'phan-forbid-undeclared-magic-properties':
                if ($this->checkCompatible('@phan-forbid-undeclared-magic-properties', [Comment::ON_CLASS], $i)) {
                    $this->comment_flags |= Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES;
                }
                return;
            case 'phan-hardcode-return-type':
                if ($this->checkCompatible('@phan-hardcode-return-type', Comment::FUNCTION_LIKE, $i)) {
                    $this->comment_flags |= Flags::HARDCODED_RETURN_TYPE;
                }
                return;
            case 'phan-forbid-undeclared-magic-methods':
                if ($this->checkCompatible('@phan-forbid-undeclared-magic-methods', [Comment::ON_CLASS], $i)) {
                    $this->comment_flags |= Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS;
                }
                return;
            case 'phan-closure-scope':
                if ($this->checkCompatible('@phan-closure-scope', Comment::FUNCTION_LIKE, $i)) {
                    $this->closure_scope = $this->getPhanClosureScopeFromCommentLine($line, $i);
                }
                return;
            case 'phan-param':
                if ($this->checkCompatible('@phan-param', Comment::FUNCTION_LIKE, $i)) {
                    $this->phan_overrides['param'][] =
                        $this->parameterFromCommentLine($line, false, $i);
                }
                return;
            case 'phan-real-return':
                if ($this->checkCompatible('@phan-real-return', Comment::FUNCTION_LIKE, $i)) {
                    $this->phan_overrides['real-return'] = new ReturnComment($this->returnOrThrowsTypeFromCommentLine($line, $i)->asRealUnionType(), $this->guessActualLineLocation($i));
                }
                return;
            case 'phan-return':
                if ($this->checkCompatible('@phan-return', Comment::FUNCTION_LIKE, $i)) {
                    $conditional = $this->parseConditionalReturnFromCommentLine($line, $i);
                    $type = $conditional ? $conditional->asFlattenedUnionType() : $this->returnOrThrowsTypeFromCommentLine($line, $i);
                    $this->phan_overrides['return'] = new ReturnComment($type, $this->guessActualLineLocation($i), $conditional);
                }
                return;
            case 'phan-override':
                if ($this->checkCompatible('@override', [Comment::ON_METHOD, Comment::ON_CONST], $i)) {
                    $this->comment_flags |= Flags::IS_OVERRIDE_INTENDED;
                }
                return;
            case 'phan-abstract':
                $this->comment_flags |= Flags::IS_PHPDOC_ABSTRACT;
                return;
            case 'phan-var':
                if (!$this->checkCompatible('@phan-var', Comment::HAS_VAR_ANNOTATION, $i)) {
                    return;
                }
                $comment_var = $this->parameterFromCommentLine($line, true, $i);
                if (\in_array($this->comment_type, Comment::FUNCTION_LIKE, true)) {
                    if ($comment_var->getName() !== '') {
                        $this->phan_overrides['var'][] = $comment_var;
                    } else {
                        $this->emitIssue(
                            Issue::UnextractableAnnotation,
                            $this->guessActualLineLocation($i),
                            \trim($line)
                        );
                    }
                } else {
                    $this->phan_overrides['var'][] = $comment_var;
                }
                return;
            case 'phan-file-suppress':
                // See BuiltinSuppressionPlugin
                return;
            case 'phan-unused-param':
                $this->parseUnusedParamLine($i, $line);
                return;
            case 'phan-suppress':
                $this->maybeParseSuppress($i, $line);
                return;
            case 'phan-property':
            case 'phan-property-read':
            case 'phan-property-write':
                $this->parsePhanProperty($i, $line);
                return;
            case 'phan-pure':
            case 'phan-side-effect-free':
                // phan-side-effect-free = "phan-immutable" + "phan-external-mutation-free".
                // - Note that there is no way for Phan to use phan-external-mutation-free for analysis on its own right now,
                // so that annotation doesn't exist.
                //
                // Note that phan-side-effect-free is recommended over phan-pure to avoid confusion.
                // Functions with this annotation are not
                if ($this->checkCompatible('@' . $type, \array_merge(Comment::FUNCTION_LIKE, [Comment::ON_CLASS]), $i)) {
                    $this->comment_flags |= Flags::IS_SIDE_EFFECT_FREE;
                }
                return;
            case 'phan-immutable':
                $this->setPhanAccessFlag($i, false, 'phan-immutable');
                break;
            case 'phan-method':
                $this->parsePhanMethod($i, $line);
                return;
            case 'phan-suppress-next-line':
            case 'phan-suppress-next-next-line':
            case 'phan-suppress-current-line':
            case 'phan-suppress-previous-line':
                // Do nothing, see BuiltinSuppressionPlugin
                return;
            case 'phan-template':
                $this->maybeParseTemplateType($i, $line, $type);
                return;
            case 'phan-inherits':
            case 'phan-extends':
                $this->maybeParsePhanInherits($i, $line, \substr($type, 5));
                return;
            case 'phan-implements':
                $this->maybeParsePhanImplements($i, $line);
                return;
            case 'phan-use':
                $this->maybeParsePhanUse($i, $line);
                return;
            case 'phan-read-only':
                $this->setPhanAccessFlag($i, false, 'phan-read-only');
                return;
            case 'phan-write-only':
                $this->setPhanAccessFlag($i, true, 'phan-write-only');
                return;
            case 'phan-transient':
                // Do nothing, see SleepCheckerPlugin
                return;
            case 'phan-assert':
            case 'phan-assert-true-condition':
            case 'phan-assert-false-condition':
                $this->maybeParsePhanAssert($i, $line);
                return;
            case 'phan-assert-if-true':
            case 'phan-assert-if-false':
                $this->maybeParseConditionalAssert($i, $line);
                return;
            case 'phan-constructor-used-for-side-effects':
                if ($this->checkCompatible('@' . $type, [Comment::ON_CLASS], $i)) {
                    $this->comment_flags |= Flags::IS_CONSTRUCTOR_USED_FOR_SIDE_EFFECTS;
                }
                return;
            case 'phan-mixin':
                $this->parseMixin($i, $line, 'phan-mixin');
                return;
            case 'phan-type':
                $this->parsePhanType($i, $line);
                return;
            case 'phan-import-type':
                $this->parseImportType($i, $line);
                return;
            default:
                // This is only reached for a tag that Phan does not know, so plugins are only loaded here for such a tag.
                $plugin_annotations = ConfigPluginSet::instance()->getCustomAnnotationPluginMap();
                if (isset($plugin_annotations[$type])) {
                    $this->custom_annotations[] = new CustomAnnotation(
                        $type,
                        self::extractCustomAnnotationText($trimmed, $case_sensitive_type),
                        $this->guessCustomAnnotationLineLocation($i)
                    );
                    return;
                }
                $this->emitIssueWithSuggestion(
                    Issue::MisspelledAnnotation,
                    $this->guessActualLineLocation($i),
                    [
                        '@' . $case_sensitive_type,
                        "The annotations that this version of Phan supports can be seen by running 'phan --help-annotations' or by visiting https://github.com/phan/phan/wiki/Annotating-Your-Source-Code",
                    ],
                    self::generateSuggestionForMisspelledAnnotation($case_sensitive_type, $plugin_annotations)
                );
                return;
        }
    }

    /**
     * Returns the text of a custom annotation (CustomAnnotation::getText()).
     *
     * @param string $trimmed the line, starting with its first tag. The tag of the annotation is either at the start
     * or the first inline tag `{(at)$case_sensitive_type` (the main regex of parseCommentLine() matched the same position).
     */
    private static function extractCustomAnnotationText(string $trimmed, string $case_sensitive_type): string
    {
        if (!\preg_match('/(?:^|(\{))@' . \preg_quote($case_sensitive_type, '/') . '(?![a-zA-Z0-9_\x7f-\xff-])(.*)$/D', $trimmed, $match)) {
            // Should not happen
            return '';
        }
        $rest = $match[2];
        if ($match[1] !== '') {
            // The inline form `{(at)phan-x-name text}`: the text ends before the `}` that closes the tag.
            $depth = 1;
            for ($offset = 0, $length = \strlen($rest); $offset < $length; $offset++) {
                $c = $rest[$offset];
                if ($c === '{') {
                    $depth++;
                } elseif ($c === '}' && --$depth === 0) {
                    return \trim(\substr($rest, 0, $offset));
                }
            }
        }
        // The rest of the line, without the end of a doc comment on one line
        return \trim((string)\preg_replace('@\s*\*+/$@D', '', $rest));
    }

    /**
     * @param array<string,mixed> $plugin_annotations maps the names of the annotations that plugins declared to the plugins
     */
    private static function generateSuggestionForMisspelledAnnotation(string $annotation, array $plugin_annotations): ?Suggestion
    {
        $candidates = self::SUPPORTED_ANNOTATIONS;
        foreach ($plugin_annotations as $name => $_) {
            $candidates['@' . $name] = '';
        }
        $suggestions = IssueFixSuggester::getSuggestionsForStringSet('@' . $annotation, $candidates);
        if (!$suggestions) {
            return null;
        }
        return Suggestion::fromString('Did you mean ' . \implode(' or ', \array_keys($suggestions)));
    }

    /**
     * Maps supported annotations starting with phan- to the empty string or a description
     *
     * The names starting with `phan-x-` (CustomAnnotationCapability::NAME_PREFIX) are reserved for the annotations
     * that plugins declare: Phan must not give a meaning of its own to such a name, here or elsewhere.
     */
    public const SUPPORTED_ANNOTATIONS = [
        '@phan-assert' => '',
        '@phan-assert-if-true' => '',
        '@phan-assert-if-false' => '',
        '@phan-assert-true-condition' => '',
        '@phan-assert-false-condition' => '',
        '@phan-closure-scope' => '',
        '@phan-constructor-used-for-side-effects' => '',
        '@phan-extends' => '',
        '@phan-file-suppress' => '',
        '@phan-forbid-undeclared-magic-methods' => '',
        '@phan-forbid-undeclared-magic-properties' => '',
        '@phan-hardcode-return-type' => '',
        '@phan-implements' => '',
        '@phan-inherits' => '',
        '@phan-method' => '',
        '@phan-mixin' => '',
        '@phan-override' => '',
        '@phan-param' => '',
        '@phan-property' => '',
        '@phan-property-read' => '',
        '@phan-property-write' => '',
        '@phan-pure' => '',
        '@phan-read-only' => '',
        '@phan-return' => '',
        '@phan-real-return' => '',
        '@phan-suppress' => '',
        '@phan-suppress-current-line' => '',
        '@phan-suppress-next-line' => '',
        '@phan-suppress-next-next-line' => '',
        '@phan-suppress-previous-line' => '',
        '@phan-template' => '',
        '@phan-import-type' => '',
        '@phan-type' => '',
        '@phan-use' => '',
        '@phan-var' => '',
        '@phan-write-only' => '',
    ];

    private function parsePhanProperty(int $i, string $line): void
    {
        if (!$this->checkCompatible('@phan-property', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for magic properties is enabled.
        if (!Config::getValue('read_magic_property_annotations')) {
            return;
        }
        $magic_property = $this->magicPropertyFromCommentLine($line, $i);
        if ($magic_property !== null) {
            $this->phan_overrides['property'][] = $magic_property;
        }
    }

    private function parsePhanMethod(int $i, string $line): void
    {
        if (!$this->checkCompatible('@phan-method', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for magic methods is enabled.
        if (!Config::getValue('read_magic_method_annotations')) {
            return;
        }
        $magic_method = $this->magicMethodFromCommentLine($line, $i);
        if ($magic_method !== null) {
            $this->phan_overrides['method'][] = $magic_method;
        }
    }

    /**
     * @var array{0:string,1:string,2:string}|null [the project root directory, the file of the context, Config::projectPath() of that file]
     * of the most recent call to guessActualLineLocation(), which is called for every (at)param and (at)return line.
     */
    private static $last_project_path = null;

    /**
     * Returns the entry of FileCache for the file of the comment, if it is cached.
     */
    private function getFileCacheEntry(): ?FileCacheEntry
    {
        $file = $this->context->getFile();
        $root = Config::getProjectRootDirectory();
        $last_project_path = self::$last_project_path;
        if ($last_project_path !== null && $last_project_path[1] === $file && $last_project_path[0] === $root) {
            $path = $last_project_path[2];
        } else {
            // Config::projectPath() is a pure function of the project root directory and the file
            $path = Config::projectPath($file);
            self::$last_project_path = [$root, $file, $path];
        }
        return FileCache::getEntry($path);
    }

    private function guessActualLineLocation(int $i): int
    {
        $entry = $this->getFileCacheEntry();
        $declaration_lineno = $this->lineno;
        if (!$entry) {
            return $declaration_lineno;
        }
        // $lineno_search <= $declaration_lineno
        $lineno_search = $declaration_lineno - ($this->comment_lines_count - $i - 1);
        // Search up to 10 lines before $lineno_search
        $lineno_stop = \max(1, $lineno_search - 9);
        // The lines of the doc comment contain no "\n", so the checks below have the same result for the lines of getLines(),
        // which end in "\n", and for these lines, which are much faster to compute.
        $lines_array = $entry->getLinesWithoutNewlines();

        $line = $this->lines[$i];
        $trimmed_line = \trim($line);
        for ($check_lineno = $lineno_search; $check_lineno >= $lineno_stop; $check_lineno--) {
            $cur_line = $lines_array[$check_lineno];
            if (str_contains($cur_line, $line)) {
                // Better heuristic: Lines in the middle of phpdoc are guaranteed to be complete, including a few newlines at the end.
                $j = $i - ($lineno_search - $check_lineno);
                if ($j > 0 && $j < $this->comment_lines_count - 1) {
                    if ($trimmed_line !== \trim($cur_line)) {
                        continue;
                    }
                }
                return $check_lineno;
            }
        }
        // We couldn't identify the line;
        return $declaration_lineno;
    }

    /**
     * Returns the line of line $i of the comment in the file, for a custom annotation (CustomAnnotation::getLineno()).
     *
     * guessActualLineLocation() searches upwards from a line that is below line $i by the distance between the end of
     * the comment and the declaration, so it returns the line of a later line of the comment with the same text
     * if there is one within that distance. This also derives the line from the line of the last line of the comment
     * (found by guessActualLineLocation(), as no line of the comment follows it), and uses that when it differs and
     * has the text of line $i. That is only possible when the lines of the comment are the lines of the source:
     * reduceMultiline() did not merge any. The lines of the annotations of Phan are not changed.
     */
    private function guessCustomAnnotationLineLocation(int $i): int
    {
        $guess = $this->guessActualLineLocation($i);
        $last_index = $this->comment_lines_count - 1;
        if ($i === $last_index || \substr_count($this->comment, "\n") !== $last_index) {
            return $guess;
        }
        $this->last_line_location ??= $this->guessActualLineLocation($last_index);
        $lineno = $this->last_line_location - ($last_index - $i);
        if ($lineno === $guess || $lineno < 1) {
            return $guess;
        }
        $entry = $this->getFileCacheEntry();
        if (!$entry) {
            return $guess;
        }
        $source_line = $entry->getLinesWithoutNewlines()[$lineno] ?? null;
        if ($source_line === null || \trim($source_line) !== \trim($this->lines[$i])) {
            return $guess;
        }
        return $lineno;
    }

    /**
     * Find the line number of line $i of the doc comment with lines $lines
     *
     * @param list<string> $lines
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function findLineNumberOfCommentForElement(AddressableElementInterface $element, array $lines, int $i): int
    {
        $context = $element->getContext();

        $entry = FileCache::getOrReadEntry(Config::projectPath($context->getFile()));
        $declaration_lineno = $context->getLineNumberStart();
        $lines_array = $entry->getLines();
        $count = \count($lines);
        $lineno_search = $declaration_lineno - ($count - $i - 1);
        $lineno_stop = \max(1, $lineno_search - 9);
        $line = $lines[$i];
        $trimmed_line = \trim($lines[$i]);
        for ($check_lineno = $lineno_search; $check_lineno >= $lineno_stop; $check_lineno--) {
            $cur_line = $lines_array[$check_lineno];
            if (str_contains($cur_line, $line)) {
                // Better heuristic: Lines in the middle of phpdoc are guaranteed to be complete, including a few newlines at the end.
                $j = $i - ($lineno_search - $check_lineno);
                if ($j > 0 && $j < $count - 1) {
                    if ($trimmed_line !== \trim($cur_line)) {
                        continue;
                    }
                }
                return $check_lineno;
            }
        }
        return $declaration_lineno;
    }

    /**
     * @param list<int> $valid_types
     * @suppress PhanAccessClassConstantInternal
     */
    private function checkCompatible(string $param_name, array $valid_types, int $i): bool
    {
        if (\in_array($this->comment_type, $valid_types, true) || $this->comment_type === Comment::ON_ANY) {
            return true;
        }
        $this->emitInvalidCommentForDeclarationType(
            $param_name,
            $this->guessActualLineLocation($i)
        );
        return false;
    }

    private function emitInvalidCommentForDeclarationType(
        string $annotation_type,
        int $issue_lineno
    ): void {
        $this->emitIssue(
            Issue::InvalidCommentForDeclarationType,
            $issue_lineno,
            $annotation_type,
            Comment::NAME_FOR_TYPE[$this->comment_type]
        );
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return TemplateType|null
     * A generic type identifier or null if a valid type identifier
     * wasn't found.
     */
    /**
     * @return ?array{0:string,1:int,2:?string}
     */
    private static function templateTypeFromCommentLine(
        string $line
    ): ?array {
        // Backslashes or nested templates wouldn't make sense, so use WORD_REGEX.
        if (\preg_match('/@(?:phan-|psalm-|phpstan-)?template(?:-(?P<variance>co|contra)variant)?\s+(?P<identifier>' . self::WORD_REGEX . ')(?:\s+of\s+(?P<constraint>' . UnionType::union_type_regex . '))?/i', $line, $match)) {
            $constraint = $match['constraint'] ?? null;
            $variance = TemplateType::VARIANCE_INVARIANT;
            $variance_keyword = strtolower($match['variance'] ?? '');
            if ($variance_keyword === 'co') {
                $variance = TemplateType::VARIANCE_COVARIANT;
            } elseif ($variance_keyword === 'contra') {
                $variance = TemplateType::VARIANCE_CONTRAVARIANT;
            }
            $identifier = $match['identifier'] ?? $match[1] ?? '';
            return [$identifier, $variance, $constraint];
        }

        return null;
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return Option<Type>
     * An optional type overriding the extended type of the class
     */
    private function inheritsFromCommentLine(
        string $line
    ): Option {
        $type = $this->typeFromCommentLine($line);
        if ($type) {
            return new Some($type);
        }
        return None::instance();
    }

    /**
     * This regex contains a single pattern, which matches a valid PHP identifier.
     * (e.g. for variable names, magic property names, etc.)
     * This does not allow backslashes.
     */
    public const WORD_REGEX = '([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';

    /**
     * This regex contains a single pattern, which matches a reasonable Phan issue name
     * (e.g. for variable names, magic property names, etc.)
     *
     * E.g. "PhanPluginSomeIssueName" (preferred), "PhanPlugin_some_issue_name", and "PhanPlugin-some-issue-name".
     *
     * Note that Phan doesn't forbid using names not matching this regex in the Issue constructor at the time of writing.
     */
    public const ISSUE_REGEX = '([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(-[a-zA-Z0-9_\x7f-\xff]+)*)';

    /**
     * @internal
     */
    public const SUPPRESS_ISSUE_LIST = '(' . self::ISSUE_REGEX . '(,\s*' . self::ISSUE_REGEX . ')*)';

    /**
     * @internal
     */
    public const PHAN_SUPPRESS_REGEX = '/@(?:phan-)?suppress\s+' . self::SUPPRESS_ISSUE_LIST . '/';

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return list<string>
     * 0 or more issue names to suppress
     */
    private static function suppressIssuesFromCommentLine(
        string $line
    ): array {
        if (\preg_match(self::PHAN_SUPPRESS_REGEX, $line, $match)) {
            return \array_map('trim', \explode(',', $match[1]));
        }

        return [];
    }

    /** @internal */
    public const MAGIC_PARAM_REGEX = '/^(' . UnionType::union_type_regex . ')?\s*(?:(\.\.\.)\s*)?(?:\$' . self::WORD_REGEX . ')?((?:\s*=.*)?)$/';

    /**
     * Parses a magic method based on https://phpdoc.org/docs/latest/references/phpdoc/tags/method.html
     * @return ?Parameter - if null, the phpdoc magic method was invalid.
     */
    private function magicParamFromMagicMethodParamString(
        string $param_string,
        int $param_index,
        int $comment_line_offset
    ): ?Parameter {
        $param_string = \trim($param_string);
        // Don't support trailing commas, or omitted params. Provide at least one of [type] or [parameter]
        if ($param_string === '') {
            return null;
        }
        // Parse an entry for [type] [parameter] - Assume both of those are optional.
        // https://github.com/phpDocumentor/phpDocumentor2/pull/1271/files - phpdoc allows passing an default value.
        // Phan allows `=.*`, to indicate that a parameter is optional
        // TODO: in another PR, check that optional parameters aren't before required parameters.
        if (\preg_match(self::MAGIC_PARAM_REGEX, $param_string, $param_match)) {
            // Note: a magic method parameter can be variadic, but it can't be pass-by-reference? (No support in __call)
            $union_type_string = $param_match[1];
            $union_type = UnionType::fromStringInContext(
                $union_type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
            $is_variadic = $param_match[15] === '...';
            $default_str = $param_match[17];
            $has_default_value = $default_str !== '';
            if ($has_default_value) {
                $default_value_representation = \trim(\explode('=', $default_str, 2)[1]);
                if (\strcasecmp($default_value_representation, 'null') === 0) {
                    $union_type = $union_type->nullableClone();
                }
            } else {
                $default_value_representation = null;
            }
            $var_name = $param_match[16];
            if ($var_name === '') {
                // placeholder names are p1, p2, ...
                $var_name = 'p' . ($param_index + 1);
            }
            return new Parameter($var_name, $union_type, $this->guessActualLineLocation($comment_line_offset), $is_variadic, $has_default_value, false, false, false, $default_value_representation);
        }
        return null;
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return ?Method
     * magic method with the parameter types, return types, and name.
     */
    private function magicMethodFromCommentLine(
        string $line,
        int $comment_line_offset
    ): ?Method {
        // https://phpdoc.org/docs/latest/references/phpdoc/tags/method.html
        // > Going to assume "static" is a magic keyword, based on https://github.com/phpDocumentor/phpDocumentor2/issues/822
        // > TODO: forbid in trait?
        // TODO: finish writing the regex.
        // Syntax:
        //    @method [return type] [&][name]([[type] [parameter]<, ...>]) [<description>]
        //    Assumes the parameters end at the first ")" after "("
        //    As an exception, allows one level of matching brackets
        //    to support old style arrays such as $x = array(), $x = array(2) (Default values are ignored)
        if (\preg_match('/@(?:phan-)?method(?:\s+(static))?(?:(?:\s+(' . UnionType::union_type_regex_or_this . '))?)\s+(&)?' . self::WORD_REGEX . '\s*\(((?:[^()]|\([()]*\))*)\)\s*(.*)/', $line, $match)) {
            $is_static = $match[1] === 'static';
            $is_returns_ref = ($match[22] ?? '') === '&';
            $return_union_type_string = $match[2];
            if ($return_union_type_string !== '') {
                $return_union_type =
                    UnionType::fromStringInContext(
                        $return_union_type_string,
                        $this->context,
                        Type::FROM_PHPDOC,
                        $this->code_base
                    );
            } else {
                // From https://phpdoc.org/docs/latest/references/phpdoc/tags/method.html
                // > When the intended method does not have a return value then the return type MAY be omitted; in which case 'void' is implied.
                if ($is_static) {
                    // > `static` on its own would mean that the method returns an instance of the child class which the method is called on.
                    $return_union_type = StaticType::instance(false)->asPHPDocUnionType();
                    $is_static = false;
                } else {
                    $return_union_type = VoidType::instance(false)->asPHPDocUnionType();
                }
            }
            $method_name = $match[23];

            $arg_list = \trim($match[24]);
            $comment_params = [];
            // Special check if param list has 0 params.
            if ($arg_list !== '') {
                $params_strings = self::extractMethodParts($arg_list);
                foreach ($params_strings as $i => $param_string) {
                    $param = $this->magicParamFromMagicMethodParamString($param_string, $i, $comment_line_offset);
                    if ($param === null) {
                        $this->emitIssue(
                            Issue::UnextractableAnnotationPart,
                            $this->guessActualLineLocation($comment_line_offset),
                            \trim($line),
                            $param_string
                        );
                        return null;
                    }
                    $comment_params[] = $param;
                }
            }

            return new Method(
                $method_name,
                $return_union_type,
                $comment_params,
                $is_static,
                $is_returns_ref,
                $this->guessActualLineLocation($comment_line_offset)
            );
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($comment_line_offset),
                \trim($line)
            );
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function extractMethodParts(string $type_string): array
    {
        $parts = [];
        foreach (\explode(',', $type_string) as $part) {
            $parts[] = \trim($part);
        }

        if (\count($parts) <= 1) {
            return $parts;
        }
        if (!\preg_match('/[<({]/', $type_string)) {
            return $parts;
        }
        return self::mergeMethodParts($parts);
    }

    /**
     * @param string[] $parts (already trimmed)
     * @return string[]
     * @see Type::extractTemplateParameterTypeNameList() (Similar method)
     */
    private static function mergeMethodParts(array $parts): array
    {
        $prev_parts = [];
        $delta = 0;
        $results = [];
        foreach ($parts as $part) {
            if (\count($prev_parts) > 0) {
                $prev_parts[] = $part;
                $delta += \substr_count($part, '<') + \substr_count($part, '(') + \substr_count($part, '{') - \substr_count($part, '>') - \substr_count($part, ')') - \substr_count($part, '}');
                if ($delta <= 0) {
                    if ($delta === 0) {
                        $results[] = \implode(',', $prev_parts);
                    }  // ignore unparsable data such as "<T,T2>>"
                    $prev_parts = [];
                    $delta = 0;
                    continue;
                }
                continue;
            }
            $bracket_count = \substr_count($part, '<') + \substr_count($part, '(') + \substr_count($part, '{');
            if ($bracket_count === 0) {
                $results[] = $part;
                continue;
            }
            $delta = $bracket_count - \substr_count($part, '>') - \substr_count($part, ')') - \substr_count($part, '}');
            if ($delta === 0) {
                $results[] = $part;
            } elseif ($delta > 0) {
                $prev_parts[] = $part;
            }  // otherwise ignore unparsable data such as ">" (should be impossible)
        }
        return $results;
    }

    /**
     * @param string $line
     * An individual line of a comment
     * Analysis will handle (at)property-read and (at)property-write differently from
     * (at)property.
     *
     * @return Property|null
     * magic property with the union type.
     */
    private function magicPropertyFromCommentLine(
        string $line,
        int $i
    ): ?Property {
        // Note that the type of a property can be left out (@property $myVar) - This is equivalent to @property mixed $myVar
        // TODO: properly handle duplicates...
        if (\preg_match('/@(?:phan-)?(property|property-read|property-write)(?:\s+(' . UnionType::union_type_regex . '))?(?:\s+(?:\\$' . self::WORD_REGEX . '))/', $line, $match)) {
            $category = $match[1];
            if ($category === 'property-read') {
                $flags = Flags::IS_READ_ONLY;
            } elseif ($category === 'property-write') {
                $flags = Flags::IS_WRITE_ONLY;
            } else {
                $flags = 0;
            }
            $type = $match[2] ?? '';

            $property_name = $match[16] ?? '';
            if ($property_name === '') {
                return null;
            }

            // If the type looks like a property name, make it an
            // empty type so that other stuff can match it.
            $union_type =
                UnionType::fromStringInContext(
                    $type,
                    $this->context,
                    Type::FROM_PHPDOC,
                    $this->code_base
                );

            return new Property(
                $property_name,
                $union_type,
                $this->guessActualLineLocation($i),
                $flags
            );
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                \trim($line)
            );
        }

        return null;
    }

    /**
     * The context in which the comment line appears
     *
     * @param string $line
     * An individual line of a comment
     *
     * @return Option<Type>
     * A class/interface to use as a context for a closure.
     * (Phan expects a ClassScope to have exactly one type)
     */
    private function getPhanClosureScopeFromCommentLine(
        string $line,
        int $comment_line_offset
    ): Option {
        // https://secure.php.net/manual/en/closure.bindto.php
        // There wasn't anything in the phpdoc standard to indicate the class to which
        // a Closure would be bound with bind() or bindTo(), so using a custom tag.
        //
        // TODO: Also add a version which forbids using $this in the closure?

        $type = $this->typeFromCommentLine($line);
        if ($type !== null) {
            return new Some($type);
        }
        $this->emitIssue(
            Issue::UnextractableAnnotation,
            $this->guessActualLineLocation($comment_line_offset),
            \trim($line)
        );
        return None::instance();
    }

    /**
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $issue_lineno
     * The line number where the issue was found
     *
     * @param int|string|FQSEN|UnionType|Type ...$parameters
     * Template parameters for the issue's error message
     */
    protected function emitIssue(
        string $issue_type,
        int $issue_lineno,
        FQSEN|Type|UnionType|int|string ...$parameters
    ): void {
        $this->issues[] = [
            $issue_type,
            $issue_lineno,
            $parameters,
            null,
        ];
    }

    /**
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $issue_lineno
     * The line number where the issue was found
     *
     * @param list<int|string|FQSEN|UnionType|Type> $parameters
     * Template parameters for the issue's error message
     *
     * @param ?Suggestion $suggestion
     */
    protected function emitIssueWithSuggestion(
        string $issue_type,
        int $issue_lineno,
        array $parameters,
        ?Suggestion $suggestion = null
    ): void {
        $this->issues[] = [
            $issue_type,
            $issue_lineno,
            $parameters,
            $suggestion
        ];
    }

    protected function emitDeferredIssues(): void
    {
        foreach ($this->issues as [$issue_type, $issue_lineno, $parameters, $suggestion]) {
            if (\array_key_exists($issue_type, $this->suppress_issue_set)) {
                // Record that this suppression has been used.
                $this->suppress_issue_set[$issue_type] = 1;
                continue;
            }
            Issue::maybeEmitWithParameters(
                $this->code_base,
                $this->context,
                $issue_type,
                $issue_lineno,
                $parameters,
                $suggestion
            );
        }
        $this->issues = [];
    }

    /**
     * Matches the tag of an annotation that has a type, at the start of the text after an `(at)`.
     */
    private const MULTILINE_TAG_REGEX = '/^(?:phan-|(?i:phpstan-|psalm-))?(?:param|var|return|real-return|property(?:-read|-write)?|assert|(?i:type)|implements|extends|inherits|use)\s/';

    /**
     * Joins the lines of a type that is written on several lines, so that the annotation can be parsed as one line:
     *
     *     (at)return array<string, array{
     *          id: int,
     *          name: string,
     *      }>
     *
     * A type goes on in the next line while a bracket is open at the end of a line,
     * whether the line ends with the bracket or with something else (`array{id: int,` and then `name: string}`).
     */
    private static function reduceMultiline(string $comment): string
    {
        $comment = \str_replace("\r", '', $comment);
        // The closure below only changes an annotation that has an opening bracket and more than one line.
        if (!str_contains($comment, "\n") || \strpbrk($comment, '<{[(') === false) {
            return $comment;
        }
        // An `@` in a key in quotes does not start an annotation: `array{'user@example.com': string}`
        $has_quoted_at = (str_contains($comment, "'") || str_contains($comment, '"')) && \preg_match('/([\'"])[^\'"\n@]*@[^\'"\n]*\1\s*\??\s*:/', $comment) > 0;
        if ($has_quoted_at) {
            $comment = \preg_replace_callback(
                '/([\'"])[^\'"\n@]*@[^\'"\n]*\1(?=\s*\??\s*:)/',
                /** @param array{0:string} $match */
                static function (array $match): string {
                    return \str_replace('@', "\0", $match[0]);
                },
                $comment
            ) ?? $comment;
        }
        $result = \implode('@', \array_map(
            static function (string $annotation): string {
                if (!str_contains($annotation, "\n") || !\preg_match(self::MULTILINE_TAG_REGEX, $annotation, $match)) {
                    return $annotation;
                }
                $buffer = self::joinLinesOfType($annotation, \strlen($match[0]));
                if ($buffer === null) {
                    return $annotation;
                }
                // Keep the number of lines, so that the lines of the other annotations can still be found in the file
                // (guessActualLineLocation() counts from the end of the comment).
                $missing_lines = \substr_count($annotation, "\n") - \substr_count($buffer, "\n");
                if ($missing_lines <= 0) {
                    return $buffer;
                }
                // (Before the last line break, so that the start of the next annotation (` * `) stays on the line of that annotation)
                $pos = \strrpos($buffer, "\n");
                return $pos === false
                    ? $buffer . \str_repeat("\n", $missing_lines)
                    : \substr_replace($buffer, \str_repeat("\n", $missing_lines), $pos, 0);
            },
            \explode('@', $comment)
        ));
        return $has_quoted_at ? \str_replace("\0", '@', $result) : $result;
    }

    /**
     * @param int $i the offset of whitespace after a part of a type of which no bracket is open
     * @param string $buffer the type so far
     * @return bool whether the type goes on after the whitespace: `array{...} | null`, `Closure(...): int`
     */
    private static function isTypeContinuedAt(string $annotation, int $i, string $buffer): bool
    {
        $last = \substr(\rtrim($buffer), -1);
        if ($last === '|' || $last === '&' || $last === ':') {
            return true;
        }
        // The next character that is not whitespace or the `*` at the start of a line of the comment
        if (!\preg_match('/\G(?:[ \t]+|\n[ \t]*\*?(?!\/))*+(.)/', $annotation, $match, 0, $i)) {
            return false;
        }
        // (`) : int` of a closure type, not the colon of a description)
        return $match[1] === '|' || $match[1] === '&' || ($match[1] === ':' && $last === ')');
    }

    /**
     * @param int $offset the offset of the type in $annotation
     * @return ?string $annotation with the line breaks inside of brackets replaced,
     * or null if the first line of the type has no bracket that is still open at its end, or if the brackets do not match.
     */
    private static function joinLinesOfType(string $annotation, int $offset): ?string
    {
        $length = \strlen($annotation);
        $depth = 0;
        $quote = '';
        $has_joined = false;
        $buffer = \substr($annotation, 0, $offset);
        for ($i = $offset; $i < $length; $i++) {
            $char = $annotation[$i];
            if ($quote !== '') {
                // A literal string in a type: `'('` is not a bracket.
                if ($char === "\n") {
                    return null;
                }
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $char . $annotation[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                $buffer .= $char;
                continue;
            }
            switch ($char) {
                case '{':
                case '<':
                case '[':
                case '(':
                    $depth++;
                    break;
                case '}':
                case '>':
                case ']':
                case ')':
                    if ($char === '>' && $i > 0 && ($annotation[$i - 1] === '-' || $annotation[$i - 1] === '=')) {
                        // `->` and `=>` in a description
                        break;
                    }
                    if (--$depth < 0) {
                        return null;
                    }
                    if ($has_joined) {
                        // `name: string,` and then `}`
                        $buffer = \rtrim($buffer, ' ,');
                    }
                    break;
                case "'":
                case '"':
                    // (At the top level only where a type is expected: `array{...}|'a b'`, not the apostrophe of a description)
                    // (Also for `|?'a b'`)
                    // (And at the start of the type: `'&'|array{`)
                    if ($depth > 0 || \strlen(\rtrim($buffer, " \t?")) <= $offset || \in_array(\substr(\rtrim($buffer, " \t?"), -1), ['|', '&'], true)) {
                        $quote = $char;
                    }
                    break;
                case '$':
                    if ($depth === 0 && $has_joined && \preg_match('/\G\$this(?![a-zA-Z0-9_\x7f-\xff])/', $annotation, $this_match, 0, $i)
                        && (\in_array(\substr(\rtrim($buffer), -1), ['|', '&'], true) || \preg_match('/\G\$this\s*[|&]/', $annotation, $this_match, 0, $i))) {
                        // The type `$this` in a union: `array{...}|$this|array{...}`
                        break;
                    }
                    if ($depth === 0) {
                        // The type ended: on its first line, or before the variable of `@param array{...} $x`.
                        return $has_joined ? $buffer . \substr($annotation, $i) : null;
                    }
                    break;
                case ' ':
                case "\t":
                    if ($depth === 0 && $has_joined && !self::isTypeContinuedAt($annotation, $i, $buffer)) {
                        // The type ended, and the rest is a description: `} Must be > 0`, `} A record (it's useful)`.
                        return $buffer . \substr($annotation, $i);
                    }
                    break;
                case "\n":
                    if ($depth === 0) {
                        if (!$has_joined) {
                            // No bracket is open at the end of the first line.
                            return null;
                        }
                        if (!self::isTypeContinuedAt($annotation, $i, $buffer)) {
                            return $buffer . \substr($annotation, $i);
                        }
                        // `}` and then `|array{` on the next line
                    }
                    // Skip the ` * ` of the next line.
                    while ($i + 1 < $length && ($annotation[$i + 1] === ' ' || $annotation[$i + 1] === "\t")) {
                        $i++;
                    }
                    if ($i + 1 < $length && $annotation[$i + 1] === '*' && ($annotation[$i + 2] ?? '') !== '/') {
                        $i++;
                        while ($i + 1 < $length && ($annotation[$i + 1] === ' ' || $annotation[$i + 1] === "\t")) {
                            $i++;
                        }
                    }
                    $has_joined = true;
                    $buffer = \rtrim($buffer, ' ');
                    // (No space after an opening bracket)
                    if ($buffer !== '' && !\in_array($buffer[\strlen($buffer) - 1], ['{', '<', '[', '('], true)) {
                        $buffer .= ' ';
                    }
                    continue 2;
            }
            $buffer .= $char;
        }
        return $has_joined && $depth === 0 && $quote === '' ? $buffer : null;
    }
}
