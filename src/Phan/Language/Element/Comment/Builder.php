<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Flags;
use Phan\Language\FQSEN;
use Phan\Language\Scope\TemplateScope;
use Phan\Language\Type;
use Phan\Language\Type\GenericMultiType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Library\None;
use Phan\Library\Option;
use Phan\Library\Some;
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
        'psalm-param' => 'param',
        'psalm-return' => 'return',
        'psalm-var' => 'var',
        'psalm-require-extends' => 'require-extends',
        'psalm-require-implements' => 'require-implements',
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
    /** @var UnionType the union type of the set of (at)throws annotations */
    public $throw_union_type;
    /** @var array<string,Assertion> assertions about each parameter */
    public $param_assertion_map = [];

    /** @var list<Type> */
    private $required_extends_types = [];

    /** @var list<Type> */
    private $required_implements_types = [];

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
        '/@(?:phan-|psalm-)?(param|var)\b\s*(' . UnionType::union_type_regex . ')?(?:\s*(\.\.\.)?\s*&?(?:\\$' . self::WORD_REGEX . '))?/';

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
    public const RETURN_COMMENT_REGEX = '/@(?:phan-|psalm-)?(?:real-)?return\s+(&\s*)?(' . UnionType::union_type_regex_or_this . ')/';
    /** @internal */
    public const RETURN_OR_THROWS_COMMENT_REGEX = '/@(?:phan-|psalm-)?(?:real-)?(?:return|throws)\s+(&\s*)?(' . UnionType::union_type_regex_or_this . ')/';

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
            !$this->param_assertion_map
        ) {
            // Don't create an extra object if the string contained `@` but nothing of use was actually extracted.
            return NullComment::instance();
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        return new Comment(
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
        if (\preg_match('/(?:^|{)@((?i)param|deprecated|var|return|throws|throw|returns|inherits|extends|implements|use|require-extends|require-implements|suppress|unused-param|no-named-arguments|phan-[a-z0-9_-]*|psalm-(?:template(?:-(?:co|contra)variant)?|param|var|return|require-extends|require-implements)(?-i)|method|property|property-read|property-write|abstract|template(?:-(?:co|contra)variant)?|PhanClosureScope|readonly|mixin|seal-(?:methods|properties))(?:[^a-zA-Z0-9_\x7f-\xff-]|$)/D', $trimmed, $matches)) {
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
                        $this->maybeParsePhanCustomAnnotation($i, $line, $type, $case_sensitive_type);
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
        if (\preg_match('/@(phan-)?mixin\s+(\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*)\b/', $line, $matches)) {
            $type_string = $matches[2];
            $type = Type::fromStringInContext(
                $type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
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

    private function parsePhanType(int $i, string $line): void
    {
        $lineno = $this->guessActualLineLocation($i);
        if (!\preg_match(self::PHAN_TYPE_ALIAS_REGEX, $line, $matches)) {
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
                    if (isset($this->template_type_list[$template_type->getName()])) {
                        $this->emitIssue(
                            Issue::TemplateTypeDuplicate,
                            $this->guessActualLineLocation($i),
                            (string)$template_type
                        );
                    }
                    $this->template_type_list[$template_type->getName()] = $template_type;
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
            $type = $this->implementsFromCommentLine($line);
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
            $type = $this->implementsFromCommentLine($line);
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
            $type = $this->useFromCommentLine($line);
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
        $type = $this->requireFromCommentLine($line, 'require-extends');
        if ($type !== null) {
            $this->required_extends_types[] = $type;
        }
    }

    private function maybeParseRequireImplements(int $i, string $line): void
    {
        if (!$this->checkCompatible('@require-implements', [Comment::ON_CLASS], $i)) {
            return;
        }
        $type = $this->requireFromCommentLine($line, 'require-implements');
        if ($type !== null) {
            $this->required_implements_types[] = $type;
        }
    }

    private function maybeParsePhanUse(int $i, string $line): void
    {
        if (!$this->checkCompatible('@phan-use', [Comment::ON_CLASS], $i)) {
            return;
        }
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $type = $this->useFromCommentLine($line);
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

    private function requireFromCommentLine(string $line, string $tag): ?Type
    {
        $pattern = '/@(?:psalm-)?' . \preg_quote($tag, '/') . '\s+(' . Type::type_regex . ')/';
        if (\preg_match($pattern, $line, $match)) {
            $type_string = $match[1];
            return Type::fromStringInContext(
                $type_string,
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
        $new_type = $this->returnOrThrowsTypeFromCommentLine($line, $i);
        if ($return_comment) {
            $return_comment->setType($return_comment->getType()->withUnionType($new_type));
        } else {
            $this->return_comment = new ReturnComment($new_type, $this->guessActualLineLocation($i));
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

    private function maybeParsePhanCustomAnnotation(int $i, string $line, string $type, string $case_sensitive_type): void
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
                    $this->phan_overrides['return'] = new ReturnComment($this->returnOrThrowsTypeFromCommentLine($line, $i), $this->guessActualLineLocation($i));
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
            default:
                $this->emitIssueWithSuggestion(
                    Issue::MisspelledAnnotation,
                    $this->guessActualLineLocation($i),
                    [
                        '@' . $case_sensitive_type,
                        "The annotations that this version of Phan supports can be seen by running 'phan --help-annotations' or by visiting https://github.com/phan/phan/wiki/Annotating-Your-Source-Code",
                    ],
                    self::generateSuggestionForMisspelledAnnotation($case_sensitive_type)
                );
                return;
        }
    }

    private static function generateSuggestionForMisspelledAnnotation(string $annotation): ?Suggestion
    {
        $suggestions = IssueFixSuggester::getSuggestionsForStringSet('@' . $annotation, self::SUPPORTED_ANNOTATIONS);
        if (!$suggestions) {
            return null;
        }
        return Suggestion::fromString('Did you mean ' . \implode(' or ', \array_keys($suggestions)));
    }

    /**
     * Maps supported annotations starting with phan- to the empty string or a description
     */
    public const SUPPORTED_ANNOTATIONS = [
        '@phan-assert' => '',
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

    private function guessActualLineLocation(int $i): int
    {
        $path = Config::projectPath($this->context->getFile());
        $entry = FileCache::getEntry($path);
        $declaration_lineno = $this->lineno;
        if (!$entry) {
            return $declaration_lineno;
        }
        // $lineno_search <= $declaration_lineno
        $lineno_search = $declaration_lineno - ($this->comment_lines_count - $i - 1);
        // Search up to 10 lines before $lineno_search
        $lineno_stop = \max(1, $lineno_search - 9);
        $lines_array = $entry->getLines();

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
        if (\preg_match('/@(?:phan-|psalm-)?template(?:-(?P<variance>co|contra)variant)?\s+(?P<identifier>' . self::WORD_REGEX . ')(?:\s+of\s+(?P<constraint>' . UnionType::union_type_regex . '))?/i', $line, $match)) {
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
        $match = [];
        if (\preg_match('/@(?:phan-)?(?:inherits|extends)\s+(' . Type::type_regex . ')/', $line, $match)) {
            $type_string = $match[1];

            $type = new Some(Type::fromStringInContext(
                $type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            ));

            return $type;
        }

        return None::instance();
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return ?Type
     * A type for an implemented interface with template parameters, or null if not found
     */
    private function implementsFromCommentLine(
        string $line
    ): ?Type {
        $match = [];
        if (\preg_match('/@(?:phan-)?implements\s+(' . Type::type_regex . ')/', $line, $match)) {
            $type_string = $match[1];

            return Type::fromStringInContext(
                $type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
        }

        return null;
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return ?Type
     * A type for a used trait with template parameters, or null if not found
     */
    private function useFromCommentLine(
        string $line
    ): ?Type {
        $match = [];
        if (\preg_match('/@(?:phan-)?use\s+(' . Type::type_regex . ')/', $line, $match)) {
            $type_string = $match[1];

            return Type::fromStringInContext(
                $type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
        }

        return null;
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
        //    @method [return type] [name]([[type] [parameter]<, ...>]) [<description>]
        //    Assumes the parameters end at the first ")" after "("
        //    As an exception, allows one level of matching brackets
        //    to support old style arrays such as $x = array(), $x = array(2) (Default values are ignored)
        if (\preg_match('/@(?:phan-)?method(?:\s+(static))?(?:(?:\s+(' . UnionType::union_type_regex_or_this . '))?)\s+' . self::WORD_REGEX . '\s*\(((?:[^()]|\([()]*\))*)\)\s*(.*)/', $line, $match)) {
            $is_static = $match[1] === 'static';
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
            $method_name = $match[22];

            $arg_list = \trim($match[23]);
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
        $closure_scope_union_type_string = '';

        // https://secure.php.net/manual/en/closure.bindto.php
        // There wasn't anything in the phpdoc standard to indicate the class to which
        // a Closure would be bound with bind() or bindTo(), so using a custom tag.
        //
        // TODO: Also add a version which forbids using $this in the closure?
        if (\preg_match('/@(PhanClosureScope|phan-closure-scope)\s+(' . Type::type_regex . ')/', $line, $match)) {
            $closure_scope_union_type_string = $match[2];
        }

        if ($closure_scope_union_type_string !== '') {
            return new Some(Type::fromStringInContext(
                $closure_scope_union_type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            ));
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

    private static function reduceMultiline(string $comment): string
    {
        return \implode('@', \array_map(
            static function (string $annotation): string {
                if (!str_contains($annotation, "\n")
                    || !\preg_match('/^((?:(?:phan-)?(?:param|var|return|real-return|property(?:-read|-write)?|assert|type|implements|extends|inherits|use))\s[^$\n]+[\[(<{])\n/', $annotation, $match)
                ) {
                    return $annotation;
                }

                $buffer = $match[1];
                $remaining = \substr($annotation, \strlen($match[0]));
                $level = 1;

                while ($remaining !== '') {
                    if (!\preg_match('/^[^\[(<{\])>}]*([\[(<{\])>}])/', $remaining, $match)) {
                        $buffer .= $remaining;
                        break;
                    }

                    if (\in_array($match[1], ['{', '<', '[', '('], true)) {
                        $level++;
                        $buffer .= $level
                            ? \ltrim(\preg_replace('/\n\s+\*\s+/', ' ', "\n" . $match[0]))
                            : $match[0];
                        $remaining = \substr($remaining, \strlen($match[0]));
                        continue;
                    }

                    if ((--$level) < 0) {
                        return $annotation;
                    }

                    $inner = \substr($match[0], 0, -1);
                    $inner = \rtrim(\preg_replace('/\n\s+\*\s+/', ' ', "\n" . $inner), "\n ,");
                    $buffer .= \ltrim($inner) . $match[1];
                    $remaining = \substr($remaining, \strlen($match[0]));
                }

                if ($level !== 0) {
                    return $annotation;
                }

                return $buffer;
            },
            \explode('@', \str_replace("\r", '', $comment))
        ));
    }
}
