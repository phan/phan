<?php

/**
 * A class with a template.
 *
 * @template T
 */
class Templated
{
    /**
     * The template type.
     *
     * @var class-string<T>
     */
    protected $type;

    /**
     * The constructor.
     *
     * @param class-string<T> $type
     */
    public function __construct(string $type = stdClass::class)
    {
        $this->type = $type;
    }

    /**
     * Create an instance of the type.
     *
     * @return T
     */
    public function make()
    {
        return new $this->type;
    }
}

$class = new Templated();

$inst = $class->make();

'@phan-debug-var $class, $inst';
