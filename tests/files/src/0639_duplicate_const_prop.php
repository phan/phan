<?php
class HasDuplicateConst {
    // emits PhanRedefineProperty
    public $x;
    public $x;

    // emits PhanRedefineClassConst
    const a=2;
    const a=2;
}
