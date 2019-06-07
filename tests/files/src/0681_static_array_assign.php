<?php

class X681 implements ArrayAccess {
    /**
     * @param string $key
     */
    public function offsetExists($key) {
        return true;
    }

    /**
     * @param string $key
     */
    public function offsetGet($key) {
        return 'X';
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function offsetSet($key, $value) {
        echo "Stub to set $key to $value\n";
    }

    /**
     * @param string $key
     */
    public function offsetUnset($key) {
        echo "Stub to unset $key\n";
    }

    public function testGetSet() {
        $this['field'] = 'X';
    }
    public function testGet() {
        var_export($this['otherField']);
    }

    public function testUnset() {
        unset($this['myField']);
    }

    public function testIsset() {
        var_export(isset($this['otherField']));
    }
}

class Y681 {
    public function testSetInvalid() {
        $this['field'] = 'X';
    }
    public function testGetInvalid() {
        var_export($this['otherField']);
    }
    public function testUnsetInvalid() {
        unset($this['myField']);
    }
    public function testIsset() {
        var_export(isset($this['otherField']));  // Warns as a side effect of checking if isset is redundant.
    }
}
