<?php

/**
 * Test that generic type inference works for methods in stub-defined classes
 * like SplObjectStorage, where the method signature returns 'mixed' but
 * the PHPDoc has template types like @return TValue
 */

/** @param SplObjectStorage<stdClass,string> $storage */
function testSplObjectStorageOffsetGet(SplObjectStorage $storage, stdClass $key) {
    $value = $storage->offsetGet($key);
    // $value should be inferred as 'string', not 'mixed'

    // This should pass - string is compatible with string
    function expectsString(string $s) {}
    expectsString($value);

    // This should fail - string is not compatible with int
    function expectsInt(int $i) {}
    expectsInt($value);
}

/** @param SplObjectStorage<stdClass,int> $storage */
function testSplObjectStorageGetInfo(SplObjectStorage $storage) {
    $info = $storage->getInfo();
    // $info should be inferred as 'int', not 'mixed'

    // This should pass - int is compatible with int
    expectsInt($info);

    // This should fail - int is not compatible with string
    expectsString($info);
}

/** @param SplObjectStorage<Exception,string> $storage */
function testSplObjectStorageCurrent(SplObjectStorage $storage) {
    $current = $storage->current();
    // $current should be inferred as 'Exception', not 'object'

    // This should pass - Exception is compatible with Throwable
    function expectsThrowable(Throwable $t) {}
    expectsThrowable($current);

    // This should fail - Exception is not compatible with stdClass
    function expectsStdClass(stdClass $obj) {}
    expectsStdClass($current);
}

/** @template T1 @template T2 */
class UserDefinedGeneric {
    /** @param T1 $key @return T2 */
    public function get($key) { return null; }
}

/** @param UserDefinedGeneric<int,bool> $obj */
function testUserDefinedGeneric(UserDefinedGeneric $obj) {
    $result = $obj->get(42);
    // $result should be inferred as 'bool', not 'mixed'

    // This should pass - bool is compatible with bool
    function expectsBool(bool $b) {}
    expectsBool($result);

    // This should fail - bool is not compatible with string
    expectsString($result);
}
