<?php declare(strict_types=1);
namespace Phan\Library;

use Closure;

/**
 * Implements Resource Acquizition Is Initialization.
 * An unused variable in the local scope can be used to call this.
 *
 * Note: This assumes that the garbage collector eagerly calls __destruct.
 * This may not be the case in alternate PHP implementations.
 */
class RAII
{
    /** @var Closure():void */
    private $finalizer;

    /**
     * @param Closure():void $finalizer - Should not throw an exception. It may be called in __destruct()
     */
    public function __construct(Closure $finalizer)
    {
        $this->finalizer = $finalizer;
    }

    /**
     * @return void
     */
    public function callFinalizerOnce()
    {
        $finalizer = $this->finalizer;
        if ($finalizer) {
            $finalizer();
        }
    }

    public function __destruct()
    {
        $this->callFinalizerOnce();
    }
}
