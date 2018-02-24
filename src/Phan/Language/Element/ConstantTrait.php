<?php declare(strict_types=1);
namespace Phan\Language\Element;

use ast\Node;
use Closure;

trait ConstantTrait
{
    /** @var Node|string|float|int */
    protected $defining_node;

    use ElementFutureUnionType;

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
     * @param Node|string|float|int $node Either a node or a constant to be used as the value of the constant.
     * @return void
     */
    public function setNodeForValue($node)
    {
        $this->defining_node = $node;
    }

    /**
     * Gets the node with the AST representing the value of this constant.
     *
     * @return Node|string|float|int
     */
    public function getNodeForValue()
    {
        return $this->defining_node;
    }

    /**
     * @internal - Used by daemon mode to restore an element to the state it had before parsing.
     * @return ?Closure
     */
    public function createRestoreCallback()
    {
        $future_union_type = $this->future_union_type;
        if ($future_union_type === null) {
            // We already inferred the type for this class constant/global constant.
            // Nothing to do.
            return null;
        }
        // If this refers to a class constant in another file,
        // the resolved union type might change if that file changes.
        return function() use ($future_union_type) {
            $this->future_union_type = $future_union_type;
            // Probably don't need to call setUnionType(mixed) again...
        };
    }
}
