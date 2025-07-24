<?php

class Test {

    public static function inconsistentExit(string $val): never {
        if ($val === "yes") {
            exit();
        }
    }
}

Test::inconsistentExit("yes");
