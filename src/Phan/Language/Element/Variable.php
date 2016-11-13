<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\UnionType;
use ast\Node;

class Variable extends TypedElement
{
    /**
     * @access private
     * @var string[] - Maps from a built in superglobal name to a UnionType spec string.
     */
    const _BUILTIN_SUPERGLOBAL_TYPES = [
        'argv' => 'string[]',
        'argc' => 'int',
        '_GET' => 'string[]|string[][]',
        '_POST' => 'string[]|string[][]',
        '_COOKIE' => 'string[]|string[][]',
        '_REQUEST' => 'string[]|string[][]',
        '_SERVER' => 'array',
        '_ENV' => 'string[]',
        '_FILES' => 'int[][]|string[][]|int[][][]|string[][][]',  // Can have multiple files with the same name.
        '_SESSION' => 'array',
        'GLOBALS' => 'array',
        'http_response_header' => 'string[]|null' // Revisit when we implement sub-block type refining
    ];

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
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
            : new UnionType();

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
     */
    public static function isSuperglobalVariableWithName(
        string $name
    ) : bool {
        if (array_key_exists($name, self::_BUILTIN_SUPERGLOBAL_TYPES)) {
            return true;
        }
        return isset(Config::get()->runkit_superglobals_map[$name]);
    }

    /**
     * @return UnionType|null
     * Returns UnionType (Possible with empty set) if the variable with the given name is a superglobal.
     * Returns null otherwise.
     */
    public static function getUnionTypeOfSuperglobalVariableWithName(
        string $name
    ) {
        $typeString = self::_BUILTIN_SUPERGLOBAL_TYPES[$name] ?? Config::get()->runkit_superglobals_map[$name] ?? null;
        if (is_string($typeString)) {
            return UnionType::fromFullyQualifiedString($typeString);
        }
        return null;
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
