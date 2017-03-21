<?php

class A279 {
    public function foo(int $args) { }

    public function bar(int ...$args) { }

    public function baz(int ...$args) { }

    public function bag(int ...$args) { }

    public function bat() { }

    // check the way phan compares variadic types with non-variadic types
    public function man(array $args) { }

    public function dog(...$args) { }
}

class B279 extends A279 {
    public function foo(int... $args) { }  // incompatible, according to php

    public function bar(int $args) { } // incompatible, according to php

    public function baz(int ...$args) { }  // compatible

    public function bag() { }  // incompatible, according to php

    public function bat(int ...$args) { }  // compatible?

    // check the way phan compares variadic types with non-variadic types
    public function man(...$args) { }  // incompatible

    public function dog(array $args) { }  // incompatible
}
