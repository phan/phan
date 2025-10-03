<?php

class Test219NoreturnMethodStaticIndirect {

    public static function doExit(): never {
        exit();
    }

    public static function indirectExit(): never {
        self::doExit();
    }
}

Test219NoreturnMethodStaticIndirect::indirectExit();
