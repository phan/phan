<?php

namespace NS1;

use ArrayAccess;
use ArrayObject;
use stdClass;

/** @return mixed */
function test1() { return new ArrayObject(); }

/** @return object */
function test2() {
  static $instance;
  if ($instance === null) {
    $instance = new ArrayObject();
  }
  return $instance;
}
function test3(): ArrayAccess
{
    return new ArrayObject();
}

/**
 * @param object $o
 * @return mixed
 */
function test4($o)
{
    return $o;
}

class Methods {

    /**
     * @param stdClass $o
     * @return array TODO: Phan fails to emit PhanPluginMoreSpecificActualReturnTypeContainsFQSEN for (at)return object[]
     */
    function test5($o)
    {
        return [$o];
    }
    /**
     * @param stdClass $o
     * @return object[]
     */
    function test6($o)
    {
        return [$o];
    }
    /**
     * @return iterable<object>
     */
    function test7()
    {
        return [$this];
    }
}
