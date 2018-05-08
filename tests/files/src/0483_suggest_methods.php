<?php

/**
 * @method string magicMethod()
 * @method static string magicStaticMethod()
 *
 * @phan-forbid-undeclared-magic-methods
 */
class BaseClass483 {
    private function baseMethod() {
        echo "Invoked\n";
    }

    public function __call($method, $args) {
    }

    public function __callStatic($method, $args) {
    }
}

/**
 * @phan-forbid-undeclared-magic-methods
 */
class SubClass483 extends BaseClass483 {
    private function privateMethodInSameClass() {
        echo "Called\n";
    }

    protected static function protectedStaticMethodInSameClass() {
        echo "Called\n";
    }

    public function example() {
        $this->_baseMethod();
        $this->_methodNotResemblingOtherMethods();
        self::_staticMethodNotResemblingOtherMethods();
        $this->rivateMethodInSameClass();
        $this->crotectedStaticMethodInSameClass();
        self::_protectedStaticMethodInSameClass();
        $this->magicMethod();
        self::_magicMethod();
        $this->_magicMethod();
        self::_magicStaticMethod();
        $this->_magicStaticMethod();
    }
}
