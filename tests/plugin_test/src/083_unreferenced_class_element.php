<?php

class CUnref {
    private const X = 2;
    protected const Y = 2;
    protected static $protectedProp;
    private function privateMethod() {}
}
var_export(new CUnref());
