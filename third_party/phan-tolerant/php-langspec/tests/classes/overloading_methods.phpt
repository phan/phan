--TEST--
PHP Spec test generated from ./classes/overloading_methods.php
--FILE--
<?php

error_reporting(-1);

class Widget
{
    public function iDoit()
    {
        echo "Inside " . __METHOD__ . "\n";
        return 99;
    }

    public static function sDoit()
    {
        echo "Inside " . __METHOD__ . "\n";
        return 88;
    }
//*
    public function __call($name, $arguments)
//    public function __call(&$name, &$arguments)
    {
        echo "Calling instance method >$name<\n";
        var_dump($arguments);

        return 987;
    }
//*/
///*
    public static function __callStatic($name, $arguments)
//    public static function __callStatic(&$name, &$arguments)
    {
        echo "Calling static method >$name<\n";
        var_dump($arguments);

        return "hello";
    }
//*/
}

$obj = new Widget;
$v = $obj->iDoit();
$obj->__call('iDoit', []);

$v = $obj->iMethod(10, TRUE, "abc");
var_dump($v);
$obj->__call('iMethod', array(10, TRUE, "abc"));
$obj->__call('123#$%', []);

$v = Widget::sDoit();
Widget::__callStatic('sDoit', []);

$v = Widget::sMethod(NULL, 1.234);
var_dump($v);
Widget::__callStatic('sMethod', array(NULL, 1.234));
Widget::__callStatic('[]{}', []);

--EXPECTF--
Inside Widget::iDoit
Calling instance method >iDoit<
array(0) {
}
Calling instance method >iMethod<
array(3) {
  [0]=>
  int(10)
  [1]=>
  bool(true)
  [2]=>
  string(3) "abc"
}
int(987)
Calling instance method >iMethod<
array(3) {
  [0]=>
  int(10)
  [1]=>
  bool(true)
  [2]=>
  string(3) "abc"
}
Calling instance method >123#$%<
array(0) {
}
Inside Widget::sDoit
Calling static method >sDoit<
array(0) {
}
Calling static method >sMethod<
array(2) {
  [0]=>
  NULL
  [1]=>
  float(1.234)
}
string(5) "hello"
Calling static method >sMethod<
array(2) {
  [0]=>
  NULL
  [1]=>
  float(1.234)
}
Calling static method >[]{}<
array(0) {
}
