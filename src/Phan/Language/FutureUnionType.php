<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\CodeBase;
use \Phan\Exception\CodeBaseException;
use \Phan\Language\Context;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A FutureUnionType is a UnionType that is lazily loaded.
 * Call `get()` in order force the type to be figured.
 */
class FutureUnionType {

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

    public function get() : UnionType {
        try {
            return UnionType::fromNode(
                $this->context,
                $this->code_base,
                $this->node
            );
        } catch (CodeBaseException $exception) {
            Log::err(
                Log::EUNDEF,
                $exception->getMessage(),
                $this->context->getFile(),
                $this->node->lineno
            );

            return new UnionType();
        }
    }
}
