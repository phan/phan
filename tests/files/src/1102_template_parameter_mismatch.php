<?php

/**
 * Box interface storing a value of type T.
 *
 * @template T
 */
interface Box
{
    /**
     * Retrieve the stored value.
     * @return T
     */
    public function get();
}

/**
 * Missing template arguments in @implements
 * @implements Box
 */
class MissingBox implements Box
{
    public function get()
    {
        return 42;
    }
}

/**
 * Too many template arguments in @implements
 * @implements Box<int, string>
 */
class TooManyBox implements Box
{
    public function get()
    {
        return 42;
    }
}

/**
 * Trait storing a generic value of type T.
 *
 * @template T
 */
trait Holder
{
    /** @var T Description for Holder value. */
    private $value;
}

/**
 * Missing template arguments in @use
 * @use Holder
 */
class MissingHolder
{
    use Holder;
}

/**
 * Too many template arguments in @use
 * @use Holder<int, string>
 */
class ExtraHolder
{
    use Holder;
}

/**
 * Generic container class for template parameter T.
 *
 * @template T
 */
class Container
{
    /** @var T Description for Container value. */
    protected $value;
}

/**
 * Missing template arguments in extends
 */
class MissingContainer extends Container
{
}

/**
 * Too many template arguments in extends
 * @extends Container<int, string>
 */
class ExtraContainer extends Container
{
}

/**
 * TODO(variance): template-covariant scaffolding for future diagnostics.
 *
 * @template-covariant T
 */
interface CovariantBox
{
    /**
     * Provide a value of the covariant template type.
     *
     * @return T
     */
    public function get();
}

/**
 * Intentionally writes to covariant template position (no diagnostic until variance enforcement).
 * @template-covariant T
 */
trait CovariantWriter
{
    /** @var T|null */
    private $lastValue;

    /**
     * Store a value for variance validation once implemented.
     *
     * @param T $value
     */
    public function set($value): void
    {
        $this->lastValue = $value;
    }
}

/**
 * Contravariant trait used to verify property variance enforcement.
 *
 * @template-contravariant T
 */
trait ContravariantReader
{
    /** @var T */
    private $consumed;
}

/**
 * Covariant read-only array property should still be treated as invariant.
 *
 * @template-covariant T
 */
class CovariantReadonlyArrayProperty
{
    /**
     * @var array<T>
     * @phan-read-only
     */
    private $items;
}

/**
 * Contravariant write-only array property should still be treated as invariant.
 *
 * @template-contravariant T
 */
class ContravariantWriteOnlyArrayProperty
{
    /**
     * @var array<T>
     * @phan-write-only
     */
    private $items;
}
