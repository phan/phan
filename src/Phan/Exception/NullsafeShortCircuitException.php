<?php

declare(strict_types=1);

namespace Phan\Exception;

/**
 * Thrown by ContextNode when a method call, property access or array access cannot be analyzed because it is
 * part of a chain of dereferences that PHP short-circuits to null: a nullsafe access (`?->`) in the chain has a
 * receiver that is definitely null, so the member is never accessed (e.g. `$x?->method()` or `$x?->method()->other()`
 * when $x is null).
 *
 * Callers that fall back to marking every member with that name as referenced for an unknown receiver
 * (dead code detection) should not do so for this exception: the access never happens.
 */
class NullsafeShortCircuitException extends NodeException
{
}
