<?php
class NoUnextractableSuffix {
    /** @var array*/
    public static $bar = [];

    /** @return array*/
    public function test() : array {
        return [];
    }
}

class UnextractableSuffix {
    /** @var array*something */
    public static $bar = [];

    /** @return array*something */
    public function test() : array {
        return [];
    }
}
