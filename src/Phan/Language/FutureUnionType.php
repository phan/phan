<?php declare(strict_types=1);
namespace Phan\Language;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use ast\Node;

/**
 * A FutureUnionType is a UnionType that is lazily loaded.
 * Call `get()` in order force the type to be figured.
 */
class FutureUnionType
{

    /** @var CodeBase */
    private $code_base;

    /** @var Context */
    private $context;

    /** @var Node|string|int|bool|float */
    private $node;

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param Node|string|int|bool|float $node
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
     * Force the future to figure out the type of the
     * given object or throw an IssueException if it
     * is unable to do so
     *
     * @return UnionType
     * The type of the future
     *
     * @throws IssueException
     * An exception is thrown if we are unable to determine
     * the type at the time this method is called
     */
    public function get() : UnionType
    {
        return UnionType::fromNode(
            $this->context,
            $this->code_base,
            $this->node,
            false
        );
    }
}
