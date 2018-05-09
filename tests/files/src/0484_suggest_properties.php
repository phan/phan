<?php

/**
 * @property int $magicProperty
 * @phan-forbid-undeclared-magic-properties
 */
class BaseClass483 {
    private $baseProperty;

    public function __get($name) {
        return "Value of $name";
    }
}

/**
 * @phan-forbid-undeclared-magic-methods
 */
class SubClass483 extends BaseClass483 {
    private $privatePropertyInSameClass = 'v';

    protected static $protectedStaticPropertyInSameClass;

    public function example() {
        var_export($this->_baseProperty);
        var_export($this->_propertyNotResemblingOtherPropertys);
        var_export(self::$_staticPropertyNotResemblingOtherPropertys);
        var_export($this->rivatePropertyInSameClass);
        var_export($this->crotectedStaticPropertyInSameClass);
        var_export(self::$_protectedStaticPropertyInSameClass);
        var_export(self::$protected_static_property_insameclass);
        var_export($this->magicProperty);
        var_export(self::$_magicProperty);
        var_export($this->_magicProperty);
    }
}
