<?php

declare(strict_types=1);

namespace React\Promise;

/**
 * @template T
 */
class Deferred
{
    public function __construct()
    {
    }
}

namespace PromotedGeneric;

use React\Promise\Deferred;

class Example
{
    /**
     * @param Deferred<int> $deferred
     */
    public function __construct(
        protected Deferred $deferred = new Deferred()
    ) {
    }
}
