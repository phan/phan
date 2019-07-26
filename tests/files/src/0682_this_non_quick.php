<?php

class X682 {
    public function acceptOther($y) {
        var_export($y->iProp);
    }

    /** @param static $y */
    public function acceptOtherTyped($y) {
        var_export($y);
    }
}

class Y682 {
    public $aProp;
    public function handle(X682 $x) {
        $x->acceptOther($this);
        $x->acceptOtherTyped($this);  // should warn
        $x->acceptOtherTyped($x);
    }
}
