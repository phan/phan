<?php
class Container352 implements \ArrayAccess
{
    private $values = [];

    public function offsetSet($id, $value) {
        $this->values[$id] = $value;
    }

    public function offsetGet($id) {}

    public function offsetExists($id) {}

    public function offsetUnset($id) {}
}

class Registry352
{
   private static $data = null;

   public static function getData() {
       if (self::$data === null) {
           self::$data = new Container352;
       }
       return self::$data;
   }
}

function test352() {
    $reg = new Registry352;
    $reg::getData()['foo'] = 'bar';
    $reg->getData()['x'] = 'y';
}
test352();
