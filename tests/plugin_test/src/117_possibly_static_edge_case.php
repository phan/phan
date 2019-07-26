<?php  // NOTE: Only classes/namespaces beginning in PSM are checked in this test by PossiblyStaticMethodPlugin

class PSMBase117 {
    public function instanceMethod() {
        echo "in instance method: ";
        var_export($this);
        echo "\n";
    }
    public static function staticMethod() {
        echo "in static method\n";
    }
}

class PSMSubclass117 extends PSMBase117 {
    // Should not suggest converting to a static method
    public function f1() {
        self::instanceMethod();
    }

    // Should suggest converting to a static method
    public function f2() {
        self::staticMethod();
    }

    // Should not emit issue
    public function f3() {
        PARENT::instanceMethod();
    }

    // Should suggest converting to a static method
    public function f4() {
        Parent::staticMethod();
    }

    // Should suggest converting to a static method
    public function f4b() {
        parent::staticMethod();
    }

    // Should not suggest converting to a static method
    public function f5(string $method_name) {
        PARENT::{$method_name}();
    }
}
$s = new PSMSubclass117();
$s->f1();
$s->f2();
$s->f3();
$s->f4();
$s->f4b();
$s->f5('instanceMethod');
