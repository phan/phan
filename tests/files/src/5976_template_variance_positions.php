<?php

namespace TemplateVariancePositions5976;

/** @template-contravariant T */
interface Consumer
{
}

/** @template-covariant T */
interface Producer
{
}

/**
 * A covariant template can be used where the class produces the value.
 *
 * @template-covariant T
 */
class Box
{
    /** @var T */
    private $value;

    /** @var list<T> */
    public readonly array $all;

    /** @var T */
    public $writable;

    /** @var Consumer<T> */
    public readonly Consumer $consumer;

    /**
     * @var T
     * @phan-write-only
     */
    public $writeOnly;

    /**
     * The constructor can take a covariant template.
     * @param T $value
     * @param Consumer<T> $consumer
     */
    public function __construct($value, Consumer $consumer)
    {
        $this->value = $value;
        $this->all = [$value];
        $this->writable = $value;
        $this->consumer = $consumer;
        $this->writeOnly = $value;
    }

    /** @return T */
    public function get()
    {
        return $this->value;
    }

    /** @return list<T> */
    public function getAll(): array
    {
        return $this->all;
    }

    /** @return callable():T */
    public function getProducer(): callable
    {
        return fn () => $this->value;
    }

    /** @return callable(T):void */
    public function getConsumer(): callable
    {
        return static function ($value): void {
            \var_dump($value);
        };
    }

    /**
     * The parameter of a parameter is a position where the class produces the value.
     * @param \Closure(T):void $f
     */
    public function each(\Closure $f): void
    {
        $f($this->value);
    }

    /** @param \Closure():T $f */
    public function replaceWith(\Closure $f): void
    {
        $this->value = $f();
    }

    /** @param T $value */
    public function set($value): void
    {
        $this->value = $value;
    }

    /** @param array<string,T> $values */
    public function setAll(array $values): void
    {
        $this->value = \reset($values);
    }

    /** @param Consumer<T> $consumer */
    public function feed(Consumer $consumer): void
    {
        \var_dump($consumer);
    }

    /** @param Producer<T> $producer */
    public function drain(Producer $producer): void
    {
        \var_dump($producer);
    }

    /** @return Producer<T> */
    public function asProducer(): Producer
    {
        throw new \RuntimeException();
    }

    /** @return Consumer<T> */
    public function asConsumer(): Consumer
    {
        throw new \RuntimeException();
    }

    /** @return Consumer<Consumer<T>> */
    public function asConsumerOfConsumers(): Consumer
    {
        throw new \RuntimeException();
    }

    /** @param T $value */
    private function privateSet($value): void
    {
        $this->value = $value;
    }

    public function callPrivate(): void
    {
        $this->privateSet($this->value);
    }
}

/**
 * A contravariant template can be used where the class consumes the value.
 *
 * @template-contravariant T
 */
interface Sink
{
    /** @param T $value */
    public function accept($value): void;

    /** @param callable():T $producer */
    public function acceptFrom(callable $producer): void;

    /** @param callable(T):void $consumer */
    public function forward(callable $consumer): void;

    /** @return T */
    public function last();

    /** @return callable(T):void */
    public function asCallable(): callable;

    /** @return Consumer<T> */
    public function asConsumer(): Consumer;

    /** @return Producer<T> */
    public function asProducer(): Producer;
}

/**
 * A reference in a callable, method-level templates and inherited properties.
 *
 * @template-covariant T
 */
class MoreCases
{
    /** @param callable(T&):void $f */
    public function byReference(callable $f): void
    {
        \var_dump($f);
    }
}

class NotGeneric
{
    /**
     * @template-contravariant U
     * @param U $in
     * @return U
     */
    public function methodTemplate($in)
    {
        return $in;
    }
}

/** @template U */
class WritableBase
{
    /** @var U */
    public $writable;

    /** @param U $u */
    public function __construct($u)
    {
        $this->writable = $u;
    }
}

/**
 * @template-covariant T
 * @extends WritableBase<T>
 */
class CovariantChild extends WritableBase
{
}

/**
 * A reference can be written by the code that receives it.
 *
 * @template-covariant T
 */
class References
{
    /** @var list<T> */
    private array $stored = [];

    /** @param callable(T):void $callback */
    public function replaceCallback(callable &$callback): void
    {
        \var_dump($callback);
    }

    /** @return list<T> */
    public function &valuesByReference(): array
    {
        return $this->stored;
    }

    /** @return list<T> */
    public function values(): array
    {
        return $this->stored;
    }
}

/**
 * @template-contravariant T
 */
class ReferenceSink
{
    /** @param list<T> $items */
    public function replace(array &$items): void
    {
        \var_dump($items);
    }

    /** @param list<T> $items */
    public function take(array $items): void
    {
        \var_dump($items);
    }
}

/**
 * Only one of the templates has a declared variance.
 *
 * @template-covariant A
 * @template B
 */
class MixedBase
{
    /** @var B */
    public $b;

    /** @var A */
    public $a;
}

/**
 * @template-covariant T
 * @extends MixedBase<int, T>
 */
class MixedChild extends MixedBase
{
}

/**
 * @template-covariant T
 * @extends WritableBase<T>
 * @suppress PhanTemplateTypeVarianceViolation
 */
class SuppressedChild extends WritableBase
{
}

/**
 * The class that the return type uses is declared after the function.
 *
 * @template-contravariant T
 * @param T $value
 * @return LaterConsumer<T>
 */
function makeLaterConsumer($value): LaterConsumer
{
    throw new \RuntimeException(\gettype($value));
}

/**
 * @template-contravariant T
 * @param T $in
 * @return T
 */
function returnsContravariant($in)
{
    return $in;
}

/** @template-contravariant T */
interface LaterConsumer
{
}

/**
 * @template-covariant T
 */
class AnnotatedReferences
{
    /** @var T */
    private $value;

    /** @param T $value */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @param T $out @phan-output-reference
     */
    public function output(&$out): void
    {
        $out = $this->value;
    }

    /**
     * @param T $ref
     */
    public function readWrite(&$ref): void
    {
        $ref = $this->value;
    }
}

/** @template-covariant T */
trait PrivateWriter
{
    /** @var T */
    private $written;

    /** @param T $value */
    private function write($value): void
    {
        $this->written = $value;
    }

    /** @return T */
    public function read()
    {
        return $this->written;
    }
}

/**
 * @template-covariant T
 * @use PrivateWriter<T>
 */
class ExposesWriter
{
    use PrivateWriter {
        write as public;
    }
}

/**
 * @template-covariant T
 * @use PrivateWriter<T>
 */
class KeepsWriterPrivate
{
    use PrivateWriter;

    /** @param T $value */
    public function __construct($value)
    {
        $this->write($value);
    }
}
