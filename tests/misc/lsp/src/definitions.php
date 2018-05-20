<?php
namespace {
function my_global_function() {
    echo "Called global function\n";
}

/**
 * @property-read MyOtherClass $other_class
 */
class MyClass {
    const MyClassConst = 2;
    public static $my_static_property = 2;

    public static function myMethod() : MyOtherClass { return new MyOtherClass(); }


    public function myInstanceMethod() {
        echo "In instance method\n";
    }
}

class MyOtherClass {
}

const MY_GLOBAL_CONST = 2;
}  // end global namespace

namespace MyNS\SubNS {

const MY_NAMESPACED_CONST = 2;

class MyNamespacedClass {
    const MyOtherClassConst = [2];
}
}  // end MyNS\SubNS
