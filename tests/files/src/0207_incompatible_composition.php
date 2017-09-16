<?php

class C1 {
    public $p = 42;
    /** @suppress PhanIncompatibleCompositionProp (sanity check of suppression) */
    public $v = 42;
}

trait T1 {
    public $p = 'string';
    /** @suppress PhanIncompatibleCompositionProp (sanity check of suppression) */
    public $v = 'string';
}

class C2 extends C1 {
    use T1;
}
