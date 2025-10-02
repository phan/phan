<?php

class Test {

    public function inconsistentExit(string $val): never {
        if ($val === "yes") {
            exit();
        }
    }
}

(new Test)->inconsistentExit("yes");
