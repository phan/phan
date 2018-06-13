<?php

namespace NS;

use stdClass;

class MyClass496 {
    public static function myStaticMethod(stdClass $arg) {}
    public function myInstanceMethod(stdClass $arg) {}
    /** @var array */
    public $static_prop = [];
}

call_user_func(function() {
    $x = 'strlen';
    echo $x(rand(0,10));

    // TODO: Phan should consistently use the FQSEN casing of the declaration instead of the first occurrence here
    // (This example is consistent, though)
    echo count(MyClass496::class);

    $m = 'myStaticMethod';
    echo MyClass496::$m();
    $i = 'myInstanceMethod';
    echo (new MyClass496())->$i();

    $name = 'static_prop';
    echo MyClass496::${$name};
});
