<?php

class Test504 {
    /** @var int[] */
    public static $prop = [2];
    /** @var int[] */
    public static $otherProp = [3];

    public static function test() {
        $t = self::class;
        $t::$prop = 2;
        echo $t::$otherProp;
        $t::$propMissing = 2;
        echo $t::$otherMissingProp;
    }
}
