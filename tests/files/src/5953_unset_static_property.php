<?php
// unset() of a static property always throws Error: Attempt to unset static property
class UnsetStatic5953 {
    public static $arr = ['k' => 1];
    public static $obj;
    private static $counter = 0;
    public $instance = 1;

    public function run(UnsetStatic5953 $other): void {
        unset(self::$arr);
        unset(static::$counter);
        unset(UnsetStatic5953::$obj);
        unset($other::$arr);
        unset(self::$undeclared);
        // These are allowed: they modify the value of the static property, not the property itself
        unset(self::$arr['k']);
        unset(self::$obj->field);
        unset($this->instance);
    }
}
