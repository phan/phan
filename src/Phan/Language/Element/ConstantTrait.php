<?php declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Closure;

/**
 * Represents functionality common to GlobalConstant and ClassConstant
 * for APIs Phan has for representations of constants.
 *
 * @see ConstantInterface - Classes using this trait use this interface
 */
trait ConstantTrait
{
    use ElementFutureUnionType;

    /** @var Node|string|float|int|resource the node (or built-in value) which defined the value of this constant. */
    protected $defining_node;

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    abstract public function getName() : string;

    public function __toString() : string
    {
        return 'const ' . $this->getName();
    }

    /**
     * Sets the node with the AST representing the value of this constant.
     *
     * @param Node|string|float|int|resource $node Either a node or a constant to be used as the value of the constant.
     * Can be resource for STDERR, etc.
     */
    public function setNodeForValue($node) : void
    {
        $this->defining_node = $node;
    }

    /**
     * Gets the node with the AST representing the value of this constant.
     *
     * @return Node|string|float|int|resource
     */
    public function getNodeForValue()
    {
        return $this->defining_node;
    }

    /**
     * Used by daemon mode to restore an element to the state it had before parsing.
     * @internal
     */
    public function createRestoreCallback() : ?Closure
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            // We already inferred the type for this class constant/global constant.
            // Nothing to do.
            return null;
        }
        // If this refers to a class constant in another file,
        // the resolved union type might change if that file changes.
        return function () use ($future_union_type) : void {
            $this->future_union_type = $future_union_type;
            // Probably don't need to call setUnionType(mixed) again...
        };
    }
}
