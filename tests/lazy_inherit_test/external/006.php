<?php
/**
 * @template T
 */
class V006Box
{
    /** @var T */
    protected $value;
    /** @param T $value */
    public function __construct($value) { $this->value = $value; }
    /** @return T */
    public function get() { return $this->value; }
    public function count(): int { return 1; }
}
