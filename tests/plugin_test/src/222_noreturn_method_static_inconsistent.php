<?php

class Test222NoreturnMethodStaticInconsistent {

    public static function inconsistentExit(string $val): never {
        if ($val === "yes") {
            exit();
        }
    }
}

Test222NoreturnMethodStaticInconsistent::inconsistentExit("yes");
