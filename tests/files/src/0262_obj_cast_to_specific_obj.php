<?php

class ObjectCastClass262 {
}

class ObjectCastBug262 {
    /**
     * @param string $className
     * @return object
     */
    public static function mock(string $className) {
        return new $className();
    }

    /**
     * @return NullableClass
     */
    public function returns_nullable_class() {
        return self::mock('NullableClass');
    }

    /**
     * @return array
     */
    public function returns_array() {
        return self::mock('NullableClass');  // should warn
    }
}
