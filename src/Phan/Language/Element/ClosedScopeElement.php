<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\Scope\ClosedScope;

/**
 * A trait for closed scope elements (classes, functions, methods,
 * closures).
 */
trait ClosedScopeElement
{

    /**
     * @var ClosedScope
     */
    private $internal_scope;

    /**
     * @return void
     */
    public function setInternalScope(ClosedScope $internal_scope)
    {
        $this->internal_scope = $internal_scope;
    }

    /**
     * @return ClosedScope
     * The internal scope of this closed scope element
     */
    public function getInternalScope() : ClosedScope
    {
        return $this->internal_scope;
    }
}
