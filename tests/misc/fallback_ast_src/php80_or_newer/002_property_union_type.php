<?php
class Example {
    public static int|string $scalar;
    public stdClass|false $ip;
}

Example::$scalar = 1;
Example::$scalar = 'value';
Example::$scalar = null;
Example::$scalar = false;
Example::$scalar = [];
$e = new Example();
$e->ip = new stdClass();
$e->ip = false;
$e->ip = true;
$e->ip = [];
