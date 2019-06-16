<?php

class Foo {

    public function __call($method, $args_array) {
        return method_exists('DateTime', $method);
    }

    public function __callStatic($method, $args_array) {
        return method_exists('DateTime', $method);
    }

    public function test() {
        $dateTimeZone = new DateTimeZone('UTC-0000');
        var_export($this->setTimezone($dateTimeZone));
        var_export(self::setGlobalTimezone($dateTimeZone, true, 'ignored'));
    }
}
