<?php

class Test {

    public static function doExit(): never {
        exit();
    }

    public static function indirectExit(): never {
        self::doExit();
    }
}

Test::indirectExit();
