<?php

class Test {

    public function doExit(): never {
        exit();
    }

    public function indirectExit(): never {
        $this->doExit();
    }
}

(new Test)->indirectExit();
