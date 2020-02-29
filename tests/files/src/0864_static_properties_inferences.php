<?php
namespace DefiningStaticProperties;

trait T {
    public static $traitInstance;
}

class A {
    public static $instance;
}
class B1 extends A {
    use T;
}
class B2 extends A {
    use T;
}

B1::$traitInstance = new \ArrayObject();
B2::$traitInstance->missingMethod();  // Phan should not yet infer a value for B2::$traitInstance because it's not the same property
B1::$traitInstance->missingMethod();  // should warn
B2::$traitInstance = new \SplObjectStorage();
B2::$traitInstance->missingMethod();  // should warn
B1::$instance = new A();
B2::$instance->missingMethod();  // should warn
