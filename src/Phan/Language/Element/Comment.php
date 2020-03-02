<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use AssertionError;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Assertion;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\Comment\Method as CommentMethod;
use Phan\Language\Element\Comment\NullComment;
use Phan\Language\Element\Comment\Parameter as CommentParameter;
use Phan\Language\Element\Comment\Property as CommentProperty;
use Phan\Language\Element\Comment\ReturnComment;
use Phan\Language\Type;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\Library\None;
use Phan\Library\Option;
use Phan\Library\StringUtil;

/**
 * Handles extracting information(param types, return types, magic methods/properties, etc.) from phpdoc comments.
 * Instances of Comment contain the extracted information.
 *
 * @see Builder for the logic to create an instance of this class.
 * @phan-pure
 * @phan-file-suppress PhanAccessReadOnlyProperty TODO: Add a way to whitelist private methods that are only accessible from initializers (__construct, __wakeup, etc.)
 */
class Comment
{
    public const ON_CLASS      = 1;
    public const ON_VAR        = 2;
    public const ON_PROPERTY   = 3;
    public const ON_CONST      = 4;
    // TODO: Add another type for closure. (e.g. (at)phan-closure-scope)
    public const ON_METHOD     = 5;
    public const ON_FUNCTION   = 6;

    // List of types that are function-like (e.g. have params and function body)
    public const FUNCTION_LIKE = [
        self::ON_METHOD,
        self::ON_FUNCTION,
    ];

    // Lists of types that can have (at)var annotations.
    public const HAS_VAR_ANNOTATION = [
        self::ON_METHOD,
        self::ON_FUNCTION,
        self::ON_VAR,
        self::ON_PROPERTY,
        self::ON_CONST,
    ];

    public const HAS_TEMPLATE_ANNOTATION = [
        self::ON_CLASS,
        self::ON_FUNCTION,
        self::ON_METHOD,
    ];

    public const NAME_FOR_TYPE = [
        self::ON_CLASS      => 'class',
        self::ON_VAR        => 'variable',
        self::ON_PROPERTY   => 'property',
        self::ON_CONST      => 'constant',
        self::ON_METHOD     => 'method',
        self::ON_FUNCTION   => 'function',
    ];

    /**
     * @var int - contains a subset of flags to set on elements
     * Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES
     * Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS
     * Flags::IS_READ_ONLY
     * Flags::IS_WRITE_ONLY
     * Flags::IS_DEPRECATED
     */
    protected $comment_flags = 0;

    /**
     * @var list<CommentParameter>
     * A list of CommentParameters from var declarations
     */
    protected $variable_list = [];

    /**
     * @var list<CommentParameter>
     * A list of CommentParameters from param declarations
     */
    protected $parameter_list = [];

    /**
     * @var array<string,CommentParameter>
     * A map from variable name to CommentParameters from
     * param declarations
     */
    protected $parameter_map = [];

    /**
     * @var list<TemplateType>
     * A list of template types parameterizing a generic class
     */
    protected $template_type_list = [];

    /**
     * @var Option<Type>|None
     * Classes may specify their inherited type explicitly
     * via `(at)inherits Type`.
     */
    protected $inherited_type;

    /**
     * @var ReturnComment|null
     * the representation of an (at)return directive
     */
    protected $return_comment = null;

    /**
     * @var array<string,int>
     * A set of issue types to be suppressed
     */
    protected $suppress_issue_set = [];

    /**
     * @var array<string,CommentProperty>
     * A mapping from magic property parameters to types.
     */
    protected $magic_property_map = [];

    /**
     * @var array<string,CommentMethod>
     * A mapping from magic methods to parsed parameters, name, and return types.
     */
    protected $magic_method_map = [];

    /**
     * @var UnionType a list of types for (at)throws annotations
     */
    protected $throw_union_type;

    /**
     * @var Option<Type>|None
     * An optional class name defined by an (at)phan-closure-scope directive.
     * (overrides the class in which it is analyzed)
     */
    protected $closure_scope;

    /**
     * @var array<string,Assertion>
     * An optional assertion on a parameter's type
     */
    protected $param_assertion_map = [];

    /**
     * @var list<Type>
     * A list of mixins used by this class
     */
    protected $mixin_types = [];

    /**
     * A private constructor meant to ingest a parsed comment
     * docblock.
     *
     * @param int $comment_flags uses the following flags
     * - Flags::IS_DEPRECATED
     *   Set to true if the comment contains a 'deprecated'
     *   directive.
     * - Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES
     * - Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS
     *
     * @param list<CommentParameter> $variable_list
     *
     * @param list<CommentParameter> $parameter_list
     *
     * @param list<TemplateType> $template_type_list
     * A list of template types parameterizing a generic class
     *
     * @param Option<Type>|None $inherited_type (Note: some issues with templates and narrowing signature types to phpdoc type, added None as a workaround)
     * An override on the type of the extended class
     *
     * @param ?ReturnComment $return_comment
     *
     * @param array<string,int> $suppress_issue_set
     * A set of tags for error type to be suppressed
     *
     * @param list<CommentProperty> $magic_property_list
     *
     * @param list<CommentMethod> $magic_method_list
     *
     * @param array<string,mixed> $phan_overrides
     *
     * @param Option<Type>|None $closure_scope
     * For closures: Allows us to document the class of the object
     * to which a closure will be bound.
     *
     * @param UnionType $throw_union_type
     *
     * @param array<string,Assertion> $param_assertion_map
     *
     * @internal
     */
    public function __construct(
        int $comment_flags,
        array $variable_list,
        array $parameter_list,
        array $template_type_list,
        Option $inherited_type,
        $return_comment,
        array $suppress_issue_set,
        array $magic_property_list,
        array $magic_method_list,
        array $phan_overrides,
        Option $closure_scope,
        UnionType $throw_union_type,
        array $param_assertion_map,
        CodeBase $code_base,
        Context $context
    ) {
        $this->comment_flags = $comment_flags;
        $this->variable_list = $variable_list;
        $this->parameter_list = $parameter_list;
        $this->template_type_list = $template_type_list;
        $this->inherited_type = $inherited_type;
        $this->return_comment = $return_comment;
        $this->suppress_issue_set = $suppress_issue_set;
        $this->closure_scope = $closure_scope;
        $this->throw_union_type = $throw_union_type;
        $this->param_assertion_map = $param_assertion_map;

        foreach ($this->parameter_list as $i => $parameter) {
            $name = $parameter->getName();
            if (StringUtil::isNonZeroLengthString($name)) {
                if (isset($this->parameter_map[$name])) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::CommentDuplicateParam,
                        $context->getLineNumberStart(),
                        $name
                    );
                }
                // Add it to the named map
                $this->parameter_map[$name] = $parameter;

                // Remove it from the offset map
                unset($this->parameter_list[$i]);
            }
        }
        foreach ($magic_property_list as $property) {
            $name = $property->getName();
            if (StringUtil::isNonZeroLengthString($name)) {
                if (isset($this->magic_property_map[$name])) {
                    // Emit warning for duplicates.
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::CommentDuplicateMagicProperty,
                        $context->getLineNumberStart(),
                        $name
                    );
                }
                // Add it to the named map
                $this->magic_property_map[$name] = $property;
            }
        }
        foreach ($magic_method_list as $method) {
            $name = $method->getName();
            if (StringUtil::isNonZeroLengthString($name)) {
                if (isset($this->magic_method_map[$name])) {
                    // Emit warning for duplicates.
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::CommentDuplicateMagicMethod,
                        $context->getLineNumberStart(),
                        $name
                    );
                }
                // Add it to the named map
                $this->magic_method_map[$name] = $method;
            }
        }
        foreach ($phan_overrides as $key => $override_value) {
            $this->applyOverride($key, $override_value);
        }
        if (isset($phan_overrides['real-return'])) {
            $this->applyRealReturnOverride($phan_overrides['real-return']);
        }
    }

    private function applyRealReturnOverride(ReturnComment $real_return_comment): void
    {
        $old_comment = $this->return_comment;
        if (!$old_comment) {
            $this->return_comment = $real_return_comment;
            return;
        }
        $return_type = $old_comment->getType()->withRealTypeSet($real_return_comment->getType()->getRealTypeSet());
        $this->return_comment = new ReturnComment($return_type, $old_comment->getLineno());
    }

    /**
     * @param mixed $value
     */
    private function applyOverride(string $key, $value): void
    {
        switch ($key) {
            case 'param':
                foreach ($value as $parameter) {
                    '@phan-var CommentParameter $parameter';
                    $name = $parameter->getName();
                    if ($name !== '') {
                        // Add it to the named map
                        // TODO: could check that @phan-param is compatible with the original @param
                        $this->parameter_map[$name] = $parameter;
                    }
                }
                return;
            case 'real-return':
                return;
            case 'return':
                // TODO: could check that @phan-return is compatible with the original @return
                $this->return_comment = $value;
                return;
            case 'var':
                // TODO: Remove pre-existing entries.
                $this->mergeVariableList($value);
                return;
            case 'property':
                foreach ($value as $property) {
                    '@phan-var CommentProperty $property';
                    $name = $property->getName();
                    if ($name !== '') {
                        // Override or add the entry in the named map
                        $this->magic_property_map[$name] = $property;
                    }
                }
                return;
            case 'method':
                foreach ($value as $method) {
                    '@phan-var CommentMethod $method';
                    $name = $method->getName();
                    if ($name !== '') {
                        // Override or add the entry in the named map (probably always has a name)
                        $this->magic_method_map[$name] = $method;
                    }
                }
                return;
            case 'template':
                $this->template_type_list = $value;
                return;
            case 'inherits':
            case 'extends':
                $this->inherited_type = $value;
                return;
            case 'mixin':
                $this->mixin_types = $value;
                return;
        }
    }

    /**
     * @param list<CommentParameter> $override_comment_vars
     * A list of CommentParameters from var declarations
     */
    private function mergeVariableList(array $override_comment_vars): void
    {
        $known_names = [];
        foreach ($override_comment_vars as $override_var) {
            $known_names[$override_var->getName()] = true;
        }
        foreach ($this->variable_list as $i => $var) {
            if (isset($known_names[$var->getName()])) {
                unset($this->variable_list[$i]);
            }
        }
        $this->variable_list = \array_merge($this->variable_list, $override_comment_vars);
    }


    /**
     * @param string $comment full text of doc comment
     * @param CodeBase $code_base
     * @param Context $context
     * @param int $comment_type self::ON_* (the type of comment this is)
     * @return Comment
     * A comment built by parsing the given doc block
     * string.
     *
     * suppress PhanTypeMismatchArgument - Still need to work out issues with prefer_narrowed_phpdoc_param_type
     */
    public static function fromStringInContext(
        string $comment,
        CodeBase $code_base,
        Context $context,
        int $lineno,
        int $comment_type
    ): Comment {

        // Don't parse the comment if this doesn't need to.
        if ($comment === '' || !Config::getValue('read_type_annotations') || \strpos($comment, '@') === false) {
            return NullComment::instance();
        }

        // @phan-suppress-next-line PhanAccessMethodInternal
        return (new Builder(
            $comment,
            $code_base,
            $context,
            $lineno,
            $comment_type
        ))->build();
    }

    // TODO: Is `@return &array` valid phpdoc2?

    /**
     * @return bool
     * Set to true if the comment contains a 'deprecated'
     * directive.
     */
    public function isDeprecated(): bool
    {
        return ($this->comment_flags & Flags::IS_DEPRECATED) !== 0;
    }

    /**
     * @return bool
     * Set to true if the comment contains an 'override'
     * directive.
     */
    public function isOverrideIntended(): bool
    {
        return ($this->comment_flags & Flags::IS_OVERRIDE_INTENDED) !== 0;
    }

    /**
     * @return bool
     * Set to true if the comment contains an 'internal'
     * directive.
     */
    public function isNSInternal(): bool
    {
        return ($this->comment_flags & Flags::IS_NS_INTERNAL) !== 0;
    }

    /**
     * @return bool
     * Set to true if the comment contains an 'phan-pure'
     * directive.
     * (or phan-read-only + phan-external-mutation-free, eventually)
     */
    public function isPure(): bool
    {
        return ($this->comment_flags & Flags::IS_SIDE_EFFECT_FREE) === Flags::IS_SIDE_EFFECT_FREE;
    }

    private const FLAGS_FOR_PROPERTY = Flags::IS_NS_INTERNAL | Flags::IS_DEPRECATED | Flags::IS_READ_ONLY | Flags::IS_WRITE_ONLY;

    /**
     * Gets the subset of the bitmask that applies to properties.
     */
    public function getPhanFlagsForProperty(): int
    {
        return $this->comment_flags & self::FLAGS_FOR_PROPERTY;
    }

    private const FLAGS_FOR_CLASS =
        Flags::IS_NS_INTERNAL |
        Flags::IS_DEPRECATED |
        Flags::IS_SIDE_EFFECT_FREE |
        Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS |
        Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES |
        Flags::IS_CONSTRUCTOR_USED_FOR_SIDE_EFFECTS;

    /**
     * Gets the subset of the bitmask that applies to classes.
     */
    public function getPhanFlagsForClass(): int
    {
        return $this->comment_flags & self::FLAGS_FOR_CLASS;
    }

    private const FLAGS_FOR_METHOD =
        Flags::IS_NS_INTERNAL |
        Flags::IS_DEPRECATED |
        Flags::HARDCODED_RETURN_TYPE |
        Flags::IS_SIDE_EFFECT_FREE;

    /**
     * Gets the subset of the bitmask that applies to methods.
     */
    public function getPhanFlagsForMethod(): int
    {
        return $this->comment_flags & self::FLAGS_FOR_METHOD;
    }

    /**
     * @return bool
     * Set to true if the comment contains a 'phan-forbid-undeclared-magic-properties'
     * directive.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getForbidUndeclaredMagicProperties(): bool
    {
        return ($this->comment_flags & Flags::CLASS_FORBID_UNDECLARED_MAGIC_PROPERTIES) !== 0;
    }

    /**
     * @return bool
     * Set to true if the comment contains a 'phan-forbid-undeclared-magic-methods'
     * directive.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getForbidUndeclaredMagicMethods(): bool
    {
        return ($this->comment_flags & Flags::CLASS_FORBID_UNDECLARED_MAGIC_METHODS) !== 0;
    }

    /**
     * @return UnionType
     * A UnionType defined by a (at)return directive
     */
    public function getReturnType(): UnionType
    {
        if (!$this->return_comment) {
            throw new AssertionError('Should check hasReturnUnionType');
        }
        return $this->return_comment->getType();
    }

    /**
     * @return int
     * A line of a (at)return directive
     */
    public function getReturnLineno(): int
    {
        if (!$this->return_comment) {
            throw new AssertionError('Should check hasReturnUnionType');
        }
        return $this->return_comment->getLineno();
    }

    /**
     * @return bool
     * True if this doc block contains a (at)return
     * directive specifying a type.
     */
    public function hasReturnUnionType(): bool
    {
        return $this->return_comment !== null;
    }

    /**
     * @return Option<Type>
     * An optional Type defined by a (at)phan-closure-scope
     * directive specifying a single type.
     *
     * @suppress PhanPartialTypeMismatchReturn (Null)
     */
    public function getClosureScopeOption(): Option
    {
        return $this->closure_scope;
    }

    /**
     * @return list<CommentParameter> (The leftover parameters without a name)
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getParameterList(): array
    {
        return $this->parameter_list;
    }

    /**
     * @return array<string,CommentParameter> (maps the names of parameters to their values. Does not include parameters which didn't provide names)
     */
    public function getParameterMap(): array
    {
        return $this->parameter_map;
    }

    /**
     * @return list<TemplateType>
     * A list of template types parameterizing a generic class
     */
    public function getTemplateTypeList(): array
    {
        return $this->template_type_list;
    }

    /**
     * @return Option<Type>
     * An optional type declaring what a class extends.
     * @suppress PhanPartialTypeMismatchReturn (Null)
     */
    public function getInheritedTypeOption(): Option
    {
        return $this->inherited_type;
    }

    /**
     * @return list<Type>
     * An optional type declaring the mixins used by a class.
     */
    public function getMixinTypes(): array
    {
        return $this->mixin_types;
    }

    /**
     * @return array<string,int>
     * A set of issue names like 'PhanUnreferencedPublicMethod' to suppress.
     * If the values of fields are 0, the suppressions were not used yet.
     */
    public function getSuppressIssueSet(): array
    {
        return $this->suppress_issue_set;
    }

    /**
     * @return bool
     * True if we have a parameter at the given offset
     */
    public function hasParameterWithNameOrOffset(
        string $name,
        int $offset
    ): bool {
        if (isset($this->parameter_map[$name])) {
            return true;
        }

        return isset($this->parameter_list[$offset]);
    }

    /**
     * @return CommentParameter
     * The parameter at the given offset
     */
    public function getParameterWithNameOrOffset(
        string $name,
        int $offset
    ): CommentParameter {
        if (isset($this->parameter_map[$name])) {
            return $this->parameter_map[$name];
        }

        return $this->parameter_list[$offset];
    }

    /**
     * @unused
     * @return bool
     * True if we have a magic property with the given name
     */
    public function hasMagicPropertyWithName(
        string $name
    ): bool {
        return isset($this->magic_property_map[$name]);
    }

    /**
     * Returns the magic property with the given name.
     * May or may not have a type.
     * @unused
     * @suppress PhanUnreferencedPublicMethod not used right now, but making it available for plugins
     */
    public function getMagicPropertyWithName(
        string $name
    ): CommentProperty {
        return $this->magic_property_map[$name];
    }

    /**
     * @return array<string,CommentProperty> map from parameter name to parameter
     */
    public function getMagicPropertyMap(): array
    {
        return $this->magic_property_map;
    }

    /**
     * @return array<string,CommentMethod> map from method name to method info
     */
    public function getMagicMethodMap(): array
    {
        return $this->magic_method_map;
    }

    /**
     * @return UnionType list of types for throws statements
     */
    public function getThrowsUnionType(): UnionType
    {
        return $this->throw_union_type;
    }

    /**
     * @return list<CommentParameter> the list of (at)var annotations
     */
    public function getVariableList(): array
    {
        return $this->variable_list;
    }

    /**
     * @return array<string,Assertion> maps parameter names to assertions about those parameters
     */
    public function getParamAssertionMap(): array
    {
        return $this->param_assertion_map;
    }

    public function __toString(): string
    {
        // TODO: add new properties of Comment to this method
        // (magic methods, magic properties, custom @phan directives, etc.))
        $string = "/**\n";

        if (($this->comment_flags & Flags::IS_DEPRECATED) !== 0) {
            $string  .= " * @deprecated\n";
        }

        foreach ($this->variable_list as $variable) {
            $string  .= " * @var $variable\n";
        }

        foreach (array_merge($this->parameter_map, $this->parameter_list) as $parameter) {
            $string  .= " * @param $parameter\n";
        }

        if ($this->return_comment) {
            $string .= " * @return {$this->return_comment->getType()}\n";
        }
        foreach ($this->throw_union_type->getTypeSet() as $type) {
            $string .= " * @throws {$type}\n";
        }

        $string .= " */\n";

        return $string;
    }
}
