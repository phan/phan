<?php
abstract class AbstractWithConstants {
    public static $things = ['a', 'b'];
}

$test = AbstractWithConstants::$things;
