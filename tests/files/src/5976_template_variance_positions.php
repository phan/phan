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
