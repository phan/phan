<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\Type;
use Phan\Language\UnionType;
use ast\Node;

class Variable extends UnaddressableTypedElement
{
    /**
     * @access private
     * @var array<string,string> - Maps from a built in superglobal name to a UnionType spec string.
     * The string array keys **can** be integers in edge cases, but those should be rare.
     * (e.g. ${4} = 'x'; adds 4 to $GLOBALS.
     * And user-provided input of integer representations of strings as keys would also become integers.
     */
    const _BUILTIN_SUPERGLOBAL_TYPES = [
        '_GET' => 'array<string,string|string[]>',
        '_POST' => 'array<string,string|string[]>',
        '_COOKIE' => 'array<string,string|string[]>',
        '_REQUEST' => 'array<string,string|string[]>',
        '_SERVER' => 'array<string,mixed>',
        '_ENV' => 'array<string,string>',
        '_FILES' => 'array<string,array<string,int|string|array<string,int|string>>>',  // Can have multiple files with the same name.
        '_SESSION' => 'array<string,mixed>',
        'GLOBALS' => 'array<string,mixed>',
        'http_response_header' => 'array<int,string>|null', // Revisit when we implement sub-block type refining
    ];

    /**
     * @var array<string,string>
     *
     * NOTE: The string array keys of superglobals **can** be integers in edge cases, but those should be rare.
     * (e.g. ${4} = 'x'; adds 4 to $GLOBALS.
     * And user-provided input of integer representations of strings as keys would also become integers.
     */
    const _BUILTIN_GLOBAL_TYPES = [
        '_GET' => 'array<string,string|string[]>',
        '_POST' => 'array<string,string|string[]>',
        '_COOKIE' => 'array<string,string|string[]>',
        '_REQUEST' => 'array<string,string|string[]>',
        '_SERVER' => 'array<string,mixed>',
        '_ENV' => 'array<string,string>',
        '_FILES' => 'array<string,array<string,int>>|array<string,array<string,string>>|array<string,array<string,array<int,int>>>|array<string,array<string,array<int,string>>>',  // Can have multiple files with the same name.
        '_SESSION' => 'array<string,mixed>',
        'GLOBALS' => 'array<string,mixed>',
        'http_response_header' => 'array<int,string>|null', // Revisit when we implement sub-block type refining
        'argv' => 'array<int,string>',
        'argc' => 'int',
    ];

    /**
     * @param FileRef $file_ref
     * The file and lines in which the unaddressable element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        FileRef $file_ref,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $file_ref,
            $name,
            $type,
            $flags
        );
    }

    /**
     * @return bool
     * This will always return false in so far as variables
     * cannot be passed by reference.
     */
    public function isPassByReference()
    {
        return false;
    }

    /**
     * @return bool
     * This will always return false in so far as variables
     * cannot be variadic
     */
    public function isVariadic()
    {
        return false;
    }

    /**
     * Stub for compatibility with Parameter, since we replace the Parameter with a Variable and call setParameterList in PostOrderAnalysisVisitor->visitStaticCall
     * TODO: Should that code create a new Parameter instance instead?
     * @return static
     */
    public function asNonVariadic()
    {
        return $this;
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
    ) : Variable {

        $variable_name = (new ContextNode(
            $code_base,
            $context,
            $node
        ))->getVariableName();


        // Get the type of the assignment
        $union_type = $should_check_type
            ? UnionType::fromNode($context, $code_base, $node)
            : UnionType::empty();

        $variable = new Variable(
            $context
                ->withLineNumberStart($node->lineno ?? 0),
            $variable_name,
            $union_type,
            $node->flags ?? 0
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
    ) : bool {
        if (\array_key_exists($name, self::_BUILTIN_SUPERGLOBAL_TYPES)) {
            return true;
        }
        return \in_array($name, Config::getValue('runkit_superglobals'));
    }

    /**
     * Returns true for all superglobals and variables in globals_type_map.
     */
    public static function isHardcodedGlobalVariableWithName(
        string $name
    ) : bool {
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
    ) : bool {
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
    ) {
        if (\array_key_exists($name, self::_BUILTIN_GLOBAL_TYPES)) {
            // More efficient than using context.
            return UnionType::fromFullyQualifiedString(self::_BUILTIN_GLOBAL_TYPES[$name]);
        }

        if (\array_key_exists($name, Config::getValue('globals_type_map'))
            || \in_array($name, Config::getValue('runkit_superglobals'))
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
    public function getNonVariadicUnionType() : UnionType
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

    public function __toString() : string
    {
        $string = '';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= "{$this->getUnionType()} ";
        }

        return "$string\${$this->getName()}";
    }
}
