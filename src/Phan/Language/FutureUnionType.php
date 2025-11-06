<?php

declare(strict_types=1);

namespace Phan\Language;

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\IssueException;

/**
 * A FutureUnionType is a UnionType that is lazily loaded.
 * Call `get()` in order force the type to be figured.
 */
class FutureUnionType
{

    /** @var CodeBase The code base within which we're operating */
    private $code_base;

    /** @var Context the context from which we're fetching types for $this->node */
    private $context;

    /** @var Node|string|int|bool|float the node which we will be fetching the type of. */
    private $node;

    /** @var bool whether this future type is analyzing a default parameter value */
    private $is_default_value;

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param Node|string|int|bool|float $node
     * @param bool $is_default_value whether this is analyzing a default parameter value
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        $node,
        bool $is_default_value = false
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->node = $node;
        $this->is_default_value = $is_default_value;
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
    public function get(): UnionType
    {
        $this->context->clearCachedUnionTypes();

        // Temporarily set a flag to indicate we're analyzing a default value
        if ($this->is_default_value) {
            $previous = UnionTypeVisitor::setAnalyzingDefaultValue(true);
        }

        try {
            return UnionTypeVisitor::unionTypeFromNode(
                $this->code_base,
                $this->context,
                $this->node,
                false
            );
        } finally {
            if ($this->is_default_value) {
                UnionTypeVisitor::setAnalyzingDefaultValue($previous ?? false);
            }
        }
    }

    /**
     * Gets the codebase singleton which created this FutureUnionType.
     * (used to resolve class references, constants, etc.)
     * @internal (May rethink exposing the codebase in the future)
     */
    public function getCodebase(): CodeBase
    {
        return $this->code_base;
    }

    /**
     * Gets the context in which this FutureUnionType was created
     * (used to resolve class references, constants, etc.)
     * @internal (May rethink exposing the codebase in the future)
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return bool whether this future type is analyzing a default parameter value
     * @internal
     */
    public function isDefaultValue(): bool
    {
        return $this->is_default_value;
    }

    /**
     * Gets the node which this is based on
     */
    public function getNode() : Node|bool|float|int|string
    {
        return $this->node;
    }
}
