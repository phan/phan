<?php

namespace NS972;
class Foo {
    public function m(int $arg): void {
        echo "In Foo::m\n";
    }
}
class Bar extends Foo {
    public function m(int $arg): void {
        echo "In Bar::m\n";
    }
}
// Note that phan has never implemented resolution for this callable method name syntax.
call_user_func([new Foo(), 'm']);
call_user_func([new Foo(), 'NS972\Foo::m']);
call_user_func([new Foo(), 'NS972\Bar::m']);
call_user_func([new Bar(), 'm']);
call_user_func([new Bar(), 'NS972\Foo::m']);
call_user_func([new Bar(), 'NS972\Bar::m']);
call_user_func('NS972\Bar::NS972\Foo::m');  // invalid
(new Foo())->{'NS972\Foo::m'}();
