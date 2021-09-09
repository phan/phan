<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of a Variable, as well as methods for accessing and modifying variables.
 *
 * This has subclasses for parameters, etc.
 */
class Variable extends UnaddressableTypedElement implements TypedElementInterface
{
    /**
     * @access private
     * @var array<string,string> - Maps from a built in superglobal name to a UnionType spec string.
     * The string array keys **can** be integers in edge cases, but those should be rare.
     * (e.g. ${4} = 'x'; adds 4 to $GLOBALS.
     * And user-provided input of integer representations of strings as keys would also become integers.
     */
    public const _BUILTIN_SUPERGLOBAL_TYPES = [
        '_GET' => 'array<string,string|string[]>',
        '_POST' => 'array<string,string|string[]>',
        '_COOKIE' => 'array<string,string|string[]>',
        '_REQUEST' => 'array<string,string|string[]>',
        '_SERVER' => 'array<string,mixed>',
        '_ENV' => 'array<string,string>',
        '_FILES' => 'array<string,array<string,int|string|array<string,int|string>>>',  // Can have multiple files with the same name.
        '_SESSION' => 'array<string,mixed>',
        'GLOBALS' => 'array<string,mixed>',
        'http_response_header' => 'list<string>|null', // Revisit when we implement sub-block type refining
    ];

    /**
     * @var array<string,string>
     * @internal this will be protected in a future release
     *
     * NOTE: The string array keys of superglobals **can** be integers in edge cases, but those should be rare.
     * (e.g. ${4} = 'x'; adds 4 to $GLOBALS.
     * And user-provided input of integer representations of strings as keys would also become integers.
     */
    public const _BUILTIN_GLOBAL_TYPES = [
        '_GET' => 'array<string,string|string[]>',
        '_POST' => 'array<string,string|string[]>',
        '_COOKIE' => 'array<string,string|string[]>',
        '_REQUEST' => 'array<string,string|string[]>',
        '_SERVER' => 'array<string,mixed>',
        '_ENV' => 'array<string,string>',
        '_FILES' => 'array<string,array<string,int>>|array<string,array<string,string>>|array<string,array<string,list<int>>>|array<string,array<string,list<string>>>',  // Can have multiple files with the same name.
        '_SESSION' => 'array<string,mixed>',
        'GLOBALS' => 'array<string,mixed>',
        'http_response_header' => 'list<string>|null', // Revisit when we implement sub-block type refining
        'argv' => 'list<string>',
        'argc' => 'int',
    ];

    /**
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags
        );
    }
     */

    /**
     * @return bool
     * This will always return false in so far as variables
     * cannot be passed by reference.
     * @suppress PhanUnreferencedPublicMethod this is added for convenience for plugins
     */
    public function isPassByReference(): bool
    {
        return false;
    }

    /**
     * @return bool
     * This will always return false in so far as variables
     * cannot be variadic
     * @suppress PhanUnreferencedPublicMethod this may be useful in the future.
     */
    public function isVariadic(): bool
    {
        return false;
    }

    /**
     * @param Node $node
     * An AST_VAR node
     *
     * @param Context $context
     * The context in which the variable is found
     *
     * @param CodeBase $code_base
     *
     * @return Variable
     * A variable begotten from a node
     *
     * @throws IssueException
     */
    public static function fromNodeInContext(
        Node $node,
        Context $context,
        CodeBase $code_base,
        bool $should_check_type = true
    ): Variable {

        $variable_name = (new ContextNode(
            $code_base,
            $context,
            $node
        ))->getVariableName();

        $scope = $context->getScope();
        $variable = $scope->getVariableByNameOrNull($variable_name);
        if ($variable) {
            return clone($variable);
        }

        // Get the type of the assignment
        $union_type = $should_check_type
            ? UnionTypeVisitor::unionTypeFromNode($code_base, $context, $node)
            : UnionType::empty();

        $variable = new Variable(
            $context->withLineNumberStart($node->lineno),
            $variable_name,
            $union_type,
            0
        );

        return $variable;
    }

    /**
     * @return bool
     * True if the variable with the given name is a
     * superglobal
     * Implies Variable::isHardcodedGlobalVariableWithName($name) is true
     */
    public static function isSuperglobalVariableWithName(
        string $name
    ): bool {
        if (\array_key_exists($name, self::_BUILTIN_SUPERGLOBAL_TYPES)) {
            return true;
        }
        return \in_array($name, Config::getValue('runkit_superglobals'), true);
    }

    /**
     * Is $name a valid variable identifier?
     */
    public static function isValidIdentifier(
        string $name
    ): bool {
        return \preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $name) > 0;
    }

    /**
     * Returns true for all superglobals and variables in globals_type_map.
     */
    public static function isHardcodedGlobalVariableWithName(
        string $name
    ): bool {
        return self::isSuperglobalVariableWithName($name) ||
            \array_key_exists($name, self::_BUILTIN_GLOBAL_TYPES) ||
            \array_key_exists($name, Config::getValue('globals_type_map'));
    }

    /**
     * Returns true for all superglobals (if is_in_global_scope, also for variables in globals_type_map/built in globals)
     */
    public static function isHardcodedVariableInScopeWithName(
        string $name,
        bool $is_in_global_scope
    ): bool {
        return $is_in_global_scope ? self::isHardcodedGlobalVariableWithName($name)
                                   : self::isSuperglobalVariableWithName($name);
    }

    /**
     * @return ?UnionType
     * Returns UnionType (Possible with empty set) if and only
     * if isHardcodedGlobalVariableWithName is true. Returns null
     * otherwise.
     */
    public static function getUnionTypeOfHardcodedGlobalVariableWithName(
        string $name
    ): ?UnionType {
        if (\array_key_exists($name, self::_BUILTIN_GLOBAL_TYPES)) {
            // More efficient than using context.
            // Note that global constants can be modified by user code
            return UnionType::fromFullyQualifiedPHPDocString(self::_BUILTIN_GLOBAL_TYPES[$name]);
        }

        if (\array_key_exists($name, Config::getValue('globals_type_map'))
            || \in_array($name, Config::getValue('runkit_superglobals'), true)
        ) {
            $type_string = Config::getValue('globals_type_map')[$name] ?? '';
            // Want to allow 'resource' or 'mixed' as a type here,
            return UnionType::fromStringInContext($type_string, new Context(), Type::FROM_PHPDOC);
        }

        return null;
    }

    /**
     * @return ?UnionType
     * Returns UnionType (Possible with empty set) if and only
     * if isHardcodedVariableInScopeWithName is true. Returns null
     * otherwise.
     */
    public static function getUnionTypeOfHardcodedVariableInScopeWithName(
        string $name,
        bool $is_in_global_scope
    ): ?UnionType {
        if (\array_key_exists($name, $is_in_global_scope ? self::_BUILTIN_GLOBAL_TYPES : self::_BUILTIN_SUPERGLOBAL_TYPES)) {
            // More efficient than using context.
            // Note that global constants can be modified by user code
            return UnionType::fromFullyQualifiedPHPDocString(self::_BUILTIN_GLOBAL_TYPES[$name]);
        }

        if (($is_in_global_scope && \array_key_exists($name, Config::getValue('globals_type_map')))
            || \in_array($name, Config::getValue('runkit_superglobals'), true)
        ) {
            $type_string = Config::getValue('globals_type_map')[$name] ?? '';
            // Want to allow 'resource' or 'mixed' as a type here,
            return UnionType::fromStringInContext($type_string, new Context(), Type::FROM_PHPDOC);
        }

        return null;
    }

    /**
     * Variables can't be variadic. This is the same as
     * getUnionType for variables, but not necessarily
     * for subclasses. Method will return the element
     * type (such as `DateTime`) for variadic parameters.
     */
    public function getNonVariadicUnionType(): UnionType
    {
        return parent::getUnionType();
    }

    /**
     * @return static - A clone of this object, where isVariadic() is false
     * Used for analyzing the context **inside** of this method
     */
    public function cloneAsNonVariadic()
    {
        return clone($this);
    }

    public function __toString(): string
    {
        $string = '';

        if (!$this->type->isEmpty()) {
            $string .= "{$this->type} ";
        }

        return "$string\${$this->getName()}";
    }

    /**
     * Returns a representation that can be used to debug issues with union types.
     * The representation may change - this should not be used for issue messages, etc.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getDebugRepresentation(): string
    {
        $string = '';

        if (!$this->type->isEmpty() || $this->type->isPossiblyUndefined()) {
            $string .= "{$this->type->getDebugRepresentation()} ";
        }

        return "$string\${$this->getName()}";
    }

    /**
     * Determine which issue type should be used when Phan finds an undefined var
     *
     * @param Context $context
     * @param string $variable_name
     */
    public static function chooseIssueForUndeclaredVariable(Context $context, string $variable_name): string
    {
        if ($variable_name === 'this') {
            return Issue::UndeclaredThis;
        }

        return $context->isInGlobalScope() ? Issue::UndeclaredGlobalVariable : Issue::UndeclaredVariable;
    }
}
