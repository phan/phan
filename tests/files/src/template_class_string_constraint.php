<?php

class TestClass {}
class OtherClass {}
/**
 * @template T of TestClass
 * @param array{class:class-string<T>} $data
 * @return T
 */
function newObject(array $data) {
    $class = $data['class'];
    return new $class();
}
/** @param array{class:class-string<TestClass>} $arr */
function good(array $arr) {
    newObject($arr);
}
/** @param array{class:class-string<OtherClass>} $arr */
function bad(array $arr) {
    newObject($arr);
}

good(['class' => TestClass::class]);
bad(['class' => OtherClass::class]);
