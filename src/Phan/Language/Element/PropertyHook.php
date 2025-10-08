<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\UnionType;

/**
 * Phan's representation of a property hook (get or set)
 *
 * Property hooks allow custom behavior when reading (get) or writing (set) to properties.
 * They were introduced in PHP 8.4.
 *
 * @see https://wiki.php.net/rfc/property-hooks
 */
class PropertyHook
{
    use HasAttributesTrait;

    /** @var string The hook name: 'get' or 'set' */
    private $name;

    /** @var FullyQualifiedPropertyName The property this hook belongs to */
    private $property_fqsen;

    /** @var list<Parameter> Parameters for the hook (only 'set' hooks typically have params) */
    private $parameter_list = [];

    /** @var ?Node The AST node representing the hook body (statements) */
    private $body_node;

    /** @var int Flags such as MODIFIER_FINAL */
    private $flags;

    /** @var Context The context in which the hook is defined */
    private $context;

    /** @var bool True if this is an abstract/interface hook (no body) */
    private $is_abstract = false;

    /**
     * @param string $name Hook name ('get' or 'set')
     * @param FullyQualifiedPropertyName $property_fqsen The property this belongs to
     * @param list<Parameter> $parameter_list Hook parameters
     * @param ?Node $body_node The hook body AST
     * @param int $flags Hook flags (e.g., final)
     * @param Context $context The definition context
     */
    public function __construct(
        string $name,
        FullyQualifiedPropertyName $property_fqsen,
        array $parameter_list,
        ?Node $body_node,
        int $flags,
        Context $context
    ) {
        $this->name = $name;
        $this->property_fqsen = $property_fqsen;
        $this->parameter_list = $parameter_list;
        $this->body_node = $body_node;
        $this->flags = $flags;
        $this->context = $context;
        $this->is_abstract = ($body_node === null);
    }

    /**
     * @return string The hook name ('get' or 'set')
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool True if this is a 'get' hook
     */
    public function isGetHook(): bool
    {
        return $this->name === 'get';
    }

    /**
     * @return bool True if this is a 'set' hook
     */
    public function isSetHook(): bool
    {
        return $this->name === 'set';
    }

    /**
     * @return FullyQualifiedPropertyName The property this hook belongs to
     */
    public function getPropertyFQSEN(): FullyQualifiedPropertyName
    {
        return $this->property_fqsen;
    }

    /**
     * @return list<Parameter> The hook's parameters
     */
    public function getParameterList(): array
    {
        return $this->parameter_list;
    }

    /**
     * @return ?Node The hook body AST node
     */
    public function getBodyNode(): ?Node
    {
        return $this->body_node;
    }

    /**
     * @return int Hook flags
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * @return Context The context where this hook is defined
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return bool True if this hook is marked as final
     */
    public function isFinal(): bool
    {
        return ($this->flags & \ast\flags\MODIFIER_FINAL) !== 0;
    }

    /**
     * @return bool True if this is an abstract hook (interface/abstract class)
     */
    public function isAbstract(): bool
    {
        return $this->is_abstract;
    }

    /**
     * @return bool True if this hook has a body
     */
    public function hasBody(): bool
    {
        return $this->body_node !== null;
    }

    /**
     * Get the union type returned by this hook (for 'get' hooks)
     * or accepted by this hook (for 'set' hooks).
     *
     */
    public function getUnionType(): UnionType
    {
        // For set hooks, return the parameter type
        if ($this->isSetHook() && !empty($this->parameter_list)) {
            return $this->parameter_list[0]->getUnionType();
        }

        // For get hooks, we would need to infer from the body or return statements
        // This is a simplified version - full implementation would analyze the body
        return UnionType::empty();
    }

    /**
     * @return string String representation of this hook
     */
    public function __toString(): string
    {
        $result = '';
        if ($this->isFinal()) {
            $result .= 'final ';
        }
        $result .= $this->name;

        if ($this->isSetHook() && !empty($this->parameter_list)) {
            $param_strings = array_map(static function (Parameter $param): string {
                return $param->__toString();
            }, $this->parameter_list);
            $result .= '(' . implode(', ', $param_strings) . ')';
        }

        return $result;
    }
}
