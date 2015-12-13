<?php
abstract class AbstractProperties {
    public static $things = ['a', 'b'];
}

$test = AbstractProperties::$things;
$test = AbstractProperties::$things2;
