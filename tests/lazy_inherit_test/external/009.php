<?php
class V009Base
{
    // Untyped: the copies link to the return type of this method (linked_yield: eager)
    public function gen() { yield 1; }
    /** @return iterable<int> */
    public function genIter(): iterable { yield 1; }
    public function genTyped(): Generator { yield 'a'; }
    /** @return int[] */
    public function notGen() { return [1]; }
}
