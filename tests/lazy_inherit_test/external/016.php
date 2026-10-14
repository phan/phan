<?php
class V016Plain
{
    // Untyped roots: the copies link to the return type of the root (a relaxed link)
    public function untyped() { return 'str'; }
    public function untypedArg($x) { return $x; }
    public function noReturn() { }
}
/**
 * @template T
 */
class V016Generic
{
    public function untyped() { return 1; }
}
class V016TemplateMethod
{
    /**
     * @template U
     * @param U $x
     * @return U
     */
    public function identity($x) { return $x; }
    public function plain() { return 2.5; }
}
