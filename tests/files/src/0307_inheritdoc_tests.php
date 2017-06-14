<?php

class A307 {

    /**
     * @param int $x
     * @return int
     */
    public function userDefinedMethod($x) {
        return $x * 2;
    }
}

class B307 implements ArrayAccess, Serializable {
    /**
     * @param string $offset (Part of the phpdoc can be specified, but any remaining fields will be inferred
     */
	public function offsetExists($offset) { }  // Should warn about having no return value. It knows the expected return value from ArrayAccess
    /**
     * @param string $offset
     */
    public function offsetGet($offset) { }  // Warns about not returning `mixed`
    /**
     * @param string $offset
     */
    public function offsetSet($offset, $value) { }
    /**
     * @param string $offset
     */
    public function offsetUnset($offset) { }

    // Should warn about returning int instead of string
    public function serialize() {
        return 42;
    }

    // Should warn.
    public function unserialize($serialized) {
        $x = intdiv($serialized, 2);  // Phan should warn, inferring that $serialized is an int.
        return true; // should warn about returning a value
    }

    public function userDefinedMethod($x) {
        $result = $x->property;  // should warn
        return serialize($x);  // should also warn
    }
}

function testB307() {
    $b = new B307;
    $b->offsetExists(null);  // should warn about invalid offset
    $b->offsetGet(null);  // should warn
    $b->offsetSet(null, 'value');  // should warn
    $b->offsetUnset(null);  // should warn
    // TODO: phan doesn't check short array syntax for array offsets yet
}
testB307();
