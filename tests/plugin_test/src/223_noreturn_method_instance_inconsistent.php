<?php

class Test223NoreturnMethodInstanceInconsistent {

    public function inconsistentExit(string $val): never {
        if ($val === "yes") {
            exit();
        }
    }
}

(new Test223NoreturnMethodInstanceInconsistent)->inconsistentExit("yes");
