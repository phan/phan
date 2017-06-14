<?php
class A { }
$a = new A;
if($a[1]) { }

class B implements ArrayAccess {
    function offsetExists($offset) { return false; }
    function offsetGet($offset) { return null; }
    function offsetSet($offset,$value) { return; }
    function offsetUnset($offset) { return; }
}

$b = new B;
if($b[1]) { }
