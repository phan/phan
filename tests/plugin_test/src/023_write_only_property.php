<?php

class MyClass23 {
    public $a = 2;
    protected $b = [];
    private $c = [];
    public $d = 2;
    /** @suppress PhanWriteOnlyPublicProperty */
    public $e = 2;
    /**
     * @suppress PhanUnreferencedPublicProperty
     * also suppressed WriteOnly*Property as a side effect
     */
    public $f = 2;
    public $g = 2;

    public static function main() {
        $m = new MyClass23();
        $m->a = 22;
        $m->a = 11;

        $m->b[] = 3;
        $m->b['x'] = 3;
        $m->c['key']['value'] = 2;
        $m->d = count($_ENV);
        var_export($m->d);
        $m->e = 11;
        $m->f = 12;
    }
}
MyClass23::main();

class OtherClass extends MyClass23 {
    public $subclassProp = 3;
    public $subclassPropUnused = 3;

    public static function main() {
        $m = new self();
        $m->subclassProp = 11;
    }
}
OtherClass::main();
