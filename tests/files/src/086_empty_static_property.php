<?php

class T {
    /** @var T */
    public static T $instance;

    public static function get(): bool {
        return !empty(self::$instance);
    }
}
T::$instance = new T();
var_dump(T::get());
