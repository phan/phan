<?php declare(strict_types=1);
function f246_0(iterable $p) {
    foreach ($p as $v) {}
}
function f246_1(array $p) {
    foreach ($p as $v) {}
}
function f246_2(\ArrayAccess $p) {
    foreach ($p as $v) {}
}
function f246_3(\Traversable $p) {
    foreach ($p as $v) {}
}
function f246_4(iterable $p) {
    f246_0($p);
    f246_1($p); // iterable -/-> array
    f246_2($p); // iterable -/-> ArrayAccess
    f246_3($p); // iterable -/-> Traversable
}
f246_4([1]);
function f246_5(array $p) {
    f246_0($p);
    f246_1($p);
    f246_2($p); // array -/-> ArrayAccess
    f246_3($p); // array -/-> Traversable
}
f246_5([1]);
function f246_6(\ArrayAccess $p) {
    f246_0($p); // ArrayAccess -/-> iterable
    f246_1($p); // ArrayAccess -/-> array
    f246_2($p);
    f246_3($p); // ArrayAccess -/-> Traversable
}
class C246_1 implements \ArrayAccess {
    public function offsetExists($offset) { return false; }
    public function offsetGet($offset) { return null; }
    public function offsetSet($offset, $value) {}
    public function offsetUnset($offset) {}
}
f246_6(new C246_1);
class C246_2 implements Iterator {
    function rewind() {}
    function current() { return null; }
    function key() { return 0; }
    function next() {}
    function valid() { return false; }
}
function f246_7(\Traversable $p) {
    f246_0($p);
    f246_1($p); // Traversable -/-> array
    f246_2($p); // Traversable -/-> ArrayAccess
    f246_3($p);
}
f246_7(new C246_2);
f246_4(new C246_2);
