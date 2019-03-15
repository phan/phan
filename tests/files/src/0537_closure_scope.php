<?php

class C537 {
    function test () {
        /**
         * @phan-closure-scope int should warn
         */
        $f = function () { return $this; };
        return $f;
    }
}
