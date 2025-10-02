<?php

class Test220NoreturnMethodInstanceIndirect {

    public function doExit(): never {
        exit();
    }

    public function indirectExit(): never {
        $this->doExit();
    }
}

(new Test220NoreturnMethodInstanceIndirect)->indirectExit();
