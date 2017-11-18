<?php declare(strict_types=1);
namespace Phan\AST;

use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\TypeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\Element\TraitAdaptations;
use Phan\Language\Element\TraitAliasSource;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Library\FileCache;
use Phan\Library\None;
use Phan\Library\Some;
use ast\Node;
use ast;

/**
 * Methods for an AST node in context
 */
class ContextNode
{

    /** @var CodeBase */
    private $code_base;

    /** @var Context */
    private $context;

    /** @var Node|string|null */
    private $node;

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param Node|string|null $node
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        $node
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->node = $node;
    }

    /**
     * Get a list of fully qualified names from a node
     *
     * @return string[]
     */
    public function getQualifiedNameList() : array
    {
        if (!($this->node instanceof Node)) {
            return [];
        }

        return \array_map(function ($name_node) {
            return (new ContextNode(
                $this->code_base,
                $this->context,
                $name_node
            ))->getQualifiedName();
        }, $this->node->children ?? []);
    }

    /**
     * Get a fully qualified name from a node
     *
     * @return string
     */
    public function getQualifiedName() : string
    {
        return $this->getClassUnionType()->__toString();
    }

    /**
     * Gets the FQSEN for a trait.
     * NOTE: does not validate that it is really used on a trait
     * @return FQSEN[]
     */
    public function getTraitFQSENList() : array
    {
        if (!($this->node instanceof Node)) {
            return [];
        }

        return \array_map(function ($name_node) : FQSEN {
            return (new ContextNode(
                $this->code_base,
                $this->context,
                $name_node
            ))->getTraitFQSEN([]);
        }, $this->node->children ?? []);
    }

    /**
     * Gets the FQSEN for a trait.
     * NOTE: does not validate that it is really used on a trait
     * @param TraitAdaptations[] $adaptations_map
     * @return ?FQSEN (If this returns null, the caller is responsible for emitting an issue or falling back)
     */
    public function getTraitFQSEN(array $adaptations_map)
    {
        // TODO: In a subsequent PR, try to make trait analysis work when $adaptations_map has multiple possible traits.
        $trait_fqsen_string = $this->getQualifiedName();
        if ($trait_fqsen_string === '') {
            if (\count($adaptations_map) === 1) {
                return \reset($adaptations_map)->getTraitFQSEN();
            } else {
                return null;
            }
        }
        return FullyQualifiedClassName::fromStringInContext(
            $trait_fqsen_string,
            $this->context
        );
    }

    /**
     * Get a list of traits adaptations from a node of kind ast\AST_TRAIT_ADAPTATIONS
     * (with fully qualified names and `as`/`instead` info)
     *
     * @param FQSEN[] $trait_fqsen_list TODO: use this for sanity check
     *
     * @return TraitAdaptations[] maps the lowercase trait fqsen to the corresponding adaptations.
     */
    public function getTraitAdaptationsMap(array $trait_fqsen_list) : array
    {
        $node = $this->node;
        if (!($node instanceof Node)) {
            return [];
        }
        \assert($node->kind === ast\AST_TRAIT_ADAPTATIONS);

        // NOTE: This fetches fully qualified names more than needed,
        // but this isn't optimized, since traits aren't frequently used in classes.

        $adaptations_map = [];
        foreach ($trait_fqsen_list as $trait_fqsen) {
            $adaptations_map[strtolower($trait_fqsen->__toString())] = new TraitAdaptations($trait_fqsen);
        }

        foreach ($this->node->children ?? [] as $adaptation_node) {
            \assert($adaptation_node instanceof Node);
            if ($adaptation_node->kind === ast\AST_TRAIT_ALIAS) {
                $this->handleTraitAlias($adaptations_map, $adaptation_node);
            } elseif ($adaptation_node->kind === ast\AST_TRAIT_PRECEDENCE) {
                $this->handleTraitPrecedence($adaptations_map, $adaptation_node);
            } else {
                \assert(false, ("Unknown adaptation node kind " . $adaptation_node->kind));
            }
        }
        return $adaptations_map;
    }

    /**
     * Handles a node of kind ast\AST_TRAIT_ALIAS, modifying the corresponding TraitAdaptations instance
     * @param TraitAdaptations[] $adaptations_map
     * @param Node $adaptation_node
     * @return void
     */
    private function handleTraitAlias(array $adaptations_map, Node $adaptation_node)
    {
        $trait_method_node = $adaptation_node->children['method'];
        $trait_original_class_name_node = $trait_method_node->children['class'];
        $trait_original_method_name = $trait_method_node->children['method'];
        $trait_new_method_name = $adaptation_node->children['alias'] ?? $trait_original_method_name;
        \assert(\is_string($trait_original_method_name));
        \assert(\is_string($trait_new_method_name));
        $trait_fqsen = (new ContextNode(
            $this->code_base,
            $this->context,
            $trait_original_class_name_node
        ))->getTraitFQSEN($adaptations_map);
        if ($trait_fqsen === null) {
            // TODO: try to analyze this rare special case instead of giving up in a subsequent PR?
            // E.g. `use A, B{foo as bar}` is valid PHP, but hard to analyze.
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::AmbiguousTraitAliasSource,
                $trait_method_node->lineno ?? 0,
                $trait_new_method_name,
                $trait_original_method_name,
                '[' . implode(', ', \array_map(function (TraitAdaptations $t) : string {
                    return (string) $t->getTraitFQSEN();
                }, $adaptations_map)) . ']'
            );
            return;
        }

        $fqsen_key = strtolower($trait_fqsen->__toString());

        $adaptations_info = $adaptations_map[$fqsen_key] ?? null;
        if ($adaptations_info === null) {
            // This will probably correspond to a PHP fatal error, but keep going anyway.
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::RequiredTraitNotAdded,
                $trait_original_class_name_node->lineno ?? 0,
                $trait_fqsen->__toString()
            );
            return;
        }
        // TODO: Could check for duplicate alias method occurrences, but `php -l` would do that for you in some cases
        $adaptations_info->alias_methods[$trait_new_method_name] = new TraitAliasSource($trait_original_method_name, $adaptation_node->lineno ?? 0, $adaptation_node->flags ?? 0);
        // Handle `use MyTrait { myMethod as private; }` by skipping the original method.
        // TODO: Do this a cleaner way.
        if (strcasecmp($trait_new_method_name, $trait_original_method_name) === 0) {
            $adaptations_info->hidden_methods[strtolower($trait_original_method_name)] = true;
        }
    }

    /**
     * Handles a node of kind ast\AST_TRAIT_PRECEDENCE, modifying the corresponding TraitAdaptations instance
     * @param TraitAdaptations[] $adaptations_map
     * @param Node $adaptation_node
     * @return void
     */
    private function handleTraitPrecedence(array $adaptations_map, Node $adaptation_node)
    {
        // TODO: Should also verify that the original method exists, in a future PR?
        $trait_method_node = $adaptation_node->children['method'];
        // $trait_chosen_class_name_node = $trait_method_node->children['class'];
        $trait_chosen_method_name = $trait_method_node->children['method'];
        $trait_chosen_class_name_node = $trait_method_node->children['class'];

        $trait_chosen_fqsen = (new ContextNode(
            $this->code_base,
            $this->context,
            $trait_chosen_class_name_node
        ))->getTraitFQSEN($adaptations_map);


        if (!$trait_chosen_fqsen) {
            throw new UnanalyzableException($trait_chosen_class_name_node, "This shouldn't happen. Could not determine trait fqsen for trait with higher precedence for method $trait_chosen_method_name");
        }

        if (($adaptations_map[strtolower($trait_chosen_fqsen->__toString())] ?? null) === null) {
            // This will probably correspond to a PHP fatal error, but keep going anyway.
            Issue::maybeEmit(
                $this->code_base,
                $this->context,
                Issue::RequiredTraitNotAdded,
                $trait_chosen_class_name_node->lineno ?? 0,
                $trait_chosen_fqsen->__toString()
            );
        }

        // This is the class which will have the method hidden
        foreach ($adaptation_node->children['insteadof']->children as $trait_insteadof_class_name) {
            \assert(\is_string($trait_chosen_method_name));
            $trait_insteadof_fqsen = (new ContextNode(
                $this->code_base,
                $this->context,
                $trait_insteadof_class_name
            ))->getTraitFQSEN($adaptations_map);
            if (!$trait_insteadof_fqsen) {
                throw new UnanalyzableException($trait_insteadof_class_name, "This shouldn't happen. Could not determine trait fqsen for trait with lower precedence for method $trait_chosen_method_name");
            }

            $fqsen_key = strtolower($trait_insteadof_fqsen->__toString());

            $adaptations_info = $adaptations_map[$fqsen_key] ?? null;
            if ($adaptations_info === null) {
                // TODO: Make this into an issue type
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::RequiredTraitNotAdded,
                    $trait_insteadof_class_name->lineno ?? 0,
                    $trait_insteadof_fqsen->__toString()
                );
                continue;
            }
            $adaptations_info->hidden_methods[strtolower($trait_chosen_method_name)] = true;
        }
    }

    /**
     * @return string
     * A variable name associated with the given node
     */
    public function getVariableName() : string
    {
        if (!($this->node instanceof ast\Node)) {
            return (string)$this->node;
        }

        $node = $this->node;

        while (($node instanceof ast\Node)
            && ($node->kind != ast\AST_VAR)
            && ($node->kind != ast\AST_STATIC)
            && ($node->kind != ast\AST_MAGIC_CONST)
        ) {
            $node = \array_values($node->children ?? [])[0];
        }

        if (!($node instanceof ast\Node)) {
            return (string)$node;
        }

        $name_node = $node->children['name'] ?? null;
        if (empty($name_node)) {
            return '';
        }

        if ($name_node instanceof ast\Node) {
            // This is nonsense. Give up, but check if it's a type other than int/string.
            // (e.g. to catch typos such as $$this->foo = bar;)
            try {
                $name_node_type = (new UnionTypeVisitor($this->code_base, $this->context, true))($name_node);
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return '';
            }
            static $int_or_string_type;
            if ($int_or_string_type === null) {
                $int_or_string_type = new UnionType();
                $int_or_string_type->addType(StringType::instance(false));
                $int_or_string_type->addType(IntType::instance(false));
                $int_or_string_type->addType(NullType::instance(false));
            }
            if (!$name_node_type->canCastToUnionType($int_or_string_type)) {
                Issue::maybeEmit($this->code_base, $this->context, Issue::TypeSuspiciousIndirectVariable, $name_node->lineno ?? 0, (string)$name_node_type);
            }

            return '';
        }

        return (string)$name_node;
    }

    /**
     * @return UnionType the union type of the class for this class node. (Should have just one Type)
     */
    public function getClassUnionType() : UnionType
    {
        return UnionTypeVisitor::unionTypeFromClassNode(
            $this->code_base,
            $this->context,
            $this->node
        );
    }

    // Constants for getClassList() API
    const CLASS_LIST_ACCEPT_ANY = 0;
    const CLASS_LIST_ACCEPT_OBJECT = 1;
    const CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME = 2;

    /**
     * @param bool $ignore_missing_classes
     * If set to true, missing classes will be ignored and
     * exceptions will be inhibited
     *
     * @param int $expected_type_categories
     * Does not affect the returned classes, but will cause phan to emit issues. Does not emit by default.
     * If set to CLASS_LIST_ACCEPT_ANY, this will not warn.
     * If set to CLASS_LIST_ACCEPT_OBJECT, this will warn if the inferred type is exclusively non-object types.
     * If set to CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME, this will warn if the inferred type is exclusively non-object and non-string types.
     *
     * @param ?string $custom_issue_type
     * If this exists, emit the given issue type (passing in union type as format arg) instead of the default issue type.
     *
     * @return Clazz[]
     * A list of classes representing the non-native types
     * associated with the given node
     *
     * @throws CodeBaseException
     * An exception is thrown if a non-native type does not have
     * an associated class
     */
    public function getClassList(bool $ignore_missing_classes = false, int $expected_type_categories = self::CLASS_LIST_ACCEPT_ANY, string $custom_issue_type = null) : array
    {
        $union_type = $this->getClassUnionType();
        if ($union_type->isEmpty()) {
            return [];
        }

        $class_list = [];

        if ($ignore_missing_classes) {
            try {
                foreach ($union_type->asClassList(
                    $this->code_base,
                    $this->context
                ) as $clazz) {
                    $class_list[] = $clazz;
                }
            } catch (CodeBaseException $exception) {
                // swallow it
            }
        } else {
            foreach ($union_type->asClassList(
                $this->code_base,
                $this->context
            ) as $clazz) {
                $class_list[] = $clazz;
            }
        }

        if (\count($class_list) === 0 && $expected_type_categories !== self::CLASS_LIST_ACCEPT_ANY) {
            if (!$union_type->hasTypeMatchingCallback(function (Type $type) use ($expected_type_categories) : bool {
                return $type->isObject() || ($type instanceof MixedType) || ($expected_type_categories === self::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME && $type instanceof StringType);
            })) {
                if ($custom_issue_type === Issue::TypeExpectedObjectPropAccess) {
                    if ($union_type->isType(NullType::instance(false))) {
                        $custom_issue_type = Issue::TypeExpectedObjectPropAccessButGotNull;
                    }
                }
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    $custom_issue_type ?? ($expected_type_categories === self::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME ? Issue::TypeExpectedObjectOrClassName : Issue::TypeExpectedObject),
                    $this->node->lineno ?? 0,
                    (string)$union_type
                );
            }
        }

        return $class_list;
    }

    /**
     * @param Node|string $method_name
     * Either then name of the method or a node that
     * produces the name of the method.
     *
     * @param bool $is_static
     * Set to true if this is a static method call
     *
     * @param bool $is_direct
     * Set to true if this is directly invoking the method (guaranteed not to be special syntax)
     *
     * @return Method
     * A method with the given name on the class referenced
     * from the given node
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * method
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws IssueException
     */
    public function getMethod(
        $method_name,
        bool $is_static,
        bool $is_direct = false
    ) : Method {

        if ($method_name instanceof Node) {
            // The method_name turned out to be a variable.
            // There isn't much we can do to figure out what
            // it's referring to.
            throw new NodeException(
                $method_name,
                "Unexpected method node"
            );
        }

        \assert(
            \is_string($method_name),
            "Method name must be a string. Found non-string in context."
        );

        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        try {
            // Fetch the list of valid classes, and warn about any undefined classes.
            // (We have more specific issue types such as PhanNonClassMethodCall below, don't emit PhanTypeExpected*)
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr']
                    ?? $this->node->children['class']
            ))->getClassList(false, self::CLASS_LIST_ACCEPT_ANY);
        } catch (CodeBaseException $exception) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassMethod)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $method_name, (string)$exception->getFQSEN() ]
                )
            );
        }

        // If there were no classes on the left-type, figure
        // out what we were trying to call the method on
        // and send out an error.
        if (empty($class_list)) {
            $union_type = UnionTypeVisitor::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr']
                    ?? $this->node->children['class']
            );

            if (!$union_type->isEmpty()
                && $union_type->isNativeType()
                && !$union_type->hasAnyType([
                    MixedType::instance(false),
                    ObjectType::instance(false),
                    StringType::instance(false)
                ])
                && !(
                    Config::get_null_casts_as_any_type()
                    && $union_type->hasType(NullType::instance(false))
                )
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::NonClassMethodCall)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $method_name, (string)$union_type ]
                    )
                );
            }

            throw new NodeException(
                $this->node,
                "Can't figure out method call for $method_name"
            );
        }

        // Hunt to see if any of them have the method we're
        // looking for
        foreach ($class_list as $class) {
            if ($class->hasMethodWithName($this->code_base, $method_name, $is_direct)) {
                return $class->getMethodByName(
                    $this->code_base,
                    $method_name
                );
            } elseif (!$is_static && $class->allowsCallingUndeclaredInstanceMethod($this->code_base)) {
                return $class->getCallMethod($this->code_base);
            } elseif ($is_static && $class->allowsCallingUndeclaredStaticMethod($this->code_base)) {
                return $class->getCallStaticMethod($this->code_base);
            }
        }

        // Figure out an FQSEN for the method we couldn't find
        $method_fqsen = FullyQualifiedMethodName::make(
            $class_list[0]->getFQSEN(),
            $method_name
        );

        if ($is_static) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredStaticMethod)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ (string)$method_fqsen ]
                )
            );
        }

        throw new IssueException(
            Issue::fromType(Issue::UndeclaredMethod)(
                $this->context->getFile(),
                $this->node->lineno ?? 0,
                [ (string)$method_fqsen ]
            )
        );
    }

    /**
     * Yields a list of FunctionInterface objects for the 'expr' of an AST_CALL.
     * @return \Generator
     */
    public function getFunctionFromNode()
    {
        $expression = $this->node;

        if ($expression->kind == ast\AST_VAR) {
            $variable_name = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getVariableName();

            if (empty($variable_name)) {
                return;
            }

            // $var() - hopefully a closure, otherwise we don't know
            if ($this->context->getScope()->hasVariableWithName(
                $variable_name
            )) {
                $variable = $this->context->getScope()
                    ->getVariableByName($variable_name);

                $union_type = $variable->getUnionType();
                if ($union_type->isEmpty()) {
                    return;
                }

                foreach ($union_type->getTypeSet() as $type) {
                    // TODO: Allow CallableType to have FQSENs as well, e.g. `$x = [MyClass::class, 'myMethod']` has an FQSEN in a sense.
                    if (!($type instanceof ClosureType)) {
                        continue;
                    }

                    $closure_fqsen =
                        FullyQualifiedFunctionName::fromFullyQualifiedString(
                            (string)$type->asFQSEN()
                        );

                    if ($this->code_base->hasFunctionWithFQSEN(
                        $closure_fqsen
                    )) {
                        // Get the closure
                        $function = $this->code_base->getFunctionByFQSEN(
                            $closure_fqsen
                        );

                        yield $function;
                    }
                }
            }
        } elseif ($expression->kind == ast\AST_NAME
            // nothing to do
        ) {
            try {
                $method = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $expression
                ))->getFunction($expression->children['name']);
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $this->code_base,
                    $this->context,
                    $exception->getIssueInstance()
                );
                return $this->context;
            }

            yield $method;
        } elseif ($expression->kind == ast\AST_CALL
            || $expression->kind == ast\AST_STATIC_CALL
            || $expression->kind == ast\AST_NEW
            || $expression->kind == ast\AST_METHOD_CALL
        ) {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $expression
            ))->getClassList(false, self::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);

            foreach ($class_list as $class) {
                if (!$class->hasMethodWithName(
                    $this->code_base,
                    '__invoke'
                )) {
                    continue;
                }

                $method = $class->getMethodByName(
                    $this->code_base,
                    '__invoke'
                );

                // Check the call for parameter and argument types
                yield $method;
            }
        } elseif ($expression->kind === ast\AST_CLOSURE) {
            $closure_fqsen = FullyQualifiedFunctionName::fromClosureInContext(
                $this->context->withLineNumberStart($expression->lineno ?? 0),
                $expression
            );
            $method = $this->code_base->getFunctionByFQSEN($closure_fqsen);
            yield $method;
        }
        // TODO: AST_CLOSURE
    }

    /**
     * @param string $function_name
     * The name of the function we'd like to look up
     *
     * @param bool $is_function_declaration
     * This must be set to true if we're getting a function
     * that is being declared and false if we're getting a
     * function being called.
     *
     * @return FunctionInterface
     * A method with the given name in the given context
     *
     * @throws IssueException
     * An exception is thrown if we can't find the given
     * function
     */
    public function getFunction(
        string $function_name,
        bool $is_function_declaration = false
    ) : FunctionInterface {

        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );
        $namespace = $this->context->getNamespace();
        // TODO: support namespace aliases for functions
        if ($is_function_declaration) {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $namespace,
                    $function_name
                );
        } else {
            if (($this->node->flags & (ast\flags\NAME_RELATIVE | ast\flags\NAME_NOT_FQ)) !== 0) {
                // For relative and non-fully qualified functions (e.g. namespace\foo(), foo())
                $function_fqsen =
                    FullyQualifiedFunctionName::make(
                        $namespace,
                        $function_name
                    );

                if ($this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
                    return $this->code_base->getFunctionByFQSEN($function_fqsen);
                }
                if (($this->node->flags & ast\flags\NAME_RELATIVE) !== 0 || $namespace === '') {
                    throw new IssueException(
                        Issue::fromType(Issue::UndeclaredFunction)(
                            $this->context->getFile(),
                            $this->node->lineno ?? 0,
                            [ "$function_fqsen()" ]
                        )
                    );
                }
                // If it doesn't exist in the local namespace, try it
                // in the global namespace
            }
            $function_fqsen =
                FullyQualifiedFunctionName::fromStringInContext(
                    $function_name,
                    $this->context
                );
        }

        // Make sure the method we're calling actually exists
        if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredFunction)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ "$function_fqsen()" ]
                )
            );
        }

        return $this->code_base->getFunctionByFQSEN($function_fqsen);
    }

    /**
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws IssueException
     * A IssueException is thrown if the variable doesn't
     * exist
     */
    public function getVariable() : Variable
    {
        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        // Get the name of the variable
        $variable_name = $this->getVariableName();

        if (empty($variable_name)) {
            throw new NodeException(
                $this->node,
                "Variable name not found"
            );
        }

        // Check to see if the variable exists in this scope
        if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredVariable)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $variable_name ]
                )
            );
        }

        return $this->context->getScope()->getVariableByName(
            $variable_name
        );
    }

    /**
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     */
    public function getOrCreateVariable() : Variable
    {
        try {
            return $this->getVariable();
        } catch (IssueException $exception) {
            // Swallow it
        }

        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        // Create a new variable
        $variable = Variable::fromNodeInContext(
            $this->node,
            $this->context,
            $this->code_base,
            false
        );

        $this->context->addScopeVariable($variable);

        return $variable;
    }

    /**
     * @param string|Node $property_name
     * The name of the property we're looking up
     *
     * @param bool $is_static
     * True if we're looking for a static property,
     * false if we're looking for an instance property.
     *
     * @return Property
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws IssueException
     * An exception is thrown if we can't find the given
     * class or if we don't have access to the property (its
     * private or protected)
     * or if the property is static and missing.
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws UnanalyzableException
     * An exception is thrown if we hit a construct in which
     * we can't determine if the property exists or not
     */
    public function getProperty(
        $property_name,
        bool $is_static
    ) : Property {

        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        $property_name = $this->node->children['prop'];

        // Give up for things like C::$prop_name
        if (!\is_string($property_name)) {
            throw new NodeException(
                $this->node,
                "Cannot figure out non-string property name"
            );
        }

        $class_fqsen = null;

        try {
            $expected_type_categories = $is_static ? self::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME : self::CLASS_LIST_ACCEPT_OBJECT;
            $expected_issue = $is_static ? Issue::TypeExpectedObjectStaticPropAccess : Issue::TypeExpectedObjectPropAccess;
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr'] ??
                    $this->node->children['class']
            ))->getClassList(true, $expected_type_categories, $expected_issue);
        } catch (CodeBaseException $exception) {
            if ($is_static) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredStaticProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $property_name, (string)$exception->getFQSEN() ]
                    )
                );
            } else {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ "{$exception->getFQSEN()}->$property_name" ]
                    )
                );
            }
        }

        foreach ($class_list as $class) {
            $class_fqsen = $class->getFQSEN();

            // Keep hunting if this class doesn't have the given
            // property
            if (!$class->hasPropertyWithName(
                $this->code_base,
                $property_name
            )) {
                // (if fetching an instance property)
                // If there's a getter on properties then all
                // bets are off. However, @phan-forbid-undeclared-magic-properties
                // will make this method analyze the code as if all properties were declared or had @property annotations.
                if (!$is_static && $class->hasGetMethod($this->code_base) && !$class->getForbidUndeclaredMagicProperties($this->code_base)) {
                    throw new UnanalyzableException(
                        $this->node,
                        "Can't determine if property {$property_name} exists in class {$class->getFQSEN()} with __get defined"
                    );
                }

                continue;
            }

            $property = $class->getPropertyByNameInContext(
                $this->code_base,
                $property_name,
                $this->context,
                $is_static
            );

            if ($property->isDeprecated()) {
                throw new IssueException(
                    Issue::fromType(Issue::DeprecatedProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [
                            (string)$property->getFQSEN(),
                            $property->getFileRef()->getFile(),
                            $property->getFileRef()->getLineNumberStart(),
                        ]
                    )
                );
            }

            if ($property->isNSInternal($this->code_base)
                && !$property->isNSInternalAccessFromContext(
                    $this->code_base,
                    $this->context
                )
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyInternal)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [
                            (string)$property->getFQSEN(),
                            $property->getElementNamespace($this->code_base),
                            $property->getFileRef()->getFile(),
                            $property->getFileRef()->getLineNumberStart(),
                            $this->context->getNamespace(),
                        ]
                    )
                );
            }

            return $property;
        }

        // Since we didn't find the property on any of the
        // possible classes, check for classes with dynamic
        // properties
        if (!$is_static) {
            foreach ($class_list as $class) {
                if (Config::getValue('allow_missing_properties')
                    || $class->getHasDynamicProperties($this->code_base)
                ) {
                    return $class->getPropertyByNameInContext(
                        $this->code_base,
                        $property_name,
                        $this->context,
                        $is_static
                    );
                }
            }
        }

        /*
        $std_class_fqsen =
            FullyQualifiedClassName::getStdClassFQSEN();

        // If missing properties are cool, create it on
        // the first class we found
        if (!$is_static && ($class_fqsen && ($class_fqsen === $std_class_fqsen))
            || Config::getValue('allow_missing_properties')
        ) {
            if (count($class_list) > 0) {
                $class = $class_list[0];
                return $class->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    $is_static
                );
            }
        }
        */

        // If the class isn't found, we'll get the message elsewhere
        if ($class_fqsen) {
            if ($is_static) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredStaticProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $property_name, (string)$class_fqsen ]
                    )
                );
            } else {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ "$class_fqsen->$property_name" ]
                    )
                );
            }
        }

        throw new NodeException(
            $this->node,
            "Cannot figure out property from {$this->context}"
        );
    }

    /**
     * @return Property
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws UnanalyzableException
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws IssueException
     * An exception is thrown if $is_static, but the property doesn't exist.
     */
    public function getOrCreateProperty(
        string $property_name,
        bool $is_static
    ) : Property {

        try {
            return $this->getProperty($property_name, $is_static);
        } catch (IssueException $exception) {
            if ($is_static) {
                throw $exception;
            }
            // TODO: log types of IssueException that aren't for undeclared properties?
            // (in another PR)

            // For instance properties, ignore it,
            // because we'll create our own property
        } catch (UnanalyzableException $exception) {
            if ($is_static) {
                throw $exception;
            }
            // For instance properties, ignore it,
            // because we'll create our own property
        }

        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        try {
            $expected_type_categories = $is_static ? self::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME : self::CLASS_LIST_ACCEPT_OBJECT;
            $expected_issue = $is_static ? Issue::TypeExpectedObjectStaticPropAccess : Issue::TypeExpectedObjectPropAccess;
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr'] ?? null
            ))->getClassList(false, $expected_type_categories, $expected_issue);
        } catch (CodeBaseException $exception) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassReference)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $exception->getFQSEN() ]
                )
            );
        }

        $class = \reset($class_list);

        if (!($class instanceof Clazz)) {
            // empty list
            throw new UnanalyzableException(
                $this->node,
                "Could not get class name from node"
            );
        }

        $flags = 0;
        if ($this->node->kind == ast\AST_STATIC_PROP) {
            $flags |= ast\flags\MODIFIER_STATIC;
        }

        $property_fqsen = FullyQualifiedPropertyName::make(
            $class->getFQSEN(),
            $property_name
        );

        // Otherwise, we'll create it
        $property = new Property(
            $this->context,
            $property_name,
            new UnionType(),
            $flags,
            $property_fqsen
        );

        $class->addProperty($this->code_base, $property, new None);

        return $property;
    }

    /**
     * @return GlobalConstant
     * Get the (non-class) constant associated with this node
     * in this context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     */
    public function getConst() : GlobalConstant
    {
        $node = $this->node;
        \assert(
            $node instanceof ast\Node,
            '$node must be a node'
        );

        \assert(
            $node->kind === ast\AST_CONST,
            "Node must be of type ast\AST_CONST"
        );

        if ($node->children['name']->kind !== ast\AST_NAME) {
            throw new NodeException(
                $node,
                "Can't determine constant name"
            );
        }

        $constant_name =
            $node->children['name']->children['name'];
        $constant_name_lower = \strtolower($constant_name);
        if ($constant_name_lower === 'true' || $constant_name_lower === 'false' || $constant_name_lower === 'null') {
            return $this->code_base->getGlobalConstantByFQSEN(
                FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
                    $constant_name_lower
                )
            );
        }

        // TODO: could speed up looking up reserved words such as null, true, false
        $fqsen = FullyQualifiedGlobalConstantName::fromStringInContext(
            $constant_name,
            $this->context
        );

        if (!$this->code_base->hasGlobalConstantWithFQSEN($fqsen)) {
            $fqsen = FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
                $constant_name
            );

            if (!$this->code_base->hasGlobalConstantWithFQSEN($fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredConstant)(
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        [ $fqsen ]
                    )
                );
            }
        }

        $constant = $this->code_base->getGlobalConstantByFQSEN($fqsen);

        if ($constant->isNSInternal($this->code_base)
            && !$constant->isNSInternalAccessFromContext(
                $this->code_base,
                $this->context
            )
        ) {
            throw new IssueException(
                Issue::fromType(Issue::AccessConstantInternal)(
                    $this->context->getFile(),
                    $node->lineno ?? 0,
                    [
                        (string)$constant->getFQSEN(),
                        $constant->getElementNamespace($this->code_base),
                        $constant->getFileRef()->getFile(),
                        $constant->getFileRef()->getLineNumberStart(),
                        $this->context->getNamespace()
                    ]
                )
            );
        }

        return $constant;
    }

    /**
     * @return ClassConstant
     * Get the (non-class) constant associated with this node
     * in this context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws UnanalyzableException
     * An exception is thrown if we hit a construct in which
     * we can't determine if the property exists or not
     *
     * @throws IssueException
     * An exception is thrown if an issue is found while getting
     * the list of possible classes.
     */
    public function getClassConst() : ClassConstant
    {
        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        \assert(
            $this->node->kind === ast\AST_CLASS_CONST,
            "Node must be of type ast\AST_CLASS_CONST"
        );

        $constant_name = $this->node->children['const'];

        $class_fqsen = null;

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['class']
            ))->getClassList(false, self::CLASS_LIST_ACCEPT_OBJECT_OR_CLASS_NAME);
        } catch (CodeBaseException $exception) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassConstant)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $constant_name, $exception->getFQSEN() ]
                )
            );
        }

        foreach ($class_list as $class) {
            // Remember the last analyzed class for the next issue message
            $class_fqsen = $class->getFQSEN();

            // Check to see if the class has the constant
            if (!$class->hasConstantWithName(
                $this->code_base,
                $constant_name
            )) {
                continue;
            }

            $constant = $class->getConstantByNameInContext(
                $this->code_base,
                $constant_name,
                $this->context
            );

            if ($constant->isNSInternal($this->code_base)
                && !$constant->isNSInternalAccessFromContext(
                    $this->code_base,
                    $this->context
                )
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessClassConstantInternal)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [
                            (string)$constant->getFQSEN(),
                            $constant->getFileRef()->getFile(),
                            $constant->getFileRef()->getLineNumberStart(),
                        ]
                    )
                );
            }

            return $constant;
        }

        // If no class is found, we'll emit the error elsewhere
        if ($class_fqsen) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredConstant)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ "$class_fqsen::$constant_name" ]
                )
            );
        }

        throw new NodeException(
            $this->node,
            "Can't figure out constant {$constant_name} in node"
        );
    }

    /**
     * @return string
     * A unique and stable name for an anonymous class
     */
    public function getUnqualifiedNameForAnonymousClass() : string
    {
        \assert(
            $this->node instanceof ast\Node,
            '$this->node must be a node'
        );

        \assert(
            (bool)($this->node->flags & ast\flags\CLASS_ANONYMOUS),
            "Node must be an anonymous class node"
        );

        $class_name = 'anonymous_class_'
            . \substr(\md5(\implode('|', [
                $this->context->getFile(),
                $this->context->getLineNumberStart()
            ])), 0, 8);

        return $class_name;
    }

    /**
     * @return Func
     */
    public function getClosure() : Func
    {
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context,
                $this->node
            );

        if (!$this->code_base->hasFunctionWithFQSEN($closure_fqsen)) {
            throw new CodeBaseException(
                $closure_fqsen,
                "Could not find closure $closure_fqsen"
            );
        }

        return $this->code_base->getFunctionByFQSEN($closure_fqsen);
    }

    /**
     * Perform some backwards compatibility checks on a node.
     * This ignores union types, and can be run in the parse phase.
     * (It often should, because outside quick mode, it may be run multiple times per node)
     *
     * @return void
     */
    public function analyzeBackwardCompatibility()
    {
        if (!Config::get_backward_compatibility_checks()) {
            return;
        }

        if (!($this->node instanceof ast\Node) || empty($this->node->children['expr'])) {
            return;
        }

        if ($this->node->kind === ast\AST_STATIC_CALL ||
           $this->node->kind === ast\AST_METHOD_CALL) {
            return;
        }

        $llnode = $this->node;

        if ($this->node->kind !== ast\AST_DIM) {
            if (!($this->node->children['expr'] instanceof Node)) {
                return;
            }

            if ($this->node->children['expr']->kind !== ast\AST_DIM) {
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $this->node->children['expr']
                ))->analyzeBackwardCompatibility();
                return;
            }

            $temp = $this->node->children['expr']->children['expr'];
            $llnode = $this->node->children['expr'];
            $lnode = $temp;
        } else {
            $temp = $this->node->children['expr'];
            $lnode = $temp;
        }

        // Strings can have DIMs, it turns out.
        if (!($temp instanceof Node)) {
            return;
        }

        if (!($temp->kind == ast\AST_PROP
            || $temp->kind == ast\AST_STATIC_PROP
        )) {
            return;
        }

        while ($temp instanceof Node
            && ($temp->kind == ast\AST_PROP
            || $temp->kind == ast\AST_STATIC_PROP)
        ) {
            $llnode = $lnode;
            $lnode = $temp;

            // Lets just hope the 0th is the expression
            // we want
            $temp = \array_values($temp->children)[0];
        }

        if (!($temp instanceof Node)) {
            return;
        }

        // Foo::$bar['baz'](); is a problem
        // Foo::$bar['baz'] is not
        if ($lnode->kind === ast\AST_STATIC_PROP
            && $this->node->kind !== ast\AST_CALL
        ) {
            return;
        }

        // $this->$bar['baz']; is a problem
        // $this->bar['baz'] is not
        if ($lnode->kind === ast\AST_PROP
            && !($lnode->children['prop'] instanceof Node)
            && !($llnode->children['prop'] instanceof Node)
        ) {
            return;
        }

        if ((
                (
                    $lnode->children['prop'] instanceof Node
                    && $lnode->children['prop']->kind == ast\AST_VAR
                )
                ||
                (
                    !empty($lnode->children['class'])
                    && $lnode->children['class'] instanceof Node
                    && (
                        $lnode->children['class']->kind == ast\AST_VAR
                        || $lnode->children['class']->kind == ast\AST_NAME
                    )
                )
                ||
                (
                    !empty($lnode->children['expr'])
                    && $lnode->children['expr'] instanceof Node
                    && (
                        $lnode->children['expr']->kind == ast\AST_VAR
                        || $lnode->children['expr']->kind == ast\AST_NAME
                    )
                )
            )
            &&
            (
                $temp->kind == ast\AST_VAR
                || $temp->kind == ast\AST_NAME
            )
        ) {
            $cache_entry = FileCache::getOrReadEntry($this->context->getFile());
            $line = $cache_entry->getLine($this->node->lineno);
            \assert(\is_string($line));
            unset($cache_entry);
            if (strpos($line, '}[') === false
                || strpos($line, ']}') === false
                || strpos($line, '>{') === false
            ) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::CompatiblePHP7,
                    $this->node->lineno ?? 0
                );
            }
        }
    }

    /**
     * @return ?FullyQualifiedClassName
     * @throws IssueException if the list of possible classes couldn't be determined.
     */
    public function resolveClassNameInContext()
    {
        // A function argument to resolve into an FQSEN
        $arg = $this->node;

        if (\is_string($arg)) {
            // Class_alias treats arguments as fully qualified strings.
            return FullyQualifiedClassName::fromFullyQualifiedString($arg);
        }
        if ($arg instanceof Node
            && $arg->kind === ast\AST_CLASS_CONST
            && \strcasecmp($arg->children['const'], 'class') === 0
        ) {
            $class_type = (new ContextNode(
                $this->code_base,
                $this->context,
                $arg->children['class']
            ))->getClassUnionType();

            // If we find a class definition, then return it. There should be 0 or 1.
            // (Expressions such as 'int::class' are syntactically valid, but would have 0 results).
            foreach ($class_type->asClassFQSENList($this->context) as $class_fqsen) {
                return $class_fqsen;
            }
        }

        $class_name = $this->getEquivalentPHPScalarValue();
        // TODO: Emit
        if (\is_string($class_name)) {
            if (\preg_match('/^\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\]*$/', $class_name)) {
                return FullyQualifiedClassName::fromFullyQualifiedString($class_name);
            }
        }

        return null;
    }

    // Flags for getEquivalentPHPValue

    // Should this attempt to resolve arrays?
    const RESOLVE_ARRAYS = (1<<0);
    // Should this attempt to resolve array keys?
    const RESOLVE_ARRAY_KEYS = (1<<1);
    // Should this attempt to resolve array values?
    const RESOLVE_ARRAY_VALUES = (1<<2);
    // Should this attempt to resolve accesses to constants?
    const RESOLVE_CONSTANTS = (1<<3);
    // If resolving array keys fails, should this use a placeholder?
    const RESOLVE_KEYS_USE_FALLBACK_PLACEHOLDER = (1<<4);
    // Skip unknown keys
    const RESOLVE_KEYS_SKIP_UNKNOWN_KEYS = (1<<5);

    const RESOLVE_DEFAULT =
        self::RESOLVE_ARRAYS |
        self::RESOLVE_ARRAY_KEYS |
        self::RESOLVE_ARRAY_VALUES |
        self::RESOLVE_CONSTANTS |
        self::RESOLVE_KEYS_USE_FALLBACK_PLACEHOLDER;

    const RESOLVE_SCALAR_DEFAULT =
        self::RESOLVE_CONSTANTS |
        self::RESOLVE_KEYS_USE_FALLBACK_PLACEHOLDER;

    /**
     * @param Node[]|string[]|float[]|int[] $children
     * @param int $flags - See self::RESOLVE_*
     * @return ?array - array if elements could be resolved.
     */
    private function getEquivalentPHPArrayElements(array $children, int $flags)
    {
        $elements = [];
        foreach ($children as $child_node) {
            $key_node = ($flags & self::RESOLVE_ARRAY_KEYS) != 0 ? $child_node->children['key'] : null;
            $value_node = $child_node->children['value'];
            if (self::RESOLVE_ARRAY_VALUES) {
                $value_node = $this->getEquivalentPHPValueForNode($value_node, $flags);
            }
            // NOTE: this has some overlap with DuplicateKeyPlugin
            if ($key_node === null) {
                $elements[] = $value_node;
            } elseif (\is_scalar($key_node)) {
                $elements[$key_node] = $value_node;  // Check for float?
            } else {
                $key = $this->getEquivalentPHPValueForNode($key_node, $flags);
                if (is_scalar($key)) {
                    $elements[$key] = $value_node;
                } else {
                    if (($flags & self::RESOLVE_KEYS_USE_FALLBACK_PLACEHOLDER) !== 0) {
                        $elements[] = $value_node;
                    } else {
                        // TODO: Alternate strategies?
                        return null;
                    }
                }
            }
        }
        return $elements;
    }

    /**
     * This converts an AST node in context to the value it represents.
     * This is useful for plugins, etc, and will gradually improve.
     *
     * @see $this->getEquivalentPHPValue
     *
     * @param Node|float|int|string $node
     * @return Node|Node[]|string[]|int[]|float[]|string|float|int|bool|null - If this could be resolved and we're certain of the value, this gets an equivalent definition. Otherwise, this returns $node.
     */
    private function getEquivalentPHPValueForNode($node, int $flags)
    {
        if (!($node instanceof Node)) {
            return $node;
        }
        $kind = $node->kind;
        if ($kind === ast\AST_ARRAY) {
            if (($flags & self::RESOLVE_ARRAYS) === 0) {
                return $node;
            }
            $elements = $this->getEquivalentPHPArrayElements($node->children, $flags);
            if ($elements === null) {
                // Attempted to resolve elements but failed at one or more elements.
                return $node;
            }
            return $elements;
        } elseif ($kind === ast\AST_CONST) {
            $name = $node->children['name']->children['name'] ?? null;
            if (\is_string($name)) {
                switch (\strtolower($name)) {
                    case 'false':
                        return false;
                    case 'true':
                        return true;
                    case 'null':
                        return null;
                }
            }
            if (($flags & self::RESOLVE_CONSTANTS) === 0) {
                return $node;
            }
            try {
                $constant = (new ContextNode($this->code_base, $this->context, $node))->getConst();
            } catch (\Exception $e) {
                return $node;
            }
            // TODO: Recurse, but don't try to resolve constants again
            $new_node = $constant->getNodeForValue();
            if (is_object($new_node)) {
                // Avoid infinite recursion, only resolve once
                $new_node = $this->getEquivalentPHPValueForNode($new_node, $flags & ~self::RESOLVE_CONSTANTS);
            }
            return $new_node;
        } elseif ($kind === ast\AST_CLASS_CONST) {
            if (($flags & self::RESOLVE_CONSTANTS) === 0) {
                return $node;
            }
            try {
                $constant = (new ContextNode($this->code_base, $this->context, $node))->getClassConst();
            } catch (\Exception $e) {
                return $node;
            }
            // TODO: Recurse, but don't try to resolve constants again
            $new_node = $constant->getNodeForValue();
            if (is_object($new_node)) {
                // Avoid infinite recursion, only resolve once
                $new_node = $this->getEquivalentPHPValueForNode($new_node, $flags & ~self::RESOLVE_CONSTANTS);
            }
            return $new_node;
        } elseif ($kind === ast\AST_MAGIC_CONST) {
            return $this->getValueForMagicConstByNode($node);
        }
        return $node;
    }

    public function getValueForMagicConst()
    {
        assert($this->node->kind === ast\AST_MAGIC_CONST);
        return $this->getValueForMagicConstByNode($this->node);
    }

    public function getValueForMagicConstByNode(Node $node)
    {
        $context = $this->context;
        switch ($node->flags) {
            case ast\flags\MAGIC_CLASS:
                if ($context->isInClassScope()) {
                    return (string)$context->getClassFQSEN();
                }
                return $node;
            case ast\flags\MAGIC_FUNCTION:
                if ($context->isInFunctionLikeScope()) {
                    $fqsen = $context->getFunctionLikeFQSEN();
                    return $fqsen->isClosure() ? '{closure}' : $fqsen->getName();
                }
                return $node;
            case ast\flags\MAGIC_METHOD:
                // TODO: Is this right?
                if ($context->isInMethodScope()) {
                    return \ltrim((string)$context->getFunctionLikeFQSEN(), '\\');
                }
                return $node;
            case ast\flags\MAGIC_DIR:
                // TODO: Absolute directory?
                return \dirname($context->getFile());
            case ast\flags\MAGIC_FILE:
                return $context->getFile();
            case ast\flags\MAGIC_LINE:
                return $node->lineno ?? $context->getLineNumberStart();
            case ast\flags\MAGIC_NAMESPACE:
                return \ltrim($context->getNamespace(), '\\');
            case ast\flags\MAGIC_TRAIT:
                // TODO: Could check if in trait, low importance.
                if ($context->isInClassScope()) {
                    return (string)$context->getClassFQSEN();
                }
                return $node;
        }
        return $node;
    }

    /**
     * This converts an AST node in context to the value it represents.
     * This is useful for plugins, etc, and will gradually improve.
     *
     * This does not create new object instances.
     *
     * @return Node|string[]|int[]|float[]|string|float|int|bool|null - If this could be resolved and we're certain of the value, this gets an equivalent definition. Otherwise, this returns $node.
     * @throws InvalidArgumentException if the object could not be determined - Callers must catch this.
     */
    public function getEquivalentPHPValue(int $flags = self::RESOLVE_DEFAULT)
    {
        return $this->getEquivalentPHPValueForNode($this->node, $flags);
    }

    /**
     * This converts an AST node in context to the value it represents.
     * This is useful for plugins, etc, and will gradually improve.
     *
     * This does not create new object instances.
     *
     * @return Node||string|float|int|bool|null - If this could be resolved and we're certain of the value, this gets an equivalent definition. Otherwise, this returns $node. If this would be an array, this returns $node.
     *
     * @throws InvalidArgumentException if the object could not be determined - Callers must catch this.
     */
    public function getEquivalentPHPScalarValue()
    {
        return $this->getEquivalentPHPValueForNode($this->node, self::RESOLVE_SCALAR_DEFAULT);
    }
}
