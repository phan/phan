<?php
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
