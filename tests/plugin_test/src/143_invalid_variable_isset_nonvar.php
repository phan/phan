<?php
class X143{
    /** @var array<string,string> */
    static $queryCache = [];

    function query(string $hash, string $query): string{
        if (isset(self::$queryCache[$hash])) { // should not emit PhanPluginUndeclaredVariableIsset
            return self::$queryCache[$hash];
        }
        if (isset($self::$queryCache[$hash])) { // should emit PhanPluginUndeclaredVariableIsset
            return $self::$queryCache[$hash];
        }
        if (isset('bar'->queryCache[$hash])) { // should not crash.
            return 'x';
        }
        return $query;
    }
}
var_export((new X143())->query('foo', 'bar'));

$a = isset($this->a); // should not throw as the config is set to ignore vars in global scope
