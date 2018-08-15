<?php
declare(strict_types=1);
namespace Phan\Language\Element\Comment;

use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Library\FileCache;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\Element\Comment;
use Phan\Language\Element\Comment\Parameter as CommentParameter;
use Phan\Language\Element\Comment\Method as CommentMethod;
use Phan\Language\Element\Flags;
use Phan\Language\Type;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Library\Option;
use Phan\Library\None;
use Phan\Library\Some;

/**
 * This constructs comments from doc comments (or other comment types).
 *
 * @internal - For use only by Comment.php
 */
final class Builder
{
    /** @var array<int,string> */
    public $lines;
    /** @var int */
    public $comment_lines_count;
    /** @var CodeBase */
    public $code_base;
    /** @var Context */
    public $context;
    /** @var int */
    public $lineno;
    /** @var int */
    public $comment_type;
    /** @var array<int,Parameter> */
    public $variable_list = [];
    /** @var array<int,Parameter> */
    public $parameter_list = [];
    /** @var array<int,TemplateType> */
    public $template_type_list = [];
    /** @var Option<Type> */
    public $inherited_type;
    /** @var UnionType */
    public $return_union_type;
    /**
     * @var array<int,string>
     * @suppress PhanReadOnlyPublicProperty FIXME: array_push doesn't count as a write-reference
     */
    public $suppress_issue_list = [];
    /** @var array<int,Parameter> */
    public $magic_property_list = [];
    /** @var array<int,Method> */
    public $magic_method_list = [];
    /** @var Option */
    public $closure_scope;
    /** @var int */
    public $comment_flags = 0;
    /** @var array<string,mixed> */
    public $phan_overrides = [];
    /** @var UnionType */
    public $throw_union_type;

    public function __construct(
        string $comment,
        CodeBase $code_base,
        Context $context,
        int $lineno,
        int $comment_type
    ) {
        // @phan-suppress-next-line PhanPossiblyFalseTypeMismatchProperty
        $this->lines = \explode("\n", $comment);
        $this->comment_lines_count = \count($this->lines);
        $this->code_base = $code_base;
        $this->context = $context;
        $this->lineno = $lineno;
        $this->comment_type = $comment_type;

        $this->inherited_type = new None();
        $this->return_union_type = UnionType::empty();
        $this->closure_scope = new None();
        $this->throw_union_type = UnionType::empty();
    }

    /** @internal */
    const PARAM_COMMENT_REGEX =
        '/@(?:phan-)?(param|var)\b\s*(' . UnionType::union_type_regex . ')?(?:\s*(\.\.\.)?\s*&?(?:\\$' . self::WORD_REGEX . '))?/';

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @param bool $is_var
     * True if this is parsing a variable, false if parsing a parameter.
     *
     * @return CommentParameter
     * A CommentParameter associated with a line that has a var
     * or param reference.
     *
     * TODO: account for difference between (at)var and (at)param
     */
    private function parameterFromCommentLine(
        string $line,
        bool $is_var,
        int $i
    ) {
        $matched = \preg_match(self::PARAM_COMMENT_REGEX, $line, $match);
        // Parse https://docs.phpdoc.org/references/phpdoc/tags/param.html
        // Exceptions: Deliberately allow "&" in "@param int &$x" when documenting references.
        // Warn if there is neither a union type nor a variable
        if ($matched && (isset($match[2]) || isset($match[21]))) {
            if (!isset($match[2])) {
                return new CommentParameter('', UnionType::empty());
            }
            if (!$is_var && !isset($match[21])) {
                $this->checkParamWithoutVarName($line, $match[0], $match[2], $i);
            }
            $original_type = $match[2];

            $is_variadic = ($match[20] ?? '') === '...';

            if ($is_var && $is_variadic) {
                $variable_name = '';  // "@var int ...$x" is nonsense and invalid phpdoc.
            } else {
                $variable_name = $match[21] ?? '';
            }
            // Fix typos or non-standard phpdoc tags, according to the user's configuration.
            // Does nothing by default.
            $type = self::rewritePHPDocType($original_type);

            // If the type looks like a variable name, make it an
            // empty type so that other stuff can match it. We can't
            // just skip it or we'd mess up the parameter order.
            $union_type = null;
            if (0 !== \strpos($type, '$')) {
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
            $is_output_parameter = \stripos($line, '@phan-output-reference') !== false;

            return new CommentParameter(
                $variable_name,
                $union_type,
                $is_variadic,
                false,  // has_default_value
                $is_output_parameter
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

        return new CommentParameter('', UnionType::empty());
    }

    /** @internal */
    const RETURN_COMMENT_REGEX = '/@(?:phan-)?(?:return|throws)\s+(&\s*)?(' . UnionType::union_type_regex_or_this . '+)/';

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return UnionType
     * The declared return type
     */
    private function returnTypeFromCommentLine(
        string $line,
        int $i
    ) {
        $return_union_type_string = '';

        if (\preg_match(self::RETURN_COMMENT_REGEX, $line, $match)) {
            $return_union_type_string = $match[2];
            $raw_match = $match[0];
            $char_at_end_offset = $line[\strpos($line, $raw_match) + \strlen($raw_match)] ?? ' ';
            if (\ord($char_at_end_offset) > 32) {  // Not a control character or space
                $this->emitIssue(
                    Issue::UnextractableAnnotationSuffix,
                    $this->guessActualLineLocation($i),
                    \trim($line),
                    $return_union_type_string,
                    $char_at_end_offset
                );
            }
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                trim($line)
            );
        }
        // Not emitting any issues about failing to extract, e.g. `@return - Description of what this returns` is a valid comment.
        $return_union_type_string = self::rewritePHPDocType($return_union_type_string);

        $return_union_type = UnionType::fromStringInContext(
            $return_union_type_string,
            $this->context,
            Type::FROM_PHPDOC,
            $this->code_base
        );

        return $return_union_type;
    }

    private static function rewritePHPDocType(
        string $original_type
    ) : string {
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
    ) {

        $match_offset = \strpos($line, $raw_match);
        $end_offset = $match_offset + strlen($raw_match);

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

    public function build() : Comment
    {
        foreach ($this->lines as $i => $line) {
            if (\strpos($line, '@') === false) {
                continue;
            }
            $this->parseCommentLine($i, \trim($line));
        }

        // @phan-suppress-next-line PhanAccessMethodInternal
        return new Comment(
            $this->comment_flags,
            $this->variable_list,
            $this->parameter_list,
            $this->template_type_list,
            $this->inherited_type,
            $this->return_union_type,
            $this->suppress_issue_list,
            $this->magic_property_list,
            $this->magic_method_list,
            $this->phan_overrides,
            $this->closure_scope,  // @phan-suppress-current-line PhanPartialTypeMismatchArgument
            $this->throw_union_type
        );
    }

    /**
     * @return void
     */
    private function parseCommentLine(int $i, string $line)
    {
        // https://secure.php.net/manual/en/regexp.reference.internal-options.php
        // (?i) makes this case sensitive, (?-1) makes it case insensitive
        if (\preg_match('/@((?i)param|var|return|throws|throw|returns|inherits|suppress|phan-[a-z0-9_-]*(?-i)|method|property|template|PhanClosureScope)\b/', $line, $matches)) {
            $case_sensitive_type = $matches[1];
            $type = \strtolower($case_sensitive_type);

            if ($type === 'param') {
                $this->parseParamLine($i, $line);
            } elseif ($type === 'var') {
                $this->maybeParseVarLine($i, $line);
            } elseif ($type === 'template') {
                $this->maybeParseTemplateType($i, $line);
            } elseif ($type === 'inherits') {
                $this->maybeParseInherits($i, $line);
            } elseif ($type === 'return') {
                $this->maybeParseReturn($i, $line);
            } elseif ($type === 'returns') {
                $this->emitIssue(
                    Issue::MisspelledAnnotation,
                    $this->guessActualLineLocation($i),
                    '@returns',
                    '@return'
                );
            } elseif ($type === 'throws') {
                $this->maybeParseThrows($i, $line);
            } elseif ($type === 'throw') {
                $this->emitIssue(
                    Issue::MisspelledAnnotation,
                    $this->guessActualLineLocation($i),
                    '@throw',
                    '@throws'
                );
            } elseif ($type === 'suppress') {
                $this->maybeParseSuppress($i, $line);
            } elseif ($type === 'property') {
                $this->maybeParseProperty($i, $line);
            } elseif ($type === 'method') {
                $this->maybeParseMethod($i, $line);
            } elseif ($type === 'phanclosurescope' || $type === 'phan-closure_scope') {
                $this->maybeParsePhanClosureScope($i, $line);
            } elseif (\strpos($type, 'phan-') === 0) {
                $this->maybeParsePhanCustomAnnotation($i, $line, $type, $case_sensitive_type);
            }
        }

        if (\strpos($line, '@deprecated') !== false) {
            if (\preg_match('/@deprecated\b/', $line, $match)) {
                $this->comment_flags |= Flags::IS_DEPRECATED;
            }
        }

        if (\strpos($line, '@internal') !== false) {
            if (\preg_match('/@internal\b/', $line, $match)) {
                $this->comment_flags |= Flags::IS_NS_INTERNAL;
            }
        }

        if (\stripos($line, 'override') !== false) {
            if (\preg_match('/@([Oo]verride)\b/', $line, $match)) {
                // TODO: split class const and global const.
                $this->checkCompatible('@override', [Comment::ON_METHOD, Comment::ON_CONST], $i);
                $this->comment_flags |= Flags::IS_OVERRIDE_INTENDED;
            }
        }
    }

    private function parseParamLine(int $i, string $line)
    {
        $this->checkCompatible('@param', Comment::FUNCTION_LIKE, $i);
        $this->parameter_list[] =
            self::parameterFromCommentLine($line, false, $i);
    }

    private function maybeParseVarLine(int $i, string $line)
    {
        $this->checkCompatible('@var', Comment::HAS_VAR_ANNOTATION, $i);
        $comment_var = self::parameterFromCommentLine($line, true, $i);
        if ($comment_var->getName() !== '' || !\in_array($this->comment_type, Comment::FUNCTION_LIKE)) {
            $this->variable_list[] = $comment_var;
        }
    }

    private function maybeParseTemplateType(int $i, string $line)
    {
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $this->checkCompatible('@template', [Comment::ON_CLASS], $i);
            $template_type = $this->templateTypeFromCommentLine($line);
            if ($template_type) {
                $this->template_type_list[] = $template_type;
            }
        }
    }

    private function maybeParseInherits(int $i, string $line)
    {
        $this->checkCompatible('@inherits', [Comment::ON_CLASS], $i);
        // Make sure support for generic types is enabled
        if (Config::getValue('generic_types_enabled')) {
            $this->inherited_type = $this->inheritsFromCommentLine($line);
        }
    }

    private function maybeParseReturn(int $i, string $line)
    {
        $this->checkCompatible('@return', Comment::FUNCTION_LIKE, $i);
        $type = $this->returnTypeFromCommentLine($line, $i)->withUnionType($this->return_union_type);
        if (!$type->isEmpty()) {
            $this->return_union_type = $type;
        }
    }

    private function maybeParseThrows(int $i, string $line)
    {
        $this->checkCompatible('@throws', Comment::FUNCTION_LIKE, $i);
        $this->throw_union_type = $this->throw_union_type->withUnionType(
            $this->returnTypeFromCommentLine($line, $i)
        );
    }

    private function maybeParseSuppress(int $i, string $line)
    {
        $suppress_issue_types = $this->suppressIssuesFromCommentLine($line);
        if (count($suppress_issue_types) > 0) {
            array_push($this->suppress_issue_list, ...$suppress_issue_types);
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                trim($line)
            );
        }
    }

    private function maybeParseProperty(int $i, string $line)
    {
        $this->checkCompatible('@property', [Comment::ON_CLASS], $i);
        // Make sure support for magic properties is enabled.
        if (Config::getValue('read_magic_property_annotations')) {
            $magic_property = $this->magicPropertyFromCommentLine($line, $i);
            if ($magic_property !== null) {
                $this->magic_property_list[] = $magic_property;
            }
        }
    }

    private function maybeParseMethod(int $i, string $line)
    {
        // Make sure support for magic methods is enabled.
        if (Config::getValue('read_magic_method_annotations')) {
            $this->checkCompatible('@method', [Comment::ON_CLASS], $i);
            $magic_method = $this->magicMethodFromCommentLine($line, $i);
            if ($magic_method !== null) {
                $this->magic_method_list[] = $magic_method;
            }
        }
    }

    private function maybeParsePhanClosureScope(int $i, string $line)
    {
        // TODO: different type for closures
        $this->checkCompatible('@PhanClosureScope', Comment::FUNCTION_LIKE, $i);
        $this->closure_scope = $this->getPhanClosureScopeFromCommentLine($line, $i);
    }

    private function maybeParsePhanCustomAnnotation(int $i, string $line, string $type, string $case_sensitive_type)
    {
        if ($type === 'phan-forbid-undeclared-magic-properties') {
            $this->checkCompatible('@phan-forbid-undeclared-magic-properties', [Comment::ON_CLASS], $i);
            $this->comment_flags |= Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES;
        } elseif ($type === 'phan-forbid-undeclared-magic-methods') {
            $this->checkCompatible('@phan-forbid-undeclared-magic-methods', [Comment::ON_CLASS], $i);
            $this->comment_flags |= Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS;
        } elseif ($type === 'phan-closure-scope') {
            $this->checkCompatible('@phan-closure-scope', Comment::FUNCTION_LIKE, $i);
            $this->closure_scope = $this->getPhanClosureScopeFromCommentLine($line, $i);
        } elseif ($type === 'phan-param') {
            $this->checkCompatible('@phan-param', Comment::FUNCTION_LIKE, $i);
            $this->phan_overrides['param'][] =
                $this->parameterFromCommentLine($line, false, $i);
        } elseif ($type === 'phan-return') {
            $this->checkCompatible('@phan-return', Comment::FUNCTION_LIKE, $i);
            $this->phan_overrides['return'] = $this->returnTypeFromCommentLine($line, $i);
        } elseif ($type === 'phan-override') {
            $this->checkCompatible('@override', [Comment::ON_METHOD, Comment::ON_CONST], $i);
            $this->comment_flags |= Flags::IS_OVERRIDE_INTENDED;
        } elseif ($type === 'phan-var') {
            $this->checkCompatible('@phan-var', Comment::HAS_VAR_ANNOTATION, $i);
            $comment_var = $this->parameterFromCommentLine($line, true, $i);
            if ($comment_var->getName() !== '' || !\in_array($this->comment_type, Comment::FUNCTION_LIKE)) {
                $this->phan_overrides['var'][] = $comment_var;
            }
        } elseif ($type === 'phan-file-suppress') {
            // See BuiltinSuppressionPlugin
            return;
        } elseif ($type === 'phan-suppress') {
            $this->parsePhanSuppress($i, $line);
        } elseif ($type === 'phan-property' || $type === 'phan-property-read' || $type === 'phan-property-write') {
            $this->parsePhanProperty($i, $line);
        } elseif ($type === 'phan-method') {
            $this->parsePhanMethod($i, $line);
        } elseif ($case_sensitive_type === 'phan-suppress-next-line' || $case_sensitive_type === 'phan-suppress-current-line') {
            // Do nothing, see BuiltinSuppressionPlugin
        } else {
            $this->emitIssue(
                Issue::MisspelledAnnotation,
                $this->guessActualLineLocation($i),
                '@' . $case_sensitive_type,
                '@phan-forbid-undeclared-magic-methods @phan-forbid-undeclared-magic-properties @phan-closure-scope @phan-override'
            );
        }
    }

    private function parsePhanSuppress(int $i, string $line)
    {
        $suppress_issue_types = $this->suppressIssuesFromCommentLine($line);
        if (count($suppress_issue_types) > 0) {
            array_push($this->suppress_issue_list, ...$suppress_issue_types);
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                trim($line)
            );
        }
    }

    private function parsePhanProperty(int $i, string $line)
    {
        $this->checkCompatible('@phan-property', [Comment::ON_CLASS], $i);
        // Make sure support for magic properties is enabled.
        if (Config::getValue('read_magic_property_annotations')) {
            $magic_property = $this->magicPropertyFromCommentLine($line, $i);
            if ($magic_property !== null) {
                $this->phan_overrides['property'][] = $magic_property;
            }
        }
    }

    private function parsePhanMethod(int $i, string $line)
    {
        // Make sure support for magic methods is enabled.
        if (Config::getValue('read_magic_method_annotations')) {
            $this->checkCompatible('@phan-method', [Comment::ON_CLASS], $i);
            $magic_method = $this->magicMethodFromCommentLine($line, $i);
            if ($magic_method !== null) {
                $this->phan_overrides['method'][] = $magic_method;
            }
        }
    }

    private function guessActualLineLocation(int $i) : int
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
        $j = $i;

        $line = $this->lines[$i];
        for ($check_lineno = $lineno_search; $check_lineno >= $lineno_stop; $check_lineno--) {
            $cur_line = $lines_array[$check_lineno];
            if (\stripos($cur_line, $line) !== false) {
                // Better heuristic: Lines in the middle of phpdoc are guaranteed to be complete, including a few newlines at the end.
                $j = $i - ($lineno_search - $check_lineno);
                if ($j > 0 && $j < $this->comment_lines_count - 1) {
                    if (\trim($line) !== \trim($cur_line)) {
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
     * @var array<int,int> $valid_types
     */
    private function checkCompatible(string $param_name, array $valid_types, int $i)
    {
        if (!\in_array($this->comment_type, $valid_types, true)) {
            $this->emitInvalidCommentForDeclarationType(
                $param_name,
                $this->guessActualLineLocation($i)
            );
        }
    }

    /**
     * @return void
     */
    private function emitInvalidCommentForDeclarationType(
        string $annotation_type,
        int $issue_lineno
    ) {
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
    private static function templateTypeFromCommentLine(
        string $line
    ) {
        // TODO: Just use WORD_REGEX? Backslashes or nested templates wouldn't make sense.
        if (preg_match('/@template\s+(' . Type::simple_type_regex . ')/', $line, $match)) {
            $template_type_identifier = $match[1];
            return new TemplateType($template_type_identifier);
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
    ) {
        $match = [];
        if (preg_match('/@inherits\s+(' . Type::type_regex . ')/', $line, $match)) {
            $type_string = $match[1];

            $type = new Some(Type::fromStringInContext(
                $type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            ));

            return $type;
        }

        return new None();
    }

    /**
     * This regex contains a single pattern, which matches a valid PHP identifier.
     * (e.g. for variable names, magic property names, etc.
     * This does not allow backslashes.
     */
    const WORD_REGEX = '([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';

    /**
     * @internal
     */
    const SUPPRESS_ISSUE_LIST = '(' . self::WORD_REGEX . '(,\s*' . self::WORD_REGEX . ')*)';

    /**
     * @internal
     */
    const PHAN_SUPPRESS_REGEX = '/@(?:phan-)?suppress\s+' . self::SUPPRESS_ISSUE_LIST . '/';

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return array<int,string>
     * 0 or more issue names to suppress
     */
    private static function suppressIssuesFromCommentLine(
        string $line
    ) : array {
        if (preg_match(self::PHAN_SUPPRESS_REGEX, $line, $match)) {
            return array_map('trim', explode(',', $match[1]));
        }

        return [];
    }

    /** @internal */
    const MAGIC_PARAM_REGEX = '/^(' . UnionType::union_type_regex . ')?\s*(?:(\.\.\.)\s*)?(?:\$' . self::WORD_REGEX . ')?((?:\s*=.*)?)$/';

    /**
     * Parses a magic method based on https://phpdoc.org/docs/latest/references/phpdoc/tags/method.html
     * @return ?CommentParameter - if null, the phpdoc magic method was invalid.
     */
    private function magicParamFromMagicMethodParamString(
        string $param_string,
        int $param_index
    ) {
        $param_string = trim($param_string);
        // Don't support trailing commas, or omitted params. Provide at least one of [type] or [parameter]
        if ($param_string === '') {
            return null;
        }
        // Parse an entry for [type] [parameter] - Assume both of those are optional.
        // https://github.com/phpDocumentor/phpDocumentor2/pull/1271/files - phpdoc allows passing an default value.
        // Phan allows `=.*`, to indicate that a parameter is optional
        // TODO: in another PR, check that optional parameters aren't before required parameters.
        if (preg_match(self::MAGIC_PARAM_REGEX, $param_string, $param_match)) {
            // Note: a magic method parameter can be variadic, but it can't be pass-by-reference? (No support in __call)
            $union_type_string = $param_match[1];
            $union_type = UnionType::fromStringInContext(
                $union_type_string,
                $this->context,
                Type::FROM_PHPDOC,
                $this->code_base
            );
            $is_variadic = $param_match[19] === '...';
            $default_str = $param_match[21];
            $has_default_value = $default_str !== '';
            if ($has_default_value) {
                $default_value_repr = trim(explode('=', $default_str, 2)[1]);
                if (strcasecmp($default_value_repr, 'null') === 0) {
                    $union_type = $union_type->nullableClone();
                }
            }
            $var_name = $param_match[20];
            if ($var_name === '') {
                // placeholder names are p1, p2, ...
                $var_name = 'p' . ($param_index + 1);
            }
            return new CommentParameter($var_name, $union_type, $is_variadic, $has_default_value);
        }
        return null;
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return ?CommentMethod
     * magic method with the parameter types, return types, and name.
     */
    private function magicMethodFromCommentLine(
        string $line,
        int $comment_line_offset
    ) {
        // https://phpdoc.org/docs/latest/references/phpdoc/tags/method.html
        // > Going to assume "static" is a magic keyword, based on https://github.com/phpDocumentor/phpDocumentor2/issues/822
        // > TODO: forbid in trait?
        // TODO: finish writing the regex.
        // Syntax:
        //    @method [return type] [name]([[type] [parameter]<, ...>]) [<description>]
        //    Assumes the parameters end at the first ")" after "("
        //    As an exception, allows one level of matching brackets
        //    to support old style arrays such as $x = array(), $x = array(2) (Default values are ignored)
        if (preg_match('/@(?:phan-)?method(?:\s+(static))?(?:(?:\s+(' . UnionType::union_type_regex_or_this . '))?)\s+' . self::WORD_REGEX . '\s*\(((?:[^()]|\([()]*\))*)\)\s*(.*)/', $line, $match)) {
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
                $return_union_type = VoidType::instance(false)->asUnionType();
            }
            $method_name = $match[26];

            $arg_list = trim($match[27]);
            $comment_params = [];
            // Special check if param list has 0 params.
            if ($arg_list !== '') {
                // TODO: Would need to use a different approach if templates were ever supported
                //       e.g. The magic method parsing doesn't support commas?
                $params_strings = explode(',', $arg_list);
                $failed = false;
                foreach ($params_strings as $i => $param_string) {
                    $param = $this->magicParamFromMagicMethodParamString($param_string, $i);
                    if ($param === null) {
                        $this->emitIssue(
                            Issue::UnextractableAnnotationPart,
                            $this->guessActualLineLocation($comment_line_offset),
                            \trim($line),
                            $param_string
                        );
                        $failed = true;
                    }
                    $comment_params[] = $param;
                }
                if ($failed) {
                    // Emit everything that was wrong with the parameters of the @method annotation at once, then reject it.
                    return null;
                }
            }

            // TODO: Track the line number for comment methods?
            return new CommentMethod($method_name, $return_union_type, $comment_params, $is_static);
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($comment_line_offset),
                trim($line)
            );
        }

        return null;
    }

    /**
     * @param string $line
     * An individual line of a comment
     * Currently treats property-read and property-write the same way
     * because of the rewrites required for read-only properties.
     *
     * @return CommentParameter|null
     * magic property with the union type.
     *
     * TODO: guess line number for emitted issue
     */
    private function magicPropertyFromCommentLine(
        string $line,
        int $i
    ) {
        // Note that the type of a property can be left out (@property $myVar) - This is equivalent to @property mixed $myVar
        // TODO: properly handle duplicates...
        // TODO: support read-only/write-only checks elsewhere in the codebase?
        if (\preg_match('/@(?:phan-)?(property|property-read|property-write)(?:\s+(' . UnionType::union_type_regex . '))?(?:\s+(?:\\$' . self::WORD_REGEX . '))/', $line, $match)) {
            $type = $match[2] ?? '';

            $property_name = $match[20] ?? '';
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

            return new CommentParameter(
                $property_name,
                $union_type
            );
        } else {
            $this->emitIssue(
                Issue::UnextractableAnnotation,
                $this->guessActualLineLocation($i),
                trim($line)
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
    ) : Option {
        $closure_scope_union_type_string = '';

        // https://secure.php.net/manual/en/closure.bindto.php
        // There wasn't anything in the phpdoc standard to indicate the class to which
        // a Closure would be bound with bind() or bindTo(), so using a custom tag.
        //
        // TODO: Also add a version which forbids using $this in the closure?
        if (preg_match('/@(PhanClosureScope|phan-closure-scope)\s+(' . Type::type_regex . ')/', $line, $match)) {
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
            trim($line)
        );
        return new None();
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
     *
     * @return void
     */
    protected function emitIssue(
        string $issue_type,
        int $issue_lineno,
        ...$parameters
    ) {
        Issue::maybeEmitWithParameters(
            $this->code_base,
            $this->context,
            $issue_type,
            $issue_lineno,
            $parameters
        );
    }
}
