<?php

// Regression test for earlier bugs caused because namespace {} is treated slightly differently from the absence of a namespace statement.
// This was fixed already.

/**
 * @internal
 */
function internalGlobalFunction2() {
}

/**
 * @internal
 */
class ClassWithInternalPublicProperty2
{
    /**
     * @internal
     */
    public $typeId;
    /**
     * @internal
     */
    const MY_CONST = 3;

    /**
     * @internal
     */
    public static function internalMethod() {
    }

    public function test()
    {
        $this->typeId = 1;
        var_export(self::MY_CONST);
        var_export(new ClassWithInternalPublicProperty2());
        self::internalMethod();
        internalGlobalFunction2();
    }
}
