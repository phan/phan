<?php

namespace {
/**
 * @internal
 */
function internalGlobalFunction() {
}

/**
 * @internal
 */
class ClassWithInternalPublicProperty
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
        var_export(new ClassWithInternalPublicProperty());
        self::internalMethod();
        internalGlobalFunction();
        var_export(OtherNS\OtherInternalClass::$internal_prop);
        var_export(OtherNS\OtherInternalClass::FOO);
        var_export(OtherNS\OtherInternalClass::test());
        var_export(OtherNS\other_internal_function());
    }
}
}

namespace OtherNS {
/** @internal */
function other_internal_function() {
}
/** @internal */
class OtherInternalClass {
    /**
     * @internal
     */
    public static $internal_prop;
    /**
     * @internal
     */
    const FOO = 'foo';
    /**
     * @internal
     */
    public static function test() {
    }
}
}
