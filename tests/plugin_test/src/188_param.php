<?php

namespace PHPDocParamTest;

class A {
    /**
     * @param string $property
     * @param string $value
     * @param bool $partialMatch
     * @param string $operator
     * @param bool $escape
     * @return static
     */
    public function compareProperty($property, $value, $partialMatch=false, $operator='AND', $escape=true)
    {
        var_export([$property, $value, $partialMatch, $operator, $escape]);
        return $this;
    }
}

/**
 * @method static compareProperty(string $property, string $value, bool|false $partialMatch=false, string $operator='AND', bool|true $escape=true)
 */
class B extends A {
}
(new A())->compareProperty('prop', 'foo');
(new B())->compareProperty('prop', 'foo', null);
