<?php

namespace {
    $x = new \past\MyNode();
    $v = MyNode::class;

    class ExampleWarning extends ExampleBaseClass implements ExampleInterface {
        use ExampleTrait;
    }

    class ClassWithRepeatedName {}
}

namespace NS3 {
    class MyNode{}
    $other = ClassWithRepeatedName::class;
}

namespace NS2 {
    class ClassWithRepeatedName {}

    try {
    } catch (Exception $e) {
    }
    $x = null;
    var_export($x instanceof InvalidArgumentException);

    class ExampleBaseClass {}
    interface ExampleInterface{}
    trait ExampleTrait{}
}
